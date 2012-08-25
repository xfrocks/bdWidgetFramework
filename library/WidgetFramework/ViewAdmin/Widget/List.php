<?php
class WidgetFramework_ViewAdmin_Widget_List extends XenForo_ViewAdmin_Base {
	public function renderHtml() {
		$positions = array();
		
		foreach ($this->_params['widgets'] as &$widget) {
			$renderer = WidgetFramework_Core::getRenderer($widget['class'], false);
			if (!empty($renderer)) {
				$widget['rendererName'] = $renderer->getName();
				
				$widgetPositions = explode(',', $widget['position']);
				
				foreach ($widgetPositions as $position) {
					$position = trim($position);
					if (empty($position)) continue;
					
					if (!isset($positions[$position])) {
						$positions[$position] = array(
							'position' => $position,
							'widgets' => array(),
						);
					}
					
					if (!empty($widget['options']['tab_group'])) {
						if (!isset($positions[$position]['widgets'][$widget['options']['tab_group']])) {
							$positions[$position]['widgets'][$widget['options']['tab_group']] = array(
								'tabGroup' => $widget['options']['tab_group'],
								'widgets' => array(),
							);
						}
						$positions[$position]['widgets'][$widget['options']['tab_group']]['widgets'][] =& $widget;					
					} else {
						$positions[$position]['widgets'][] =& $widget;
					}
				}
			}
		}
		
		usort($positions, create_function('$a, $b', 'return $a["position"] > $b["position"];'));
		
		$this->_params['positions'] = $positions;
	}
}