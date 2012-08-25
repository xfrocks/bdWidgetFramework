<?php
class WidgetFramework_Extend_DataWriter_User extends XFCP_WidgetFramework_Extend_DataWriter_User {
	protected function _postSaveAfterTransaction() {
		parent::_postSaveAfterTransaction();
		
		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Users');
	}
	
	protected function _postDelete() {
		parent::_postDelete();
		
		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Users');
	}
}