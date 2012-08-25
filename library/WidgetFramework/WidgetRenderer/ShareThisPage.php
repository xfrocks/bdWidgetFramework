<?php
class WidgetFramework_WidgetRenderer_ShareThisPage extends WidgetFramework_WidgetRenderer {
	protected function _getConfiguration() {
		return array(
			'name' => 'Share This Page',
			'useWrapper' => false
		);
	}
	
	protected function _getOptionsTemplate() {
		return false;
	}
	
	protected function _getRenderTemplate(array $widget, $positionCode, array $params) {
		return 'sidebar_share_page';
	}
	
	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject) {
		$renderTemplateObject->setParam('xenOptions', $params['xenOptions']);
		if (isset($params['url'])) {
			$renderTemplateObject->setParam('url', $params['url']);
		}
		
		return $renderTemplateObject->render();		
	}
}