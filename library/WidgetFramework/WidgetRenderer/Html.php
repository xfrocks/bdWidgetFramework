<?php
class WidgetFramework_WidgetRenderer_Html extends WidgetFramework_WidgetRenderer {
	protected function _getConfiguration() {
		return array(
			'name' => 'HTML',
			'options' => array(
				'html' => XenForo_Input::STRING,
			),
		);
	}
	
	protected function _getOptionsTemplate() {
		return 'wf_widget_options_html';
	}
	
	protected function _getRenderTemplate(array $widget, $templateName, array $params) {
		return false;
	}
	
	protected function _render(array $widget, $templateName, array $params, XenForo_Template_Abstract $justAnTemplate) {
		return $widget['options']['html'];
	}
}