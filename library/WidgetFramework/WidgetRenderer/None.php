<?php

class WidgetFramework_WidgetRenderer_None extends WidgetFramework_WidgetRenderer
{
	public function renderOptions(XenForo_ViewRenderer_Abstract $viewRenderer, array &$templateParams)
	{
		$response = parent::renderOptions($viewRenderer, $templateParams);

		// hide the expression field
		$templateParams['options_loaded'] = '';

		return $response;
	}

	protected function _getConfiguration()
	{
		return array(
			// hide the tab field
			'useWrapper' => false, );
	}

	protected function _getOptionsTemplate()
	{
		return false;
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		throw new XenForo_Exception('not implemented');
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		throw new XenForo_Exception('not implemented');
	}

}
