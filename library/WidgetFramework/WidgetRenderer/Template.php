<?php

class WidgetFramework_WidgetRenderer_Template extends WidgetFramework_WidgetRenderer
{
	protected function _getConfiguration()
	{
		return array(
			'name' => '[Advanced] Template',
			'options' => array('template' => XenForo_Input::STRING, ),
		);
	}

	protected function _getOptionsTemplate()
	{
		return 'wf_widget_options_template';
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return $widget['options']['template'];
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		return $renderTemplateObject->render();
	}

}
