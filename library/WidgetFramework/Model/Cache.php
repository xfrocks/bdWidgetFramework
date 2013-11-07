<?php

class WidgetFramework_Model_Cache extends XenForo_Model
{
	const CACHED_WIDGETS_BY_PCID_PREFIX = 'wfc_';
	const INVALIDED_CACHE_ITEM_NAME = 'invalidated_cache';
	const KEY_TIME = 'time';
	const KEY_HTML = 'html';
	const KEY_EXTRA_DATA = 'extraData';

	protected static $_queuedCacheKeys = array();
	protected static $_queriedData = array();

	public static function preSaveWidget(array $widget, $positionCode, array $params, &$html)
	{
		// added support for [bd] Avatar As Attachment
		if (class_exists('bdAvatarAsAttachment_Helper_AvatarUrl'))
		{
			bdAvatarAsAttachment_Helper_AvatarUrl::replaceHashes($html);
		}

		return true;
	}

	public function queueCachedWidgets($cacheId, $permissionCombinationId)
	{
		$cacheKey = $this->_getCachedWidgetsKey($cacheId, $permissionCombinationId);

		self::$_queuedCacheKeys[] = $cacheKey;
	}

	public function getCachedWidgets($cacheId, $permissionCombinationId)
	{
		$cacheKey = $this->_getCachedWidgetsKey($cacheId, $permissionCombinationId);

		if (!isset(self::$_queriedData[$cacheKey]))
		{
			self::$_queuedCacheKeys[] = $cacheKey;
			$this->_getMulti(self::$_queuedCacheKeys);

			// remove invalidated widgets
			foreach (self::$_queuedCacheKeys as $queuedCacheKey)
			{
				if (!isset(self::$_queriedData[$queuedCacheKey]))
				{
					self::$_queriedData[$queuedCacheKey] = array();
					continue;
				}

				foreach (array_keys(self::$_queriedData[$queuedCacheKey]) as $queriedCacheId)
				{
					if ($this->_isCacheInvalidated($queriedCacheId, self::$_queriedData[$queuedCacheKey][$queriedCacheId]))
					{
						unset(self::$_queriedData[$queuedCacheKey][$queriedCacheId]);
					}
				}
			}

			self::$_queuedCacheKeys = array();
		}

		return self::$_queriedData[$cacheKey];
	}

	public function setCachedWidgets(array $cachedWidgets, $cacheId, $permissionCombinationId)
	{
		$cacheKey = $this->_getCachedWidgetsKey($cacheId, $permissionCombinationId);

		$this->_set($cacheKey, $cachedWidgets);

		self::$_queriedData[$cacheKey] = $cachedWidgets;
	}

	protected function _getCachedWidgetsKey($cacheId, $permissionCombinationId)
	{
		$exploded = explode('_', $cacheId);
		array_unshift($exploded, $permissionCombinationId);
		self::_modifyCacheKey($exploded);

		array_pop($exploded);

		return implode('_', $exploded);
	}

	public function invalidateCache($widgetId)
	{
		$invalidatedCache = $this->_getInvalidatedCacheInfo();
		$invalidatedCache[$widgetId] = XenForo_Application::$time;

		$this->_setInvalidatedCacheInfo($invalidatedCache);
	}

	protected function _getMulti($cacheKeys)
	{
		$dbKeys = array();
		foreach ($cacheKeys as $cacheKey)
		{
			$dbKeys[$cacheKey] = $this->_getDbKey($cacheKey);
		}

		$dbData = $this->_getDataRegistry()->getMulti($dbKeys);

		foreach ($dbKeys as $cacheKey => $dbKey)
		{
			if (!isset($dbData[$dbKey]))
			{
				continue;
			}

			self::$_queriedData[$cacheKey] = $dbData[$dbKey];
		}
	}

	protected function _set($cacheKey, array $data)
	{
		$dbKey = $this->_getDbKey($cacheKey);

		return $this->_getDataRegistry()->set($dbKey, $data);
	}

	protected function _getDbKey($id, $maxLength = 25)
	{
		$id = preg_replace('/[^a-zA-Z0-9_]/', '', $id);

		$key = self::CACHED_WIDGETS_BY_PCID_PREFIX . $id;

		if ($maxLength > 0 AND strlen($key) > $maxLength)
		{
			$key = self::CACHED_WIDGETS_BY_PCID_PREFIX . substr(md5($key), 0, $maxLength - strlen(self::CACHED_WIDGETS_BY_PCID_PREFIX));
		}

		return $key;
	}

