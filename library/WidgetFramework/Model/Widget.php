<?php
class WidgetFramework_Model_Widget extends XenForo_Model {
	const SIMPLE_CACHE_KEY = 'widgets';
	
	public function getAllWidgets($useCached = true, $prepare = true) {
		$widgets = false;
		
		/* try to use cached data */
		if ($useCached) {
			$widgets = XenForo_Application::getSimpleCacheData(self::SIMPLE_CACHE_KEY);
		}
		
		/* fallback to database */
		if ($widgets === false) {
			$widgets = $this->fetchAllKeyed("
				SELECT *
				FROM `xf_widget`
				ORDER BY display_order ASC
			", 'widget_id');
		}
		
		/* prepare information for widgets */
		if ($prepare) {
			foreach ($widgets as &$widget) {
				$this->_prepare($widget);
			}
		}
		
		return $widgets;
	}
	
	public function getWidgetById($widgetId) {
		$widget = $this->_getDb()->fetchRow("
			SELECT *
			FROM `xf_widget`
			WHERE widget_id = ?
		", array($widgetId));
		
		$this->_prepare($widget);
		
		return $widget;
	}
	
	public function buildCache() {
		$widgets = $this->getAllWidgets(false, false);
		XenForo_Application::setSimpleCacheData(self::SIMPLE_CACHE_KEY, $widgets);
	}
	
	protected function _prepare(array &$widget) {
		$widget['options'] = unserialize($widget['options']);
		
		$renderer = WidgetFramework_Core::getRenderer($widget['class'], false);
		if ($renderer) {
			$widget['rendererName'] = $renderer->getName();
			$configuration = $renderer->getConfiguration();
			$options =& $configuration['options'];
			foreach ($options as $optionKey => $optionType) {
				if (!isset($widget['options'][$optionKey])) {
					$widget['options'][$optionKey] = '';
				}
			}
		} else {
			$widget['rendererName'] = 'NOT FOUND';
		}
		
		if (empty($widget['title'])) {
			$widget['title'] = $widget['rendererName'];
		}
	}
}