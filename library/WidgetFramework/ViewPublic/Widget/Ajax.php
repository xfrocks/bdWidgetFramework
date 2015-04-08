<?php

class WidgetFramework_ViewPublic_Widget_Ajax extends XenForo_ViewPublic_Base
{
    public function prepareParams()
    {
        if (!empty($this->_params['_preparedParams'])) {
            return;
        }
        $this->_params['_preparedParams'] = true;

        $widget = $this->_params['widget'];
        $widget['_ajaxLoadParams'] = $this->_params['ajaxLoadParams'];

        $position = 'wf_widget_ajax';
        $hookPosition = 'hook:wf_widget_ajax';
        if (!empty($widget['_ajaxLoadParams'][WidgetFramework_WidgetRenderer::PARAM_IS_HOOK])) {
            $widget['position'] = $hookPosition;
            $widget['template_for_hooks'] = array($hookPosition => array($position));
        } else {
            $widget['position'] = $position;
        }

        $widgets = array($widget['widget_id'] => $widget);
        $core = WidgetFramework_Core::getInstance();
        $core->removeAllWidgets();
        $core->addWidgets($widgets);

        $_REQUEST['_getRender'] = 1;
        $_REQUEST['_getRenderAsTemplateHtml'] = 1;
        $_REQUEST['_renderedIds'] = $widget['widget_id'];
        WidgetFramework_Listener::saveLayoutEditorRendered(true);
    }

}