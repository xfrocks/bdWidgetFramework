<?php
class WidgetFramework_Extend_DataWriter_DiscussionMessage_ProfilePost extends XFCP_WidgetFramework_Extend_DataWriter_DiscussionMessage_ProfilePost {
	protected function _postSaveAfterTransaction() {
		parent::_postSaveAfterTransaction();
		
		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_RecentStatus');
	}
	
	protected function _messagePostDelete() {
		parent::_messagePostDelete();
		
		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_RecentStatus');
	}
}