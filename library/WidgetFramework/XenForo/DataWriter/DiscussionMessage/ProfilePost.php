<?php

class WidgetFramework_XenForo_DataWriter_DiscussionMessage_ProfilePost extends XFCP_WidgetFramework_XenForo_DataWriter_DiscussionMessage_ProfilePost
{
	protected function _postSaveAfterTransaction()
	{
		parent::_postSaveAfterTransaction();

		// commented out due to problem with high traffic board
		// since 1.3
		// WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_RecentStatus');
	}

	protected function _messagePostDelete()
	{
		parent::_messagePostDelete();

		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_RecentStatus');
	}

}
