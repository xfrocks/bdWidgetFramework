<?php

class WidgetFramework_WidgetRenderer_Users extends WidgetFramework_WidgetRenderer
{
    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])) {
            if ((empty($widget['options']['order']) || $widget['options']['order'] == 'register_date')
                && (empty($widget['options']['direction']) || strtoupper($widget['options']['direction']) == 'DESC')
            ) {
                return new XenForo_Phrase('wf_newest_members');
            }

            return new XenForo_Phrase('wf_users');
        }

        return parent::extraPrepareTitle($widget);
    }

    protected function _getConfiguration()
    {
        return array(
            'name' => 'Users',
            'options' => array(
                'limit' => XenForo_Input::UINT,
                'order' => XenForo_Input::STRING,
                'direction' => XenForo_Input::STRING,

                // since 1.3
                'displayMode' => XenForo_Input::STRING,
            ),
            'useCache' => true,
            'cacheSeconds' => 1800, // cache for 30 minutes
        );
    }

    protected function _getOptionsTemplate()
    {
        return 'wf_widget_options_users';
    }

    protected function _renderOptions(XenForo_Template_Abstract $template)
    {
        $template->setParam('_xfrmFound', WidgetFramework_Core::xfrmFound());

        return parent::_renderOptions($template);
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'wf_widget_users';
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
        if (empty($widget['options']['order'])) {
            $widget['options']['order'] = 'register_date';
        }
        if (empty($widget['options']['direction'])) {
            $widget['options']['direction'] = 'DESC';
        }
        if (empty($widget['options']['displayMode'])) {
            $widget['options']['displayMode'] = 'avatarOnlyBigger';
        }

        $users = $this->_getUsers($widget, $positionCode, $params, $renderTemplateObject);

        $renderTemplateObject->setParam('widget', $widget);
        $renderTemplateObject->setParam('users', $users);

        return $renderTemplateObject->render();
    }

    protected function _getUsers(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        // try to be smart and get the users data if they happen to be available
        if ($positionCode == 'member_notable'
            && isset($params['latestUsers'])
            && $widget['options']['limit'] == 12
            && $widget['options']['order'] == 'register_date'
            && strtoupper($widget['options']['direction']) == 'DESC'
        ) {
            return $params['latestUsers'];
        }

        /** @var WidgetFramework_Model_User $wfUserModel */
        $wfUserModel = WidgetFramework_Core::getInstance()->getModelFromCache('WidgetFramework_Model_User');
        $conditions = array(
            // sondh@2012-09-13
            // do not display not confirmed or banned users
            'user_state' => 'valid',
            'is_banned' => 0
        );
        $fetchOptions = array(
            'limit' => $widget['options']['limit'],
            'order' => $widget['options']['order'],
            'direction' => $widget['options']['direction'],
        );

        $userIds = $wfUserModel->getUserIds($conditions, $fetchOptions);
        return $wfUserModel->getUsersByIdsInOrder($userIds);
    }
}
