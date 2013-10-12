<?php

class WidgetFramework_Option
{
	protected static $_revealEnabled = null;

	public static function get($key)
	{
		$options = XenForo_Application::get('options');

		switch ($key)
		{
			case 'cacheCutoffDays':
				return 7;
			case 'indexTabId':
				return 'WidgetFramework_home';
			case 'revealEnabled':
				if (self::$_revealEnabled === null)
				{
					$session = XenForo_Application::get('session');
					if (empty($session))
					{
						// no session yet...
						return false;
					}
					self::$_revealEnabled = ($session->get('_WidgetFramework_reveal') == true);
				}

				// use the cached value
				return self::$_revealEnabled;
		}

		return $options->get('WidgetFramework_' . $key);
	}

	public static function setIndexNodeId($nodeId)
	{
		$optionDw = XenForo_DataWriter::create('XenForo_DataWriter_Option');
		$optionDw->setExistingData('WidgetFramework_indexNodeId');
		$optionDw->set('option_value', $nodeId);
		$optionDw->save();
	}

	public static function renderWidgetPages(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$widgetPages = XenForo_Model::create('WidgetFramework_Model_WidgetPage')->getList();
		$choices = array(0 => '');
		foreach ($widgetPages as $widgetPageId => $widgetPageTitle)
		{
			$choices[$widgetPageId] = $widgetPageTitle;
		}

		$editLink = $view->createTemplateObject('option_list_option_editlink', array(
			'preparedOption' => $preparedOption,
			'canEditOptionDefinition' => $canEdit
		));

		return $view->createTemplateObject('option_list_option_select', array(
			'fieldPrefix' => $fieldPrefix,
			'listedFieldName' => $fieldPrefix . '_listed[]',
			'preparedOption' => $preparedOption,
			'formatParams' => $choices,
			'editLink' => $editLink,
		));
	}

}
