<?php

class WidgetFramework_WidgetRenderer_Birthday extends WidgetFramework_WidgetRenderer
{
	public function extraPrepareTitle(array $widget)
	{
		if (empty($widget['title']))
		{
			return new XenForo_Phrase('birthday');
		}

		return parent::extraPrepareTitle($widget);
	}

	protected function _getConfiguration()
	{
		return array(
			'name' => 'Birthday',
			'options' => array('limit' => XenForo_Input::UINT, ),
			'useCache' => true,
			'cacheSeconds' => 3600, // cache for 1 hour
		);
	}

	protected function _getOptionsTemplate()
	{
		return 'wf_widget_options_birthday';
	}

	protected function _validateOptionValue($optionKey, &$optionValue)
	{
		if ('limit' == $optionKey)
		{
			if (empty($optionValue))
				$optionValue = 0;
		}

		return parent::_validateOptionValue($optionKey, $optionValue);
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return 'wf_widget_birthday';
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		$userModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_User');
		$userProfileModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_UserProfile');

		$todayStart = XenForo_Locale::getDayStartTimestamps();
		$todayStart = $todayStart['today'];
		$day = XenForo_Locale::getFormattedDate($todayStart, 'd');
		$month = XenForo_Locale::getFormattedDate($todayStart, 'm');

		$conditions = array(
			WidgetFramework_XenForo_Model_User::CONDITIONS_DOB => array(
				'd' => $day,
				'm' => $month
			),

			// checks for user state and banned status
			// since 1.1.2
			'user_state' => 'valid',
			'is_banned' => false,
		);
		$fetchOptions = array(
			'limit' => $widget['options']['limit'],
			'order' => 'username',
			'join' => XenForo_Model_User::FETCH_USER_PROFILE + XenForo_Model_User::FETCH_USER_OPTION,
		);
		$users = array_values($userModel->getUsers($conditions, $fetchOptions));

		foreach ($users as &$user)
		{
			// we can call XenForo_Model_User::prepareUserCard instead
			$user['age'] = $userProfileModel->getUserAge($user);
		}

		$renderTemplateObject->setParam('users', $users);

		return $renderTemplateObject->render();
	}

}
