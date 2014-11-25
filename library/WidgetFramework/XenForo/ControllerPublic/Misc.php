<?php

class WidgetFramework_XenForo_ControllerPublic_Misc extends XFCP_WidgetFramework_XenForo_ControllerPublic_Misc
{
    public function actionWfLayoutEditor()
    {
        $session = XenForo_Application::get('session');
        if (empty($session)) {
            return $this->responseNoPermission();
        }

        $visitor = XenForo_Visitor::getInstance();
        if (!$visitor->hasAdminPermission('style')) {
            return $this->responseNoPermission();
        }

        $session->set('_WidgetFramework_layoutEditor', !WidgetFramework_Option::get('layoutEditorEnabled'));

        return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $this->getDynamicRedirect());
    }

}
