<?php

class WidgetFramework_WidgetRenderer_UsersStaff extends WidgetFramework_WidgetRenderer
{
    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])) {
            return new XenForo_Phrase('wf_users_staff');
        }

        return parent::extraPrepareTitle($widget);
    }

    protected function _getConfiguration()
    {
        return array(
            'name' => 'Users: Staff Members',
            'options' => array(
                'limit' => XenForo_Input::UINT,
                'displayMode' => XenForo_Input::STRING,
            ),
            'useCache' => true,
            'cacheSeconds' => 86400, // cache for a day
        );
    }

    protected function _getOptionsTemplate()
    {
        return 'wf_widget_options_users_staff';
    }

    protected function _validateOptionValue($optionKey, &$optionValue)
    {
        switch ($optionKey) {
            case 'limit':
                if (empty($optionValue)) {
                    $optionValue = 0;
                }
                break;
        }

        return parent::_validateOptionValue($optionKey, $optionValue);
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
            $widget['options']['limit'] = 5;
        }

        $users = false;

        // try to be smart and get the users data if they happen to be available
        if ($positionCode == 'member_notable'
            && $widget['options']['limit'] == 0
            && !empty($params['staff'])
        ) {
            $users = $params['staff'];
        }

        if ($users === false) {
            /** @var XenForo_Model_User $userModel */
            $userModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_User');
            $users = $userModel->getUsers(array('is_staff' => true), array(
                'join' => XenForo_Model_User::FETCH_USER_FULL,
                'limit' => $widget['options']['limit'],
                'order' => 'username',
            ));
        }

        $renderTemplateObject->setParam('users', $users);

        return $renderTemplateObject->render();
    }

}
