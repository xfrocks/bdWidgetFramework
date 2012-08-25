<?php
class WidgetFramework_WidgetRenderer_OnlineStaff extends WidgetFramework_WidgetRenderer {
	protected function _getConfiguration() {
		return array(
			'name' => 'Staff Online Now',
		);
	}
	
	protected function _getOptionsTemplate() {
		return false;
	}
	
	protected function _getRenderTemplate(array $widget, $templateName, array $params) {
		return 'wf_widget_online_staff';
	}
	
	protected function _render(array $widget, $templateName, array $params, XenForo_Template_Abstract $renderTemplateObject) {
		if ('forum_list' == $templateName) {
			$renderTemplateObject->setParam('onlineUsers', $params['onlineUsers']);
		} else {
			if (empty($GLOBALS['WidgetFramework_onlineUsers'])) {
				$visitor = XenForo_Visitor::getInstance();
				$sessionModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Session');
	
				$GLOBALS['WidgetFramework_onlineUsers'] = $sessionModel->getSessionActivityQuickList(
					$visitor->toArray(),
					array('cutOff' => array('>', $sessionModel->getOnlineStatusTimeout())),
					($visitor['user_id'] ? $visitor->toArray() : null)
				);
			}
			
			$renderTemplateObject->setParam('onlineUsers', $GLOBALS['WidgetFramework_onlineUsers']);
		}

		return $renderTemplateObject->render();		
	}
}