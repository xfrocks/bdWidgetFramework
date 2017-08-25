<?php

class WidgetFramework_Model_Cache extends XenForo_Model
{
    const OPTION_CACHE_STORE_CACHE = 'cache';
    const OPTION_CACHE_STORE_DB = 'db';
    const OPTION_CACHE_STORE_FILE = 'file';

    const KEY_TIME = 'time';
    const KEY_HTML = 'html';
    const KEY_EXTRA_DATA = 'extraData';

    public static $slamDefenseSeconds = 30;

    protected static $_preloadList = array();
    protected static $_queriedData = array();

    /**
     * @param string $cacheId
     */
    public function preloadCache($cacheId)
    {
        self::$_preloadList[$cacheId] = true;
    }

    /**
     * @param int $widgetId
     * @param string $cacheId
     * @return array
     * @throws XenForo_Exception
     */
    public function getCache($widgetId, $cacheId)
    {
        if (isset(self::$_queriedData[$cacheId][$widgetId])) {
            return self::$_queriedData[$cacheId][$widgetId];
        }

        $cacheStore = WidgetFramework_Option::get('cacheStore');
        if (empty($cacheStore)) {
            return array();
        }

        switch ($cacheStore) {
            case self::OPTION_CACHE_STORE_CACHE:
                $cached = $this->_cache_getCache($widgetId, $cacheId);
                break;
            case self::OPTION_CACHE_STORE_DB:
                $cached = $this->_db_getCache($widgetId, $cacheId);
                break;
            case self::OPTION_CACHE_STORE_FILE:
                $cached = $this->_file_getCache($widgetId, $cacheId);
                break;
            default:
                throw new XenForo_Exception('Unsupported cache store: ' . $cacheStore);
        }

        if (empty($cached[self::KEY_TIME])) {
            // what?!
            $cached = array();
        }

        if (!empty($cached[self::KEY_TIME])) {
            $invalidated = $this->_getInvalidatedCache();
            if (isset($invalidated[$widgetId])
                && $invalidated[$widgetId] > $cached[self::KEY_TIME]
            ) {
                // this widget has been invalidated at some point in the past
                $cached = array();
            }
        }

        if (!empty($cached[self::KEY_TIME])) {
            $cutOff = XenForo_Application::$time - WidgetFramework_Option::get('cacheCutoffDays') * 86400;
            if ($cutOff > $cached[self::KEY_TIME]) {
                // look like a stale cache
                $cached = array();
            }
        }

        self::$_queriedData[$cacheId][$widgetId] = $cached;

        return $cached;
    }

    /**
     * @param int $widgetId
     * @param string $cacheId
     * @param array $data
     * @return bool
     */
    public function bumpCache($widgetId, $cacheId, array $data)
    {
        if (!isset($data[self::KEY_TIME])
            || self::$slamDefenseSeconds < 1
        ) {
            return false;
        }

        $data[self::KEY_TIME] = XenForo_Application::$time + self::$slamDefenseSeconds;

        return $this->_setCache($widgetId, $cacheId, $data);
    }

    /**
     * @param int $widgetId
     * @param string $cacheId
     * @param string $html
     * @param array $extraData
     * @param array $options
     * @return bool
     */
    public function setCache(
        $widgetId,
        $cacheId,
        $html,
        array $extraData = array(),
        array $options = array()
    ) {
        $html = $this->_cleanUpHtml($html);

        $data = array(
            self::KEY_HTML => $html,
            self::KEY_TIME => XenForo_Application::$time,
        );
        if (!empty($extraData)) {
            $data[self::KEY_EXTRA_DATA] = $extraData;
        }

        $set = $this->_setCache($widgetId, $cacheId, $data);
        self::$_queriedData[$cacheId][$widgetId] = $data;

        return $set;
    }

