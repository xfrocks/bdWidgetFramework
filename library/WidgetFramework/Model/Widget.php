<?php

class WidgetFramework_Model_Widget extends XenForo_Model
{
	const SIMPLE_CACHE_KEY = 'widgets';

	public function getWidgetsContainsWidgetId(array $widgets, $widgetId)
	{
		foreach (array_keys($widgets) as $_widgetId)
		{
			if (isset($widgets[$_widgetId]['widgets']))
			{
				$response = $this->getWidgetsContainsWidgetId($widgets[$_widgetId]['widgets'], $widgetId);

				if (!empty($response))
				{
					return $response;
				}
			}
			elseif ($_widgetId === $widgetId)
			{
				return $widgets;
			}
		}

		return array();
	}

	public function getLastDisplayOrder($positionWidgetGroups, $positionWidget = null)
	{
		$sameDisplayOrderLevels = array();
		if (!empty($positionWidget))
		{
			// put into a group
			$sameDisplayOrderLevels = $this->getWidgetsContainsWidgetId($positionWidgetGroups, $positionWidget['widget_id']);
		}
		else
		{
			// put into a position
			$sameDisplayOrderLevels = $positionWidgetGroups;
		}

		$maxDisplayOrder = false;
		foreach ($sameDisplayOrderLevels as $sameDisplayOrderLevel)
		{
			if ($maxDisplayOrder === false OR $maxDisplayOrder < $sameDisplayOrderLevel['display_order'])
			{
				$maxDisplayOrder = $sameDisplayOrderLevel['display_order'];
			}
		}

		return floor($maxDisplayOrder / 10) * 10 + 10;
	}

	public function getDisplayOrderFromRelative($widgetId, $relativeDisplayOrder, $positionWidgetGroups, $positionWidget = null, array &$widgetsNeedUpdate = array())
	{
		$sameDisplayOrderLevels = array();
		if (!empty($positionWidget))
		{
			// put into a group
			$sameDisplayOrderLevels = $this->getWidgetsContainsWidgetId($positionWidgetGroups, $positionWidget['widget_id']);
		}
		else
		{
			// put into a position
			$sameDisplayOrderLevels = $positionWidgetGroups;
		}

		if (isset($sameDisplayOrderLevels[$widgetId]))
		{
			// ignore current widget before calculating display order
			unset($sameDisplayOrderLevels[$widgetId]);
		}

		// sort asc by display order (ignore negative/positive)
		uasort($sameDisplayOrderLevels, array(
			'WidgetFramework_Helper_Sort',
			'widgetGroupsAsc'
		));
		$isNegative = $relativeDisplayOrder < 0;
		foreach (array_keys($sameDisplayOrderLevels) as $sameDisplayOrderLevelWidgetId)
		{
			if (($sameDisplayOrderLevels[$sameDisplayOrderLevelWidgetId]['display_order'] < 0) == $isNegative)
			{
				// same negative/positive
			}
			else
			{
				unset($sameDisplayOrderLevels[$sameDisplayOrderLevelWidgetId]);
			}
		}

		$iStart = -1;
		foreach ($sameDisplayOrderLevels as $sameDisplayOrderLevelWidgetId => $sameDisplayOrderLevel)
		{
			if ($sameDisplayOrderLevel['display_order'] < 0)
			{
				$iStart--;
			}
		}

		$i = $iStart;
		foreach ($sameDisplayOrderLevels as $sameDisplayOrderLevelWidgetId => $sameDisplayOrderLevel)
		{
			$i++;
			if ($i < $relativeDisplayOrder)
			{
				// update widget/group display order above our widget
				$currentDisplayOrder = ($isNegative ? $i - 1 : $i) * 10;

				if ($sameDisplayOrderLevel['display_order'] != $currentDisplayOrder)
				{
					$this->updateDisplayOrderForWidget($sameDisplayOrderLevelWidgetId, $currentDisplayOrder - $sameDisplayOrderLevel['display_order'], $sameDisplayOrderLevels, $widgetsNeedUpdate);
				}
			}
		}

		// set display order for the widget
		$foundDisplayOrder = $relativeDisplayOrder * 10;

		$i = $iStart;
		foreach ($sameDisplayOrderLevels as $sameDisplayOrderLevelWidgetId => $sameDisplayOrderLevel)
		{
			$i++;
			if ($i >= $relativeDisplayOrder)
			{
				// update widget/group display order below our widget
				$currentDisplayOrder = ($isNegative ? $i : $i + 1) * 10;

				if ($sameDisplayOrderLevel['display_order'] != $currentDisplayOrder)
				{
					$this->updateDisplayOrderForWidget($sameDisplayOrderLevelWidgetId, $currentDisplayOrder - $sameDisplayOrderLevel['display_order'], $sameDisplayOrderLevels, $widgetsNeedUpdate);
				}
			}
		}

		return $foundDisplayOrder;
	}

