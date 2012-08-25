<?php
class WidgetFramework_WidgetRenderer_Birthday extends WidgetFramework_WidgetRenderer {
	protected function _getConfiguration() {
		return array(
			'name' => 'Birthday',
			'options' => array(
				'limit' => XenForo_Input::UINT,
			),
			'useCache' => true,
			'cacheSeconds' => 8640, // cache for 6 hours
		);
	}
	
	protected function _getOptionsTemplate() {
		return 'wf_widget_options_birthday';
	}
	
	protected function _validateOptionValue($optionKey, &$optionValue) {
		if ('limit' == $optionKey) {
			if (empty($optionValue)) $optionValue = 0;
		}
		
		return true;
	}
	
	protected function _getRenderTemplate(array $widget, $positionCode, array $params) {
		return 'wf_widget_birthday';
	}
	
	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject) {
		$userModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_User');
		$userProfileModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_UserProfile');
		
		$todayStart = XenForo_Locale::getDayStartTimestamps();
		$todayStart = $todayStart['today'];
        $day = XenForo_Locale::getFormattedDate($todayStart, 'd');
        $month = XenForo_Locale::getFormattedDate($todayStart, 'm');
        
        $day = 29;
        $month = 1;
		
		$conditions = array(
			WidgetFramework_Extend_Model_User::CONDITIONS_DOB => array('d' => $day, 'm' => $month),
		);
		$fetchOptions = array(
			'limit' => $widget['options']['limit'],
			'order' => 'username',
			'join' => XenForo_Model_User::FETCH_USER_PROFILE + XenForo_Model_User::FETCH_USER_OPTION,
		);
		$users = array_values($userModel->getUsers($conditions, $fetchOptions));
		
		foreach ($users as &$user) {
			// we can call XenForo_Model_User::prepareUserCard instead
			$user['age'] = $userProfileModel->getUserAge($user);
		}

		$renderTemplateObject->setParam('users', $users);
		
		return $renderTemplateObject->render();
	}
}