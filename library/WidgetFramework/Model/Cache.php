<?php
class WidgetFramework_Model_Cache extends XenForo_Model {
	const CACHED_WIDGETS_ITEM_NAME = 'cached_widgets';
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
			
			// remove invalidated cache
			foreach (array_keys(self::$_queriedData[$permissionCombinationId]) as $widgetId) {
				if (isset($invalidatedCache[$widgetId])) {
					if ($invalidatedCache[$widgetId] > self::$_queriedData[$permissionCombinationId][$widgetId][self::KEY_TIME]) {
						// the cache is invalidated sometime after it's built
						// it's no longer valid now, remove it
						unset(self::$_queriedData[$permissionCombinationId][$widgetId]);
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
		$record = $this->_getDb()->fetchRow("SELECT * FROM `xf_widget_cached` WHERE data_id = ?", array($id));
		return unserialize($record['data']);
	}
	
	protected function _set($id, array $data) {
		$serialized = serialize($data);
		$this->_getDb()->query("REPLACE INTO `xf_widget_cached` VALUES (?, ?)", array($id, $serialized));
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