<?php

class WidgetFramework_WidgetRenderer_OnlineStaff extends WidgetFramework_WidgetRenderer
{
    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])) {
            return new XenForo_Phrase('wf_online_staff');
        }

        return parent::extraPrepareTitle($widget);
    }

    protected function _getConfiguration()
    {
        return array('name' => 'Users Online Now (Staff)');
    }

    protected function _getOptionsTemplate()
    {
        return false;
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'wf_widget_online_staff';
    }

    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        if ('forum_list' === $positionCode
            && !empty($params['onlineUsers'])
        ) {
            $renderTemplateObject->setParam('onlineUsers', $params['onlineUsers']);
        } else {
            if (empty($GLOBALS['WidgetFramework_onlineUsers'])) {
                $visitor = XenForo_Visitor::getInstance();
                /** @var XenForo_Model_Session $sessionModel */
                $sessionModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Session');

                $GLOBALS['WidgetFramework_onlineUsers'] = $sessionModel->getSessionActivityQuickList($visitor->toArray(),
                    array(
                        'cutOff' => array(
                            '>',
                            $sessionModel->getOnlineStatusTimeout()
                        )
                    ), ($visitor['user_id'] ? $visitor->toArray() : null));
            }

            $renderTemplateObject->setParam('onlineUsers', $GLOBALS['WidgetFramework_onlineUsers']);
        }

        return $renderTemplateObject->render();
    }

}
