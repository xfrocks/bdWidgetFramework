<?php

class WidgetFramework_WidgetRenderer_XFMG_Statistics extends WidgetFramework_WidgetRenderer
{
    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])) {
            return new XenForo_Phrase('xengallery_gallery_statistics');
        }

        return parent::extraPrepareTitle($widget);
    }

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

    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        if (!WidgetFramework_Core::xfmgFound()) {
            return '';
        }

        return $renderTemplateObject->render();
    }
}