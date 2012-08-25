<?php
class WidgetFramework_ViewAdmin_Widget_Edit extends XenForo_ViewAdmin_Base {
	public function renderHtml() {
		$widget =& $this->_params['widget'];
		if (!empty($widget['class'])) {
			$renderer = WidgetFramework_Core::getRenderer($widget['class']);;
			$renderer->renderOptions($this->_renderer, $this->_params);
		}
	}
}