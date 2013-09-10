<?php

class WidgetFramework_WidgetRenderer_FacebookFacepile extends WidgetFramework_WidgetRenderer
{
	protected function _getConfiguration()
	{
		return array(
			'name' => 'Facebook: Facepile',
			'useWrapper' => false,
		);
	}
	
	protected function _getOptionsTemplate()
	{
		return false;
	}
	
	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return 'wf_widget_facebook_facepile';
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		$renderTemplateObject->setParams($params);

		return $renderTemplateObject->render();
	}

}
