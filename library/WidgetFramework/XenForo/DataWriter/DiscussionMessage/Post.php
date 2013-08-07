<?php

class WidgetFramework_XenForo_DataWriter_DiscussionMessage_Post extends XFCP_WidgetFramework_XenForo_DataWriter_DiscussionMessage_Post
{
	protected function _postSaveAfterTransaction()
	{
		parent::_postSaveAfterTransaction();

		if (!$this->isDiscussionFirstMessage())
		{
			// commented out due to problem with high traffic board
			// since 1.3
			// WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Threads');
			// WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Poll');
		}
	}

	protected function _messagePostDelete()
	{
		parent::_messagePostDelete();

		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Threads');
		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Poll');
	}

}
