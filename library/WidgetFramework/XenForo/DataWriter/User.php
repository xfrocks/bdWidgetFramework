<?php

class WidgetFramework_XenForo_DataWriter_User extends XFCP_WidgetFramework_XenForo_DataWriter_User
{
	protected function _postSaveAfterTransaction()
	{
		parent::_postSaveAfterTransaction();

		// TODO: consider commenting this out too?
		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Users');
		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Birthday');
	}

	protected function _postDelete()
	{
		parent::_postDelete();

		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Users');
		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Birthday');
	}

}
