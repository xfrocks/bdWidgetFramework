<?php
class WidgetFramework_Extend_DataWriter_Discussion_Thread extends XFCP_WidgetFramework_Extend_DataWriter_Discussion_Thread {
	protected function _postSaveAfterTransaction() {
		parent::_postSaveAfterTransaction();
		
		// commented out due to problem with high traffic board
		// since 1.3
		// WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Threads');
		// WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Poll');
	}
	
	protected function _discussionPostDelete(array $messages = null) {
		parent::_discussionPostDelete($messages);
		
		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Threads');
		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Poll');
	}
}