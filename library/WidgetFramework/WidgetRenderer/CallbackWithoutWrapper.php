<?php

class WidgetFramework_WidgetRenderer_CallbackWithoutWrapper extends WidgetFramework_WidgetRenderer_Callback
{
	protected function _getConfiguration()
	{
		$configuration = parent::_getConfiguration();

		$configuration['name'] = '[Advanced] PHP Callback (without wrapper)';
		$configuration['useWrapper'] = false;

		return $configuration;
	}

}
