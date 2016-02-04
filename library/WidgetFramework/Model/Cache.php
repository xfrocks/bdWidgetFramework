<?php

class WidgetFramework_Model_Cache extends XenForo_Model
{
    const OPTION_CACHE_STORE = 'method';
    const OPTION_CACHE_STORE_CACHE = 'cache';
    const OPTION_CACHE_STORE_DB = 'db';
    const OPTION_CACHE_STORE_FILE = 'file';

    const INVALIDED_CACHE_ITEM_NAME = 'invalidated_cache';
    const KEY_TIME = 'time';
    const KEY_HTML = 'html';
    const KEY_EXTRA_DATA = 'extraData';

    protected static $_preloadList = array();
    protected static $_queriedData = array();

    public function preloadCache($cacheId)
    {
        self::$_preloadList[$cacheId] = true;

        return true;
    }

    public function getCache($widgetId, $cacheId, array $options = array())
    {
        $cacheStore = WidgetFramework_Option::get('cacheStore');
        if (isset($options[self::OPTION_CACHE_STORE])) {
            $cacheStore = $options[self::OPTION_CACHE_STORE];
        }

        if (empty($cacheStore)) {
            return array();
        }

        self::_modifyCacheId($cacheId);

        if (isset(self::$_queriedData[$cacheId][$widgetId])) {
            return self::$_queriedData[$cacheId][$widgetId];
        }

        switch ($cacheStore) {
            case self::OPTION_CACHE_STORE_CACHE:
                $cached = $this->_cache_getCache($widgetId, $cacheId);
                break;
            case self::OPTION_CACHE_STORE_DB:
                $cached = $this->_db_getCache($widgetId, $cacheId, $options);
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

    public function setCache(
        $widgetId,
        $cacheId,
        $html,
        array $extraData = array(),
        array $options = array()
    ) {
        $cacheStore = WidgetFramework_Option::get('cacheStore');
        if (isset($options[self::OPTION_CACHE_STORE])) {
            $cacheStore = $options[self::OPTION_CACHE_STORE];
        }

        if (empty($cacheStore)) {
            return array();
        }

        self::_modifyCacheId($cacheId);

        $data = array(
            self::KEY_HTML => $html,
            self::KEY_TIME => XenForo_Application::$time,
        );
        if (!empty($extraData)) {
            $data[self::KEY_EXTRA_DATA] = $extraData;
        }

        switch ($cacheStore) {
            case self::OPTION_CACHE_STORE_CACHE:
                $cacheSet = $this->_cache_setCache($widgetId, $cacheId, $data);
                break;
            case self::OPTION_CACHE_STORE_DB:
                $cacheSet = $this->_db_setCache($widgetId, $cacheId, $data, $options);
                break;
            case self::OPTION_CACHE_STORE_FILE:
                $cacheSet = $this->_file_setCache($widgetId, $cacheId, $data);
                break;
            default:
                throw new XenForo_Exception('Unsupported cache store: ' . $cacheStore);
        }

        if ($cacheSet) {
            self::$_queriedData[$cacheId][$widgetId] = $data;
        }

        return $cacheSet;
    }

    protected function _cache_getCache($widgetId, $cacheId)
    {
        $cache = $this->_getCache();

        $cached = $cache->load($this->_cache_getSafeCacheId($widgetId, $cacheId));

        if (XenForo_Application::debugMode()) {
            XenForo_Helper_File::log(__CLASS__, sprintf('_cache_getCache: $widgetId=%d, $cacheId=%s, '
                . 'is_string($cached)=%s',
                $widgetId, $cacheId, is_string($cached)));
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
            XenForo_Helper_File::log(__CLASS__, sprintf('_cache_setCache: $widgetId=%d, $cacheId=%s, '
                . 'strlen($dataSerialized)=%d',
                $widgetId, $cacheId, strlen($dataSerialized)));
        }

        return true;
    }

    protected function _cache_getSafeCacheId($widgetId, $cacheId)
    {
        $safeCacheId = sprintf('%s_%d', $cacheId, $widgetId);
        $safeCacheId = preg_replace('#[^a-zA-Z0-9_]#', '', $safeCacheId);

        return $safeCacheId;
    }

    protected function _db_getCache($widgetId, $cacheId, array $options = array())
    {
        $cacheRecord = $this->_db_getCacheRecord($cacheId, $options);

        if (isset($cacheRecord[$widgetId])) {
            return $cacheRecord[$widgetId];
        } else {
            return array();
        }
    }

    protected function _db_setCache($widgetId, $cacheId, array $data, array $options)
    {
        $cacheRecord = $this->_db_getCacheRecord($cacheId, $options);
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
            XenForo_Helper_File::log(__CLASS__, sprintf('_db_setCache: $widgetId=%d, $cacheId=%s, '
                . 'strlen($cacheRecordDataJson)=%d',
                $widgetId, $cacheId, strlen($cacheRecordDataJson)));
        }

        return true;
    }

    protected function _db_getCacheRecord($cacheId, array $options)
    {
        if (!isset(self::$_queriedData[$cacheId])) {
            $cacheIds = array($cacheId => $this->_db_getSafeCacheId($cacheId));

            if (empty($options[self::OPTION_CACHE_STORE])) {
                // only perform reload if cache store is not forced
                foreach (self::$_preloadList as $_cacheId => $_needPreload) {
                    $cacheIds[$_cacheId] = $this->_db_getSafeCacheId($_cacheId);
                }
                self::$_preloadList = array();
            }

            if (XenForo_Application::debugMode()) {
                XenForo_Helper_File::log(__CLASS__, sprintf('_db_getCacheRecord: $cacheId=%s, $cacheIds=%s',
                    $cacheId, implode(', ', $cacheIds)));
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
        $fileData = $this->_file_readDataFromFile($cacheId);

        if (isset($fileData[$widgetId])) {
            return $fileData[$widgetId];
        } else {
            return array();
        }
    }

    protected function _file_setCache($widgetId, $cacheId, array $data)
    {
        $fileData = $this->_file_readDataFromFile($cacheId);
        $fileData[$widgetId] = $data;
        $fileDataSerialized = self::_serialize($fileData);

        $filePath = $this->_file_getDataFilePath($cacheId);
        $dirPath = dirname($filePath);
        if (XenForo_Helper_File::createDirectory($dirPath)) {
            file_put_contents($filePath, $fileDataSerialized);
        }

        if (XenForo_Application::debugMode()) {
            XenForo_Helper_File::log(__CLASS__, sprintf('_file_setCache: $widgetId=%d, $cacheId=%s, '
                . 'strlen($fileDataSerialized)=%d',
                $widgetId, $cacheId, strlen($fileDataSerialized)));
        }

        return true;
    }

    protected function _file_readDataFromFile($cacheId)
    {
        if (!isset(self::$_queriedData[$cacheId])) {
            self::$_queriedData[$cacheId] = array();

            $filePath = $this->_file_getDataFilePath($cacheId);
            if (file_exists($filePath)) {
                $fileContents = file_get_contents($filePath);
                $data = self::_unserialize($fileContents);
                if (is_array($data)) {
                    self::$_queriedData[$cacheId] = $data;
                }
            }

            if (XenForo_Application::debugMode()) {
                XenForo_Helper_File::log(__CLASS__, sprintf('_file_readDataFromFile: $cacheId=%s, '
                    . 'count(self::$_queriedData[$cacheId])=%d',
                    $cacheId, count(self::$_queriedData[$cacheId])));
            }
        }

        return self::$_queriedData[$cacheId];
    }

    protected function _file_getDataFilePath($cacheId)
    {
        $filePath = str_replace('_', '/', $cacheId);
        $filePath = preg_replace('#[^0-9a-zA-Z\/]#', '', $filePath);
        $filePath = trim($filePath, '/');

        return sprintf('%s/WidgetFramework/cache/%s.bin', XenForo_Helper_File::getInternalDataPath(), $filePath);
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
        return XenForo_Application::getSimpleCacheData(self::INVALIDED_CACHE_ITEM_NAME);
    }

    protected function _setInvalidatedCache(array $invalidatedCache)
    {
        XenForo_Application::setSimpleCacheData(self::INVALIDED_CACHE_ITEM_NAME, $invalidatedCache);
    }

    protected static function _modifyCacheId(&$cacheId)
    {
        static $modifiers = false;

        if ($modifiers === false) {
            $modifiers = '';
            $modifiersArray = array();
            $visitor = XenForo_Visitor::getInstance();
            $options = XenForo_Application::getOptions();

            if (!empty($visitor['language_id'])
                && $visitor['language_id'] != $options->get('defaultLanguageId')
            ) {
                $modifiersArray[] = sprintf('l%d', $visitor['language_id']);
            }

            if ($visitor['style_id'] > 0) {
                $modifiersArray[] = sprintf('s%d', $visitor['style_id']);
            }

            if (!empty($visitor['timezone'])
                && $visitor['timezone'] != $options->get('guestTimeZone')
            ) {
                $modifiersArray[] = sprintf('tz%s', $visitor['timezone']);
            }

            if (!empty($modifiersArray)) {
                $modifiers = '_' . implode('_', $modifiersArray);
                $modifiers = preg_replace('#[^0-9a-zA-Z_]#', '', $modifiers);
            }
        }

        $cacheId .= $modifiers;
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
