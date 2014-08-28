<?php

class WidgetFramework_WidgetRenderer_RecentStatus extends WidgetFramework_WidgetRenderer_ProfilePosts
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
				'show_duplicates' => XenForo_Input::BINARY,
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

	protected function _getProfilePosts(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		$core = WidgetFramework_Core::getInstance();
		$userModel = $core->getModelFromCache('XenForo_Model_User');
		$userProfileModel = $core->getModelFromCache('XenForo_Model_UserProfile');
		$profilePostModel = $core->getModelFromCache('XenForo_Model_ProfilePost');

		if (XenForo_Visitor::getUserId() == 0 OR empty($widget['options']['friends_only']))
		{
			// get statuses from all users if friends_only option is not used
			// also do it if current user is guest (guest has no friend list, lol)
			$conditions = array(WidgetFramework_XenForo_Model_User::CONDITIONS_STATUS_DATE => array(
					'>',
					0
				));
			$fetchOptions = array(
				'join' => XenForo_Model_User::FETCH_USER_PROFILE,
				'order' => WidgetFramework_XenForo_Model_User::ORDER_STATUS_DATE,
				'direction' => 'desc',

				'limit' => $widget['options']['limit'] * 3,
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

		$profilePostIds = array();
		if (!empty($widget['options']['show_duplicates']) AND !empty($users))
		{
			$userIds = array_keys($users);
			$profilePostModel = $core->getModelFromCache('XenForo_Model_ProfilePost');
			$profilePostIds = $profilePostModel->WidgetFramework_getProfilePostIdsOfUserStatuses($userIds, intval($widget['options']['limit']));
		}
		else
		{
			foreach ($users as $user)
			{
				$profilePostIds[] = $user['status_profile_post_id'];
			}
		}

		$profilePosts = $profilePostModel->getProfilePostsByIds($profilePostIds, array('join' => XenForo_Model_ProfilePost::FETCH_USER_POSTER | XenForo_Model_ProfilePost::FETCH_USER_RECEIVER | XenForo_Model_ProfilePost::FETCH_USER_RECEIVER_PRIVACY));
		foreach ($profilePosts AS $id => &$profilePost)
		{
			$receivingUser = $profilePostModel->getProfileUserFromProfilePost($profilePost);

			$profilePost = $profilePostModel->prepareProfilePost($profilePost, $receivingUser);
			if (!empty($profilePost['isIgnored']))
			{
				unset($profilePosts[$id]);
			}
		}
		uasort($profilePosts, array(
			__CLASS__,
			'_cmpFunction'
		));
		$profilePosts = array_slice($profilePosts, 0, $widget['options']['limit'], true);

		return $profilePosts;
	}

	protected static function _cmpFunction($a, $b)
	{
		return $b['post_date'] - $a['post_date'];
	}

}
