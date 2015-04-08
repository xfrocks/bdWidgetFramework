<?php

class WidgetFramework_XenForo_ControllerPublic_Misc extends XFCP_WidgetFramework_XenForo_ControllerPublic_Misc
{
    public function actionWfLayoutEditor()
    {
        if (!XenForo_Application::isRegistered('session')) {
            return $this->responseNoPermission();
        }
        $session = XenForo_Application::getSession();

        $visitor = XenForo_Visitor::getInstance();
        if (!$visitor->hasAdminPermission('style')) {
            return $this->responseNoPermission();
        }

        $session->set('_WidgetFramework_layoutEditor', !WidgetFramework_Option::get('layoutEditorEnabled'));

        return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $this->getDynamicRedirect());
    }

}
