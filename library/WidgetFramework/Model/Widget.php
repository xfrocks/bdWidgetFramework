<?php

class WidgetFramework_Model_Widget extends XenForo_Model
{
	const SIMPLE_CACHE_KEY = 'widgets';

	public function importFromFile($fileName, $deleteAll = false)
	{
		if (!file_exists($fileName) || !is_readable($fileName))
		{
			throw new XenForo_Exception(new XenForo_Phrase('please_enter_valid_file_name_requested_file_not_read'), true);
		}

		try
		{
			$document = new SimpleXMLElement($fileName, 0, true);
		}
		catch (Exception $e)
		{
			throw new XenForo_Exception(new XenForo_Phrase('provided_file_was_not_valid_xml_file'), true);
		}

		if ($document->getName() != 'widget_framework')
		{
			throw new XenForo_Exception(new XenForo_Phrase('wf_provided_file_is_not_an_widgets_xml_file'), true);
		}

		$widgets = XenForo_Helper_DevelopmentXml::fixPhpBug50670($document->widget);

		XenForo_Db::beginTransaction();

		if ($deleteAll)
		{
			// get global widgets from database and delete them all!
			// NOTE: ignore widget page widgets
			$existingWidgets = $this->getGlobalWidgets(false, false);
			foreach ($existingWidgets as $existingWidget)
			{
				$dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
				$dw->setExtraData(WidgetFramework_DataWriter_Widget::EXTRA_DATA_SKIP_REBUILD, true);

				$dw->setExistingData($existingWidget);

				$dw->delete();
			}
		}

		foreach ($widgets as $widget)
		{
			$dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
			$dw->setExtraData(WidgetFramework_DataWriter_Widget::EXTRA_DATA_SKIP_REBUILD, true);

			$dw->set('title', $widget['title']);
			$dw->set('class', $widget['class']);
			$dw->set('position', $widget['position']);
			$dw->set('display_order', $widget['display_order']);
			$dw->set('active', intval($widget['active']));

			$dw->set('options', unserialize(XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($widget->options)));

			$dw->save();
		}

		$this->buildCache();

		XenForo_Db::commit();
	}

	public function getGlobalWidgets($useCached = true, $prepare = true)
	{
		$widgets = false;

		/* try to use cached data */
		if ($useCached)
		{
			$widgets = XenForo_Application::getSimpleCacheData(self::SIMPLE_CACHE_KEY);
		}

		/* fallback to database */
		if ($widgets === false)
		{
			$widgets = $this->fetchAllKeyed("
					SELECT *
					FROM `xf_widget`
					WHERE `widget_page_id` = 0
					ORDER BY display_order ASC
					", 'widget_id');
		}

		/* prepare information for widgets */
		if ($prepare)
		{
			foreach ($widgets as &$widget)
			{
				$this->prepareWidget($widget);
			}
		}

		return $widgets;
	}

	public function getWidgetPageWidgets($widgetPageId, $prepare = true)
	{
		$widgets = false;
		$widgets = $this->fetchAllKeyed("
				SELECT *
				FROM `xf_widget`
				WHERE `widget_page_id` = ?
				ORDER BY display_order ASC
				", 'widget_id', array($widgetPageId));

		if ($prepare)
		{
			foreach ($widgets as &$widget)
			{
				$this->prepareWidget($widget);
			}
		}

		return $widgets;
	}

	public function reverseNegativeDisplayOrderWidgets(array &$widgets)
	{
		$positiveWidgets = array();

		foreach (array_keys($widgets) as $widgetId)
		{
			if ($widgets[$widgetId]['display_order'] >= 0)
			{
				$positiveWidgets[$widgetId] = $widgets[$widgetId];
				unset($widgets[$widgetId]);
			}
		}

		// at this point, widgets only contains negative display order widgets
		// we will just reverse them all
		$widgets = array_reverse($widgets, true /* preserves keys */);

		// new adding back the positive ones
		foreach ($positiveWidgets as $widgetId => $widget)
		{
			$widgets[$widgetId] = $widget;
		}

		// done! I feel so smart. LOL
	}

	public function getWidgetById($widgetId)
	{
		$widget = $this->_getDb()->fetchRow("
				SELECT *
				FROM `xf_widget`
				WHERE widget_id = ?
				", array($widgetId));

		return $widget;
	}

	public function buildCache()
	{
		$widgets = $this->getGlobalWidgets(false, false);
		XenForo_Application::setSimpleCacheData(self::SIMPLE_CACHE_KEY, $widgets);
	}

	public function prepareWidget(array &$widget)
	{
		if (empty($widget))
		{
			return $widget;
		}

		$widget['options'] = @unserialize($widget['options']);
		if (empty($widget['options']))
		{
			$widget['options'] = array();
		}

		$widget['template_for_hooks'] = @unserialize($widget['template_for_hooks']);
		if (empty($widget['template_for_hooks']))
		{
			$widget['template_for_hooks'] = array();
		}

		$renderer = WidgetFramework_Core::getRenderer($widget['class'], false);

		if ($renderer)
		{
			$widget['renderer'] = &$renderer;
			$widget['rendererName'] = $renderer->getName();
			$configuration = $renderer->getConfiguration();
			$options = &$configuration['options'];
			foreach ($options as $optionKey => $optionType)
			{
				if (!isset($widget['options'][$optionKey]))
				{
					$widget['options'][$optionKey] = '';
				}
			}
		}
		else
		{
			$widget['rendererName'] = new XenForo_Phrase('xf_unknown_renderer', array('class' => $widget['class']));
			$widget['rendererNotFound'] = true;
			$widget['active'] = false;
		}

		return $widget;
	}

}
