<?php
class WidgetFramework_Model_Cache extends XenForo_Model {
	const CACHED_WIDGETS_ITEM_NAME = 'wf_cached_widgets';
	const CACHED_WIDGETS_BY_PCID_PREFIX = 'wf_cached_widgets_';
	const INVALIDED_CACHE_ITEM_NAME = 'invalidated_cache';
	const KEY_TIME = 'time';
	const KEY_HTML = 'html';
	
	protected static $_queriedData = array();

	public function getCachedWidgets($permissionCombinationId = 1) {
		if (!isset(self::$_queriedData[$permissionCombinationId])) {
			if ($permissionCombinationId == 1) {
				// guests
				$dataRegistry = $this->_getDataRegistry();
				self::$_queriedData[$permissionCombinationId] = $dataRegistry->get(self::CACHED_WIDGETS_ITEM_NAME);
			} else {
				// logged in users
				self::$_queriedData[$permissionCombinationId] = $this->_get($permissionCombinationId);
			}
			
			if (empty(self::$_queriedData[$permissionCombinationId])) self::$_queriedData[$permissionCombinationId] = array();
			$invalidatedCache = $this->_getInvalidatedCacheInfo();
			
			// there is a global cutoff for cached widgets
			// any widget older than this global cutoff will be considered invalidated
			// we are doing this to make sure no out of date cache exists
			// since 1.3
			$globalCacheCutoff = XenForo_Application::$time - WidgetFramework_Option::get('cacheCutoffDays') * 86400;
			
			// remove invalidated widgets
			foreach (array_keys(self::$_queriedData[$permissionCombinationId]) as $cacheId) {
				$parts = explode('|', $cacheId);
				if (count($parts) == 2) {
					// this is a cache id with suffix
					$widgetId = $parts[0];
				} else {
					// this is a normal cache id 
					$widgetId = $cacheId;
				}
				
				if (isset($invalidatedCache[$widgetId])) {
					if ($invalidatedCache[$widgetId] > self::$_queriedData[$permissionCombinationId][$cacheId][self::KEY_TIME]) {
						// the cache is invalidated sometime after it's built
						// it's no longer valid now, remove it
						unset(self::$_queriedData[$permissionCombinationId][$cacheId]);
					} elseif ($globalCacheCutoff > self::$_queriedData[$permissionCombinationId][$cacheId][self::KEY_TIME]) {
						// look like a staled cache, remove it
						unset(self::$_queriedData[$permissionCombinationId][$cacheId]);
					}
				}
			}
		}
		
		return self::$_queriedData[$permissionCombinationId];
	}
	
	public function setCachedWidgets(array $cachedWidgets, $permissionCombinationId = 1) {
		if ($permissionCombinationId == 1) {
			// guests 
			$dataRegistry = $this->_getDataRegistry();
			$dataRegistry->set(self::CACHED_WIDGETS_ITEM_NAME, $cachedWidgets);
		} else {
			// logged in users
			$this->_set($permissionCombinationId, $cachedWidgets);
		}
		
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
	
	protected function _getDataRegistry() {
		return $this->getModelFromCache('XenForo_Model_DataRegistry');
	}
}