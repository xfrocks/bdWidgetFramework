<?php
class WidgetFramework_Model_Cache extends XenForo_Model {
	const CACHED_WIDGETS_BY_PCID_PREFIX = 'wf_cache_';
	const INVALIDED_CACHE_ITEM_NAME = 'invalidated_cache';
	const KEY_TIME = 'time';
	const KEY_HTML = 'html';
	
	protected static $_queriedData = array();

	public function getCachedWidgets($permissionCombinationId) {
		if (!isset(self::$_queriedData[$permissionCombinationId])) {
			self::$_queriedData[$permissionCombinationId] = $this->_get($permissionCombinationId);
			
			if (empty(self::$_queriedData[$permissionCombinationId])) self::$_queriedData[$permissionCombinationId] = array();

			// remove invalidated widgets
			foreach (array_keys(self::$_queriedData[$permissionCombinationId]) as $cacheId) {
				if ($this->_isCacheInvalidated($cacheId, self::$_queriedData[$permissionCombinationId][$cacheId])) {
					unset(self::$_queriedData[$permissionCombinationId][$cacheId]);
				}
			}
		}
		
		return self::$_queriedData[$permissionCombinationId];
	}
	
	public function setCachedWidgets(array $cachedWidgets, $permissionCombinationId) {
		$this->_set($permissionCombinationId, $cachedWidgets);
		
		self::$_queriedData[$permissionCombinationId] = $cachedWidgets;
	}
	
	public function invalidateCache($widgetId) {
		$invalidatedCache = $this->_getInvalidatedCacheInfo();
		$invalidatedCache[$widgetId] = XenForo_Application::$time;
		
		$this->_setInvalidatedCacheInfo($invalidatedCache);
	}
	
	protected function _get($id) {
		// switched to use data registry
		// to make use of advanced caching mechanism
		// since 1.3
		return $this->_getDataRegistry()->get($this->_getDataRegistryKey($id));
	}
	
	protected function _set($id, array $data) {
		// switched to use data registry
		// to make use of advanced caching mechanism
		// since 1.3
		return $this->_getDataRegistry()->set($this->_getDataRegistryKey($id), $data);
	}
	
	protected function _getDataRegistryKey($id) {
		return self::CACHED_WIDGETS_BY_PCID_PREFIX . $id;
	}
	
	protected function _getInvalidatedCacheInfo() {
		return XenForo_Application::getSimpleCacheData(self::INVALIDED_CACHE_ITEM_NAME);
	}
	
	protected function _setInvalidatedCacheInfo(array $invalidatedCache) {
		XenForo_Application::setSimpleCacheData(self::INVALIDED_CACHE_ITEM_NAME, $invalidatedCache);
	}
	
	protected function _isCacheInvalidated($cacheId, array $cacheData) {
		$invalidatedCache = $this->_getInvalidatedCacheInfo();
		
		// there is a global cutoff for cached widgets
		// any widget older than this global cutoff will be considered invalidated
		// we are doing this to make sure no out of date cache exists
		// since 1.3
		$globalCacheCutoff = XenForo_Application::$time - WidgetFramework_Option::get('cacheCutoffDays') * 86400;
		
		$parts = explode('__', $cacheId);
		if (count($parts) == 2) {
			// this is a cache id with suffix
			$widgetId = $parts[0];
		} else {
			// this is a normal cache id 
			$widgetId = $cacheId;
		}
		
		if (isset($invalidatedCache[$widgetId])) {
			if ($invalidatedCache[$widgetId] > $cacheData[self::KEY_TIME]) {
				// the cache is invalidated sometime after it's built
				// it's no longer valid now
				return true;
			} elseif ($globalCacheCutoff > $cacheData[self::KEY_TIME]) {
				// look like a staled cache
				return true;
			}
		}
		
		return false;
	}
	
	protected function _getDataRegistry() {
		return $this->getModelFromCache('XenForo_Model_DataRegistry');
	}
	
	public function getLiveCache($cacheId, $permissionCombinationId) {
		$cache = $this->_getCache(true);
		$cacheKey = $this->_getLiveCacheKey($cacheId, $permissionCombinationId);

		$cacheData = ($cache ? $cache->load($cacheKey) : false);
		if ($cacheData !== false)
		{
			$cacheData = unserialize($cacheData);
			
			if ($this->_isCacheInvalidated($cacheId, $cacheData)) {
				// invalidated...
				$cacheData = false;
			}
		}

		return $cacheData;
	}
	
	public function setLiveCache($data, $cacheId, $permissionCombinationId) {
		$cache = $this->_getCache(true);
		
		if ($cache) {
			$cache->save(serialize($data), $this->_getLiveCacheKey($cacheId, $permissionCombinationId));
		}
	}
	
	protected function _getLiveCacheKey($cacheId, $permissionCombinationId) {
		return $this->_getDataRegistryKey($cacheId) . '_' . $permissionCombinationId;
	}
}