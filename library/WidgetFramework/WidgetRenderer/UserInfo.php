<?php

class WidgetFramework_WidgetRenderer_UserInfo extends WidgetFramework_WidgetRenderer
{
    protected function _getConfiguration()
    {
        return array(
            'name' => 'User: Information',
            'options' => array(
                'avatar' => XenForo_Input::UINT,
            ),
            'useWrapper' => false,
        );
    }

    protected function _getOptionsTemplate()
    {
        return 'wf_widget_options_user_info';
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'wf_widget_user_info';
    }

    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        /** @var XenForo_Model_User $userModel */
        $userModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_User');

        if (isset($params['user'])) {
            $user = $params['user'];
        } else {
            $user = XenForo_Visitor::getInstance()->toArray();
        }

        $renderTemplateObject->setParam('user', $user);
        $renderTemplateObject->setParam('canViewOnlineStatus', $userModel->canViewUserOnlineStatus($user));
        $renderTemplateObject->setParam('canViewWarnings', $userModel->canViewWarnings());

        return $renderTemplateObject->render();
    }
}
