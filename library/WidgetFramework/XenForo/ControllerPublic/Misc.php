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

    public function actionWfWidget()
    {
        /** @var WidgetFramework_Model_Widget $widgetModel */
        $widgetModel = $this->getModelFromCache('WidgetFramework_Model_Widget');

        $widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
        $widget = $widgetModel->getWidgetById($widgetId);
        if (empty($widget)) {
            return $this->responseNoPermission();
        }

        $ajaxLoadParams = $this->_input->filterSingle('alp', XenForo_Input::STRING);
        $ajaxLoadParams = @json_decode($ajaxLoadParams, true);

        $viewParams = array(
            'widget' => $widget,
            'ajaxLoadParams' => $ajaxLoadParams,
        );

        return $this->responseView('WidgetFramework_ViewPublic_Widget_Ajax', 'wf_widget_ajax', $viewParams);
    }

}
