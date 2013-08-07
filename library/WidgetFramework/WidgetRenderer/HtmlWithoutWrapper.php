<?php

class WidgetFramework_WidgetRenderer_HtmlWithoutWrapper extends WidgetFramework_WidgetRenderer_Html
{
	protected function _getConfiguration()
	{
		$configuration = parent::_getConfiguration();

		$configuration['name'] = '[Advanced] HTML (without wrapper)';
		$configuration['useWrapper'] = false;

		return $configuration;
	}

}
