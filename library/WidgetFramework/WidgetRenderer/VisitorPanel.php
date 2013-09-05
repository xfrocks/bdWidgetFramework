<?php

class WidgetFramework_WidgetRenderer_VisitorPanel extends WidgetFramework_WidgetRenderer
{
	protected function _getConfiguration()
	{
		return array(
			'name' => 'Visitor Panel',
			'useWrapper' => false,
		);
	}

	protected function _getOptionsTemplate()
	{
		return false;
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return 'wf_widget_visitor_panel';
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		$renderTemplateObject->setParams($params);

		return $renderTemplateObject->render();
	}

}
