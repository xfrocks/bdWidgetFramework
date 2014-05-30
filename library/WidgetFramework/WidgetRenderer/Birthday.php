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
			'options' => array(
				'limit' => XenForo_Input::UINT,
				'avatar_only' => XenForo_Input::UINT,
				'whitelist_user_groups' => XenForo_Input::ARRAY_SIMPLE,
				'blacklist_user_groups' => XenForo_Input::ARRAY_SIMPLE,
			),
			'useCache' => true,
			'cacheSeconds' => 3600, // cache for 1 hour
		);
	}

	protected function _getOptionsTemplate()
	{
		return 'wf_widget_options_birthday';
	}

	protected function _renderOptions(XenForo_Template_Abstract $template)
	{
		$params = $template->getParams();
		$userGroups = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_UserGroup')->getAllUserGroupTitles();

		$whitelistUserGroups = array();
		$blacklistUserGroups = array();

		$optionWhitelist = array();
		if (!empty($params['options']['whitelist_user_groups']))
		{
			$optionWhitelist = $params['options']['whitelist_user_groups'];
		}

		$optionBlacklist = array();
		if (!empty($params['options']['blacklist_user_groups']))
		{
			$optionBlacklist = $params['options']['blacklist_user_groups'];
		}

		foreach ($userGroups as $userGroupId => $title)
		{
			$whitelistSelected = in_array($userGroupId, $optionWhitelist);
			$whitelistUserGroups[] = array(
				'value' => $userGroupId,
				'label' => $title,
				'selected' => $whitelistSelected,
			);

			$blacklistSelected = in_array($userGroupId, $optionBlacklist);
			$blacklistUserGroups[] = array(
				'value' => $userGroupId,
				'label' => $title,
				'selected' => $blacklistSelected,
			);
		}

		$template->setParam('whitelistUserGroups', $whitelistUserGroups);
		$template->setParam('blacklistUserGroups', $blacklistUserGroups);

		return parent::_renderOptions($template);
	}

	protected function _validateOptionValue($optionKey, &$optionValue)
	{
		switch ($optionKey)
		{
			case 'limit':
				if (empty($optionValue))
				{
					$optionValue = 0;
				}
				break;
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
			'order' => 'username',
			'join' => XenForo_Model_User::FETCH_USER_PROFILE + XenForo_Model_User::FETCH_USER_OPTION,
		);

		if (!empty($widget['options']['limit']))
		{
			$fetchOptions['limit'] = $widget['options']['limit'];
		}

		if (!empty($widget['options']['avatar_only']))
		{
			$conditions[WidgetFramework_XenForo_Model_User::CONDITIONS_HAS_AVATAR] = true;
		}

		$users = $userModel->getUsers($conditions, $fetchOptions);

		foreach (array_keys($users) as $userId)
		{
			$user = &$users[$userId];

			if (!empty($widget['options']['whitelist_user_groups']))
			{
				// check for whitelist user groups
				if (!$userModel->isMemberOfUserGroup($user, $widget['options']['whitelist_user_groups']))
				{
					unset($users[$userId]);
					continue;
				}
			}

			if (!empty($widget['options']['blacklist_user_groups']))
			{
				// check for blacklist user groups
				if ($userModel->isMemberOfUserGroup($user, $widget['options']['blacklist_user_groups']))
				{
					unset($users[$userId]);
					continue;
				}
			}

			// we can call XenForo_Model_User::prepareUserCard instead
			$user['age'] = $userProfileModel->getUserAge($user);
		}

		$renderTemplateObject->setParam('users', array_values($users));

		return $renderTemplateObject->render();
	}

	protected function _getCacheId(array $widget, $positionCode, array $params, array $suffix = array())
	{
		$suffix[] = XenForo_Locale::getTimeZoneOffset();

		return parent::_getCacheId($widget, $positionCode, $params, $suffix);
	}

}
