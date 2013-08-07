<?php

class WidgetFramework_ViewAdmin_Widget_Export extends XenForo_ViewAdmin_Base
{
	public function renderXml()
	{
		$system = &$this->_params['system'];
		$widgets = &$this->_params['widgets'];

		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		$rootNode = $document->createElement('widget_framework');
		$rootNode->setAttribute('version', $system['version_string']);
		$document->appendChild($rootNode);

		foreach ($widgets as $widget)
		{
			$widgetNode = $document->createElement('widget');
			$widgetNode->setAttribute('title', $widget['title']);
			$widgetNode->setAttribute('class', $widget['class']);

			$optionsNode = $document->createElement('options');
			$optionsNode->appendChild(XenForo_Helper_DevelopmentXml::createDomCdataSection($document, $widget['options']));
			$widgetNode->appendChild($optionsNode);

			$widgetNode->setAttribute('position', $widget['position']);
			$widgetNode->setAttribute('display_order', $widget['display_order']);
			$widgetNode->setAttribute('active', $widget['active']);

			$rootNode->appendChild($widgetNode);
		}

		$this->setDownloadFileName('widget_framework-widgets-' . XenForo_Template_Helper_Core::date(XenForo_Application::$time, 'YmdHi') . '.xml');
		return $document->saveXml();
	}

}
