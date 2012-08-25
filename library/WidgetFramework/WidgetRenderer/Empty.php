<?php
class WidgetFramework_WidgetRenderer_Empty extends WidgetFramework_WidgetRenderer {
	const NO_VISITOR_PANEL_MARKUP = '<!-- no visitor panel please -->';
	const NO_VISITOR_PANEL_FLAG = 'WidgetFramework_WidgetRenderer_Empty.noVisitorPanel';
	
	protected function _getConfiguration() {
		return array(
			'name' => ' Clear Sidebar',
			'options' => array(
				'noVisitorPanel' => XenForo_Input::UINT,
			),
			'useWrapper' => false,
		);
	}
	
	protected function _getOptionsTemplate() {
		return 'wf_widget_options_empty';
	}
	
	protected function _getRenderTemplate(array $widget, $positionCode, array $params) {
		return false;
	}
	
	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject) {
		return false;
	}
	
	public function render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $template, &$output) {
		$output = '';
		
		if (!empty($widget['options']['noVisitorPanel'])) {
			define(self::NO_VISITOR_PANEL_FLAG, true);
			$output .= self::NO_VISITOR_PANEL_MARKUP;
		}
		
	}
}