    protected function _setCache($widgetId, $cacheId, array $data)
    {
        $cacheStore = WidgetFramework_Option::get('cacheStore');

        switch (WidgetFramework_Option::get('cacheStore')) {
            case self::OPTION_CACHE_STORE_CACHE:
                return $this->_cache_setCache($widgetId, $cacheId, $data);
            case self::OPTION_CACHE_STORE_DB:
                return $this->_db_setCache($widgetId, $cacheId, $data);
            case self::OPTION_CACHE_STORE_FILE:
                return $this->_file_setCache($widgetId, $cacheId, $data);
            default:
                throw new XenForo_Exception('Unsupported cache store: ' . var_export($cacheStore, true));
        }
    }

    protected function _cache_getCache($widgetId, $cacheId)
    {
        $cache = $this->_getCache();

        $cached = $cache->load($this->_cache_getSafeCacheId($widgetId, $cacheId));

        if (XenForo_Application::debugMode()) {
            XenForo_Helper_File::log(__CLASS__, sprintf(
                '_cache_getCache: $widgetId=%d, $cacheId=%s, '
                . 'is_string($cached)=%s',
                $widgetId,
                $cacheId,
                is_string($cached)
            ));
        }

        if (is_string($cached)) {
            $cached = self::_unserialize($cached);
        }

        if (is_array($cached)) {
            return $cached;
        } else {
            return array();
        }
    }

    protected function _cache_setCache($widgetId, $cacheId, array $data)
    {
        $cache = $this->_getCache();
        $dataSerialized = self::_serialize($data);
        $cache->save($dataSerialized, $this->_cache_getSafeCacheId($widgetId, $cacheId));

        if (XenForo_Application::debugMode()) {
            XenForo_Helper_File::log(__CLASS__, sprintf(
                '_cache_setCache: $widgetId=%d, $cacheId=%s, '
                . 'strlen($dataSerialized)=%d',
                $widgetId,
                $cacheId,
                strlen($dataSerialized)
            ));
        }

        return true;
    }

    protected function _cache_getSafeCacheId($widgetId, $cacheId)
    {
        $safeCacheId = sprintf('%s_%d', $cacheId, $widgetId);
        $safeCacheId = preg_replace('#[^a-zA-Z0-9_]#', '', $safeCacheId);

        return $safeCacheId;
    }

    protected function _db_getCache($widgetId, $cacheId)
    {
        $cacheRecord = $this->_db_getCacheRecord($cacheId);

        if (isset($cacheRecord[$widgetId])) {
            return $cacheRecord[$widgetId];
        } else {
            return array();
        }
    }

    protected function _db_setCache($widgetId, $cacheId, array $data)
    {
        $cacheRecord = $this->_db_getCacheRecord($cacheId);
        $cacheRecord[$widgetId] = $data;
        $cacheRecordDataJson = json_encode($cacheRecord);

        $this->_getDb()->query('
            REPLACE INTO `xf_widgetframework_cache`
            SET cache_id = ?, data = ?, cache_date = ?
        ', array(
            $this->_db_getSafeCacheId($cacheId),
            $cacheRecordDataJson,
            time()
        ));

        if (XenForo_Application::debugMode()) {
            XenForo_Helper_File::log(__CLASS__, sprintf(
                '_db_setCache: $widgetId=%d, $cacheId=%s, '
                . 'strlen($cacheRecordDataJson)=%d',
                $widgetId,
                $cacheId,
                strlen($cacheRecordDataJson)
            ));
        }

