<?php
class WidgetFramework_Extend_DataWriter_Discussion_Thread extends XFCP_WidgetFramework_Extend_DataWriter_Discussion_Thread {
	protected function _postSaveAfterTransaction() {
		parent::_postSaveAfterTransaction();
		
		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Threads');
		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Poll');
	}
	
	protected function _discussionPostDelete(array $messages) {
		parent::_discussionPostDelete($messages);
		
		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Threads');
		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Poll');
	}
}