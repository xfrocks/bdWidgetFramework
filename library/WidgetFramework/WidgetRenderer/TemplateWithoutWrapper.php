<?php

class WidgetFramework_WidgetRenderer_TemplateWithoutWrapper extends WidgetFramework_WidgetRenderer_Template
{
	protected function _getConfiguration()
	{
		$configuration = parent::_getConfiguration();

		$configuration['name'] = '[Advanced] Template (without wrapper)';
		$configuration['useWrapper'] = false;

		return $configuration;
	}

}
