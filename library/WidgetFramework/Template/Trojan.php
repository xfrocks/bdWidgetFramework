<?php

class WidgetFramework_Template_Trojan extends XenForo_Template_Public
{

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
		XenForo_Template_Public::$_extraData = XenForo_Application::mapMerge(XenForo_Template_Public::$_extraData, $extraData);
	}

}