	public function updatePositionGroupAndDisplayOrderForWidgets($widgetId, $newPosition, $newGroup, $newDisplayOrder, $oldPositionWidgets, array &$widgetsNeedUpdate)
	{
		$oldGroupWidgets = $this->getWidgetsContainsWidgetId($oldPositionWidgets, $widgetId);
		$oldGroup = '';
		if (empty($oldGroupWidgets))
		{
			// group not found
			return false;
		}
		if (isset($oldGroupWidgets[$widgetId]))
		{
			if (!empty($oldGroupWidgets[$widgetId]['options']['tab_group']))
			{
				$oldGroup = $oldGroupWidgets[$widgetId]['options']['tab_group'];
			}

			unset($oldGroupWidgets[$widgetId]);
		}

		$i = -1;
		$currentDisplayOrder = $newDisplayOrder;
		foreach ($oldGroupWidgets as $oldGroupWidgetId => $oldGroupWidget)
		{
			$i++;

			if ($oldGroupWidget['position'] != $newPosition)
			{
				$widgetsNeedUpdate[$oldGroupWidgetId]['position'] = $newPosition;
			}

			if ($oldGroupWidget['display_order'] <= $currentDisplayOrder)
			{
				$currentDisplayOrder = floor($currentDisplayOrder / 10) * 10 + 10;

				// update widget/group display order below our widget
				$this->updateDisplayOrderForWidget($oldGroupWidgetId, $currentDisplayOrder - $oldGroupWidget['display_order'], $oldGroupWidgets, $widgetsNeedUpdate);
			}
			else
			{
				$currentDisplayOrder = $oldGroupWidget['display_order'];
			}

			if ($oldGroupWidget['tab_group'] !== $newGroup)
			{
				if (!empty($oldGroupWidget['widgets']))
				{
					foreach (array_keys($oldGroupWidget['widgets']) as $subWidgetId)
					{
						// update all widgets within the updated group
						$subWidgetNewGroup = preg_replace('#^' . preg_quote($oldGroup) . '#', $newGroup, $oldGroupWidget['widgets'][$subWidgetId]['tab_group']);
						$this->updatePositionGroupAndDisplayOrderForWidgets($subWidgetId, $newPosition, $subWidgetNewGroup, $currentDisplayOrder, $oldGroupWidget['widgets'], $widgetsNeedUpdate);
					}
				}
				else
				{
					$widgetsNeedUpdate[$oldGroupWidgetId]['tab_group'] = $newGroup;
				}
			}

		}

		return true;
	}

	public function updateGroupForWidgets($oldGroup, $newGroup, array $widgets, array &$widgetsNeedUpdate)
	{
		$pattern = '#(^|/)' . preg_quote($oldGroup) . '($|/)#';
		$replacement = '$1' . $newGroup . '$2';

		foreach ($widgets as $widgetElement)
		{
			if (isset($widgetElement['widgets']))
			{
				$this->updateGroupForWidgets($oldGroup, $newGroup, $widgetElement['widgets'], $widgetsNeedUpdate);
			}
			else
			{
				$replaced = preg_replace($pattern, $replacement, $widgetElement['tab_group'], 1, $count);
				if ($count > 0)
				{
					$widgetsNeedUpdate[$widgetElement['widget_id']]['tab_group'] = $replaced;
				}
			}
		}
	}

	public function updateDisplayOrderForWidget($widgetId, $displayOrderOffset, $widgets, array &$widgetsNeedUpdate)
	{
		$widgetsNeedUpdate[$widgetId]['display_order'] = $widgets[$widgetId]['display_order'] + $displayOrderOffset;

		if (!empty($widgets[$widgetId]['widgets']))
		{
			foreach (array_keys($widgets[$widgetId]['widgets']) as $subWidgetId)
			{
				// update all widgets within the updated group
				$this->updateDisplayOrderForWidget($subWidgetId, $displayOrderOffset, $widgets[$widgetId]['widgets'], $widgetsNeedUpdate);
			}
		}
	}

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
			$widgets = $this->fetchAllKeyed('
				SELECT *
				FROM `xf_widget`
				WHERE `widget_page_id` = 0
				ORDER BY display_order ASC, widget_id ASC
			', 'widget_id');
		}

		foreach ($widgets as &$widget)
		{
			$this->_prepareWidgetMandatory($widget);

			if ($prepare)
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

		foreach ($widgets as &$widget)
		{
			$this->_prepareWidgetMandatory($widget);

			if ($prepare)
			{
				$this->prepareWidget($widget);
			}
		}

		return $widgets;
	}

	public function getWidgetById($widgetId)
	{
		$widget = $this->_getDb()->fetchRow("
				SELECT *
				FROM `xf_widget`
				WHERE widget_id = ?
				", array($widgetId));

		if (!empty($widget))
		{
			$this->_prepareWidgetMandatory($widget);
		}

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
			$widget['rendererName'] = new XenForo_Phrase('wf_unknown_renderer', array('class' => $widget['class']));
			$widget['rendererNotFound'] = true;
			$widget['active'] = false;
		}

		return $widget;
	}

	protected function _prepareWidgetMandatory(array &$widget)
	{
		if (!is_array($widget['options']))
		{
			$widget['options'] = @unserialize($widget['options']);
		}
		if (empty($widget['options']))
		{
			$widget['options'] = array();
		}

		if (!is_array($widget['template_for_hooks']))
		{
			$widget['template_for_hooks'] = @unserialize($widget['template_for_hooks']);
		}
		if (empty($widget['template_for_hooks']))
		{
			$widget['template_for_hooks'] = array();
		}
	}

}