	protected function _getInvalidatedCacheInfo()
	{
		return XenForo_Application::getSimpleCacheData(self::INVALIDED_CACHE_ITEM_NAME);
	}

	protected function _setInvalidatedCacheInfo(array $invalidatedCache)
	{
		XenForo_Application::setSimpleCacheData(self::INVALIDED_CACHE_ITEM_NAME, $invalidatedCache);
	}

	protected function _isCacheInvalidated($cacheId, array $cacheData)
	{
		$invalidatedCache = $this->_getInvalidatedCacheInfo();

		// there is a global cutoff for cached widgets
		// any widget older than this global cutoff will be considered invalidated
		// we are doing this to make sure no out of date cache exists
		// since 1.3
		$globalCacheCutoff = XenForo_Application::$time - WidgetFramework_Option::get('cacheCutoffDays') * 86400;

		$parts = explode('_', $cacheId);
		$widgetId = array_pop($parts);

		if (isset($invalidatedCache[$widgetId]))
		{
			if ($invalidatedCache[$widgetId] > $cacheData[self::KEY_TIME])
			{
				// the cache is invalidated sometime after it's built
				// it's no longer valid now
				return true;
			}
			elseif ($globalCacheCutoff > $cacheData[self::KEY_TIME])
			{
				// look like a staled cache
				return true;
			}
		}

		return false;
	}

	protected function _getDataRegistry()
	{
		return $this->getModelFromCache('XenForo_Model_DataRegistry');
	}

	public function getLiveCache($cacheId, $permissionCombinationId)
	{
		$cache = $this->_getCache(true);

		if (empty($cache))
		{
			$cachedWidget = $this->getCachedWidgets($cacheId, $permissionCombinationId);
			if (isset($cachedWidget[$cacheId]))
			{
				return $cachedWidget[$cacheId];
			}
			else
			{
				return false;
			}
		}

		$cacheKey = $this->_getLiveCacheKey($cacheId, $permissionCombinationId);

		// sondh@2012-08-14
		// randomly return false to keep the cache fresh
		// so we can avoid db peak when the cache is invalid
		// and all the requests start quering db for data (very bad)
		if (rand(1, 15000) == 296)
		{
			return false;
		}

		$cacheData = $cache->load($cacheKey);
		if ($cacheData !== false)
		{
			$cacheData = unserialize($cacheData);

			if ($this->_isCacheInvalidated($cacheId, $cacheData))
			{
				// invalidated...
				$cacheData = false;
			}
		}

		return $cacheData;
	}

	public function setLiveCache($data, $cacheId, $permissionCombinationId)
	{
		$cache = $this->_getCache(true);

		if (empty($cache))
		{
			// fallback to normal cache
			$cachedWidgets = $this->getCachedWidgets($cacheId, $permissionCombinationId);
			$cachedWidgets[$cacheId] = $data;
			$this->setCachedWidgets($cachedWidgets, $cacheId, $permissionCombinationId);
			return;
		}

		$cache->save(serialize($data), $this->_getLiveCacheKey($cacheId, $permissionCombinationId));
	}

	protected function _getLiveCacheKey($cacheId, $permissionCombinationId)
	{
		$exploded = explode('_', $cacheId);
		$exploded[] = $permissionCombinationId;
		self::_modifyCacheKey($exploded);

		return $this->_getDbKey(implode('_', $exploded), 0);
	}

	protected static function _modifyCacheKey(array &$keys)
	{
		static $modifiers = false;

		if ($modifiers === false)
		{
			$modifiersArray = array();
			$visitor = XenForo_Visitor::getInstance();
			$options = XenForo_Application::getOptions();

			if (!empty($visitor['language_id']) AND $visitor['language_id'] != $options->get('defaultLanguageId'))
			{
				$modifiersArray[] = sprintf('l%d', $visitor['language_id']);
			}

			if ($visitor['style_id'] > 0)
			{
				$modifiersArray[] = sprintf('s%d', $visitor['style_id']);
			}

			if (!empty($visitor['timezone']) AND $visitor['timezone'] != $options->get('guestTimeZone'))
			{
				$modifiersArray[] = sprintf('tz%s', $visitor['timezone']);
			}

			$modifiers = implode('', $modifiersArray);
		}

		array_unshift($keys, $modifiers);
	}

}
