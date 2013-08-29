<?php

class WidgetFramework_WidgetRenderer_RecentStatus extends WidgetFramework_WidgetRenderer
{
	public function extraPrepareTitle(array $widget)
	{
		if (empty($widget['title']))
		{
			return new XenForo_Phrase('wf_recent_status');
		}

		return parent::extraPrepareTitle($widget);
	}

	protected function _getConfiguration()
	{
		return array(
			'name' => 'User Recent Status',
			'options' => array(
				'limit' => XenForo_Input::UINT,
				'friends_only' => XenForo_Input::BINARY,
				'show_update_form' => XenForo_Input::BINARY,
			),
			'useCache' => true,
			'useUserCache' => true,
			'cacheSeconds' => 3600, // cache for 1 hour
		);
	}

	protected function _getOptionsTemplate()
	{
		return 'wf_widget_options_recent_status';
	}

	protected function _validateOptionValue($optionKey, &$optionValue)
	{
		if ('limit' == $optionKey)
		{
			if (empty($optionValue))
			{
				$optionValue = 5;
			}
		}

		return parent::_validateOptionValue($optionKey, $optionValue);
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return 'wf_widget_recent_status';
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		$core = WidgetFramework_Core::getInstance();
		$userModel = $core->getModelFromCache('XenForo_Model_User');
		$userProfileModel = $core->getModelFromCache('XenForo_Model_UserProfile');

		if (XenForo_Visitor::getUserId() == 0 OR empty($widget['options']['friends_only']))
		{
			// get statuses from all users if friends_only option is not used
			// also do it if current user is guest (guest has no friend list, lol)
			$conditions = array(WidgetFramework_XenForo_Model_User::CONDITIONS_STATUS_DATE => array(
					'>',
					0
				), );
			$fetchOptions = array(
				'join' => XenForo_Model_User::FETCH_USER_PROFILE,

				'order' => WidgetFramework_XenForo_Model_User::ORDER_STATUS_DATE,
				'direction' => 'desc',

				'limit' => $widget['options']['limit'] * 2, // we have to check for permissions
				// later
			);

			$users = $userModel->getUsers($conditions, $fetchOptions);

			// remove users if current user has no permission
			foreach (array_keys($users) as $userId)
			{
				if ($userProfileModel->canViewProfilePosts($users[$userId]) == false)
				{
					unset($users[$userId]);
				}
			}
			if (count($users) > $widget['options']['limit'])
			{
				// remove if there are too many users left
				$users = array_slice($users, 0, $widget['options']['limit'], true /* preserve
				 * keys */);
			}
		}
		else
		{
			$users = $userModel->getFollowedUserProfiles(XenForo_Visitor::getUserId(), $widget['options']['limit'], 'user_profile.status_date DESC');

			// remove users if no status is found
			foreach (array_keys($users) as $userId)
			{
				if (empty($users[$userId]['status_date']))
				{
					unset($users[$userId]);
				}
			}
		}

		$renderTemplateObject->setParam('users', $users);

		if ($widget['options']['show_update_form'])
		{
			$renderTemplateObject->setParam('canUpdateStatus', XenForo_Visitor::getInstance()->canUpdateStatus());
		}

		return $renderTemplateObject->render();
	}

	public function extraPrepare(array $widget, &$html)
	{
		$visitor = XenForo_Visitor::getInstance();
		$html = str_replace('CSRF_TOKEN_PAGE', $visitor->get('csrf_token_page'), $html);
		$html = str_replace('LINK_MEMBER_POST_VISITOR', XenForo_Link::buildPublicLink('members/post', $visitor), $html);

		return parent::extraPrepare($widget, $html);
	}

}