        return true;
    }

    protected function _db_getCacheRecord($cacheId)
    {
        if (!isset(self::$_queriedData[$cacheId])) {
            $cacheIds = array($cacheId => $this->_db_getSafeCacheId($cacheId));

            foreach (self::$_preloadList as $_cacheId => $_needPreload) {
                $cacheIds[$_cacheId] = $this->_db_getSafeCacheId($_cacheId);
            }
            self::$_preloadList = array();

            if (XenForo_Application::debugMode()) {
                XenForo_Helper_File::log(__CLASS__, sprintf(
                    '_db_getCacheRecord: $cacheId=%s, $cacheIds=%s',
                    $cacheId,
                    implode(', ', $cacheIds)
                ));
            }

            $cacheRecords = $this->fetchAllKeyed('
                SELECT *
                FROM `xf_widgetframework_cache`
                WHERE cache_id IN (' . $this->_getDb()->quote($cacheIds) . ')
            ', 'cache_id');

            foreach ($cacheIds as $_cacheId => $_safeCacheId) {
                self::$_queriedData[$_cacheId] = array();

                if (!empty($cacheRecords[$_safeCacheId]['data'])) {
                    self::$_queriedData[$_cacheId] = json_decode($cacheRecords[$_safeCacheId]['data'], true);
                }
            }
        }

        return self::$_queriedData[$cacheId];
    }

    protected function _db_getSafeCacheId($cacheId)
    {
        if (strlen($cacheId) > 255) {
            return sprintf('%s_%s', substr($cacheId, 0, 222), md5($cacheId));
        } else {
            return $cacheId;
        }
    }

    protected function _file_getCache($widgetId, $cacheId)
    {
        $filePath = $this->_file_getDataFilePath($widgetId, $cacheId);
        if (file_exists($filePath)) {
            $fileContents = file_get_contents($filePath);
            $data = self::_unserialize($fileContents);
            if (is_array($data)) {
                return $data;
            }
        }

        return array();
    }

    protected function _file_setCache($widgetId, $cacheId, array $data)
    {
        $set = false;
        $dataSerialized = self::_serialize($data);

        $filePath = $this->_file_getDataFilePath($widgetId, $cacheId);
        $dirPath = dirname($filePath);
        if (XenForo_Helper_File::createDirectory($dirPath)) {
            $set = @file_put_contents($filePath, $dataSerialized) !== false;
        }

        if ($set) {
            XenForo_Helper_File::makeWritableByFtpUser($filePath);
        }

        return $set;
    }

    protected function _file_getDataFilePath($widgetId, $cacheId)
    {
        if (!empty($widgetId)) {
            $filePath = sprintf('%s/%s', $cacheId, $widgetId);
        } else {
            $filePath = $cacheId;
        }

        $filePath = str_replace('_', '/', $filePath);
        $filePath = preg_replace('#[^0-9a-zA-Z\/]#', '', $filePath);
        $filePath = trim($filePath, '/');

        return sprintf(
            '%s/WidgetFramework/cache/%s.%s',
            XenForo_Helper_File::getInternalDataPath(),
            $filePath,
            function_exists('igbinary_serialize') ? 'ibn' : 'bin'
        );
    }

    public function invalidateCache($widgetId)
    {
        $invalidatedCache = $this->_getInvalidatedCache();
        $invalidatedCache[$widgetId] = XenForo_Application::$time;

        $cutOff = XenForo_Application::$time - WidgetFramework_Option::get('cacheCutoffDays') * 86400;
        foreach (array_keys($invalidatedCache) as $widgetId) {
            if ($invalidatedCache[$widgetId] < $cutOff) {
                unset($invalidatedCache[$widgetId]);
            }
        }

        $this->_setInvalidatedCache($invalidatedCache);
    }

    protected function _getInvalidatedCache()
    {
        return XenForo_Application::getSimpleCacheData(WidgetFramework_Core::SIMPLE_CACHE_INVALIDATED_WIDGETS);
    }

    protected function _setInvalidatedCache(array $invalidatedCache)
    {
        XenForo_Application::setSimpleCacheData(
            WidgetFramework_Core::SIMPLE_CACHE_INVALIDATED_WIDGETS,
            $invalidatedCache
        );
    }

    protected function _cleanUpHtml($html)
    {
        $html = preg_replace('#(\s)\s+#', '$1', $html);
        $html = utf8_trim($html);

        return $html;
    }

    protected static function _serialize($data)
    {
        if (function_exists('igbinary_serialize')) {
            return igbinary_serialize($data);
        } else {
            return serialize($data);
        }
    }

    protected static function _unserialize($serialized)
    {
        if (function_exists('igbinary_unserialize')) {
            return @igbinary_unserialize($serialized);
        } else {
            return @unserialize($serialized);
        }
    }
}
