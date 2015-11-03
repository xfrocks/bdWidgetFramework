<?php

class WidgetFramework_WidgetRenderer_XFMG_Statistics extends WidgetFramework_WidgetRenderer
{
    protected function _getConfiguration()
    {
        return array(
            'name' => 'XFMG: Statistics',
            'options' => array(
                'limit' => XenForo_Input::UINT,
            ),
            'useWrapper' => false,
            'canAjaxLoad' => true,
        );
    }

    protected function _getOptionsTemplate()
    {
        return '';
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'wf_widget_xfmg_statistics';
    }

    protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
    {
        return $renderTemplateObject->render();
    }
}