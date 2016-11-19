<?php

class WidgetFramework_WidgetRenderer_Birthday extends WidgetFramework_WidgetRenderer
{
    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])) {
            return new XenForo_Phrase('wf_birthday');
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

        /** @var XenForo_Model_UserGroup $userGroupModel */
        $userGroupModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_UserGroup');
        $userGroups = $userGroupModel->getAllUserGroupTitles();

        $whitelistUserGroups = array();
        $blacklistUserGroups = array();

        $optionWhitelist = array();
        if (!empty($params['options']['whitelist_user_groups'])) {
            $optionWhitelist = $params['options']['whitelist_user_groups'];
        }

        $optionBlacklist = array();
        if (!empty($params['options']['blacklist_user_groups'])) {
            $optionBlacklist = $params['options']['blacklist_user_groups'];
        }

        foreach ($userGroups as $userGroupId => $title) {
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

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'wf_widget_birthday';
    }

    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        if (empty($widget['options']['limit'])) {
            $widget['options']['limit'] = 12;
        }

        $users = $this->_getUsers($widget, $positionCode, $params, $renderTemplateObject);
        if (empty($users)) {
            return '';
        }

        $core = WidgetFramework_Core::getInstance();
        /** @var XenForo_Model_User $userModel */
        $userModel = $core->getModelFromCache('XenForo_Model_User');
        /** @var XenForo_Model_UserProfile $userProfileModel */
        $userProfileModel = $core->getModelFromCache('XenForo_Model_UserProfile');
        foreach (array_keys($users) as $userId) {
            $user = &$users[$userId];

            if (!empty($widget['options']['whitelist_user_groups'])) {
                // check for whitelist user groups
                if (!$userModel->isMemberOfUserGroup($user, $widget['options']['whitelist_user_groups'])) {
                    unset($users[$userId]);
                    continue;
                }
            }

            if (!empty($widget['options']['blacklist_user_groups'])) {
                // check for blacklist user groups
                if ($userModel->isMemberOfUserGroup($user, $widget['options']['blacklist_user_groups'])) {
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

    protected function _getUsers(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        // try to be smart and get the users data if they happen to be available
        if ($positionCode == 'member_notable'
            && isset($params['birthdays'])
            && $widget['options']['limit'] == 12
            && empty($widget['options']['avatar_only'])
            && empty($widget['options']['whitelist_user_groups'])
            && empty($widget['options']['blacklist_user_groups'])
        ) {
            return $params['birthdays'];
        }

        /** @var WidgetFramework_Model_User $wfUserModel */
        $wfUserModel = WidgetFramework_Core::getInstance()->getModelFromCache('WidgetFramework_Model_User');
        $todayStart = XenForo_Locale::getDayStartTimestamps();
        $todayStart = $todayStart['today'];
        $day = XenForo_Locale::getFormattedDate($todayStart, 'd');
        $month = XenForo_Locale::getFormattedDate($todayStart, 'm');

        $conditions = array(
            WidgetFramework_Model_User::CONDITIONS_DOB => array(
                'd' => $day,
                'm' => $month
            ),

            // checks for user state and banned status
            // since 1.1.2
            'user_state' => 'valid',
            'is_banned' => false,

            // only include users who are active recently
            // since 2.6.3
            'active_recently' => true
        );
        $fetchOptions = array(
            'order' => 'username',
        );

        if (!empty($widget['options']['limit'])) {
            $fetchOptions['limit'] = $widget['options']['limit'];
        }

        if (!empty($widget['options']['avatar_only'])) {
            $conditions[WidgetFramework_Model_User::CONDITIONS_HAS_AVATAR] = true;
        }

        $userIds = $wfUserModel->getUserIds($conditions, $fetchOptions);
        return $wfUserModel->getUsersByIdsInOrder($userIds);
    }
}
