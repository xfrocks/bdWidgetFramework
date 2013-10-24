<?php

class WidgetFramework_Template_Extended extends XenForo_Template_Public
{
	protected static $_WidgetFramework_pageContainerTemplate = null;

	protected static $_WidgetFramework_lateExtraData = array();

	public static function WidgetFramework_setPageContainer(XenForo_Template_Abstract $template)
	{
		if (!($template instanceof XenForo_Template_Public))
		{
			return;
		}

		self::$_WidgetFramework_pageContainerTemplate = $template;
	}

	public static function WidgetFramework_getRequiredExternals()
	{
		return XenForo_Template_Public::$_required;
	}

	public static function WidgetFramework_setRequiredExternals(array $required)
	{
		XenForo_Template_Public::$_required = $required;
	}

	public static function WidgetFramework_mergeExtraContainerData(array $extraData)
	{
		if (empty($extraData))
		{
			return;
		}

		if (self::$_WidgetFramework_pageContainerTemplate === null OR self::$_WidgetFramework_pageContainerTemplate->getParam('contents') === null)
		{
			XenForo_Template_Public::$_extraData = XenForo_Application::mapMerge(XenForo_Template_Public::$_extraData, $extraData);
		}
		else
		{
			// these extra data came too late
			// page container has already started rendering...
			self::$_WidgetFramework_lateExtraData = XenForo_Application::mapMerge(self::$_WidgetFramework_lateExtraData, $extraData);
		}
	}

	public static function WidgetFramework_processLateExtraData(&$output, array &$containerData, XenForo_Template_Abstract $template)
	{
		if (!($template instanceof XenForo_Template_Public))
		{
			return;
		}

		foreach (self::$_WidgetFramework_lateExtraData as $key => $value)
		{
			switch ($key)
			{
				case 'head':
					foreach ($value as $headKey => $headValue)
					{
						$output = str_replace('</head>', $headValue . '</head>', $output);
					}
					break;
			}
		}
	}

}
