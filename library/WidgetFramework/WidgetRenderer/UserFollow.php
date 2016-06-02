<?php

class WidgetFramework_WidgetRenderer_UserFollow extends WidgetFramework_WidgetRenderer
{
    protected function _getConfiguration()
    {
        return array(
            'name' => 'User: Follow',
            'useWrapper' => false,
        );
    }

    protected function _getOptionsTemplate()
    {
        return false;
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'wf_widget_user_follow';
    }

    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        /** @var XenForo_Model_User $userModel */
        $userModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_User');

        $user = XenForo_Visitor::getInstance();
        $userId = $user['user_id'];

        if (!empty($user['following'])) {
            $followingToShowCount = 6;
            $followingCount = substr_count($user['following'], ',') + 1;

            $following = $userModel->getFollowedUserProfiles($userId, $followingToShowCount, 'RAND()');
            if (count($following) < $followingToShowCount) {
                $followingCount = count($following);
            }
        } else {
            $followingCount = 0;
            $following = array();
        }

        $followersCount = $userModel->countUsersFollowingUserId($userId);
        $followers = $userModel->getUsersFollowingUserId($userId, 6, 'RAND()');

        $renderTemplateObject->setParams(array(
            'followingCount' => $followingCount,
            'followersCount' => $followersCount,

            'following' => $following,
            'followers' => $followers,
        ));

        return $renderTemplateObject->render();
    }
}
