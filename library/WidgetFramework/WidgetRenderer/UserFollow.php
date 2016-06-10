<?php

class WidgetFramework_WidgetRenderer_UserFollow extends WidgetFramework_WidgetRenderer
{
    public static /** @noinspection HtmlUnknownTarget */
        $countFormat = '<a href="%2$s" class="count OverlayTrigger">%1$d</a>';

    protected static $_links = array();
    protected static $_counts = array();

    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])) {
            if (!empty($widget['options']['type'])
                && $widget['options']['type'] === 'followers'
            ) {
                $phrase = new XenForo_Phrase('followers');
                $countKey = 'followersCount';
            } else {
                $phrase = new XenForo_Phrase('following');
                $countKey = 'followingCount';
            }

            if (isset(self::$_counts[$widget['widget_id']][$countKey])
                && !empty(self::$_links[$widget['widget_id']])
                && empty($widget['group_id'])
            ) {
                return WidgetFramework_Helper_String::createArrayOfStrings(array(
                    $phrase,
                    sprintf(self::$countFormat,
                        self::$_counts[$widget['widget_id']][$countKey],
                        self::$_links[$widget['widget_id']])
                ));
            } else {
                return $phrase;
            }
        }

        return parent::extraPrepareTitle($widget);
    }

    protected function _getConfiguration()
    {
        return array(
            'name' => 'User: Follow',
            'options' => array(
                'type' => XenForo_Input::STRING,
            )
        );
    }

    protected function _getOptionsTemplate()
    {
        return 'wf_widget_options_user_follow';
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

        $user = XenForo_Visitor::getInstance()->toArray();

        $limit = !empty($widget['options']['limit']) ? $widget['options']['limit'] : 6;
        $type = 'following';
        if (!empty($widget['options']['type'])) {
            switch ($widget['options']['type']) {
                case 'following':
                case 'followers':
                    $type = $widget['options']['type'];
                    break;
            }
        }

        switch ($type) {
            case 'following':
                if (!empty($user['following'])) {
                    $followingCount = substr_count($user['following'], ',') + 1;

                    $following = $userModel->getFollowedUserProfiles($user['user_id'], $limit, 'RAND()');
                    if (count($following) < $limit) {
                        $followingCount = count($following);
                    }
                } else {
                    $followingCount = 0;
                    $following = array();
                }

                $renderTemplateObject->setParams(array(
                    'followingCount' => $followingCount,
                    'following' => $following,
                ));
                self::$_links[$widget['widget_id']] = XenForo_Link::buildPublicLink('members/following', $user);
                self::$_counts[$widget['widget_id']]['followingCount'] = $followingCount;
                break;
            case 'followers':
                $followersCount = $userModel->countUsersFollowingUserId($user['user_id']);
                $followers = $userModel->getUsersFollowingUserId($user['user_id'], 6, 'RAND()');

                $renderTemplateObject->setParams(array(
                    'followersCount' => $followersCount,
                    'followers' => $followers,
                ));
                self::$_links[$widget['widget_id']] = XenForo_Link::buildPublicLink('members/followers', $user);
                self::$_counts[$widget['widget_id']]['followersCount'] = $followersCount;
                break;
        }

        $renderTemplateObject->setParam('user', $user);

        return $renderTemplateObject->render();
    }
}
