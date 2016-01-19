<?php

class WidgetFramework_WidgetRenderer_XFMG_Comments extends WidgetFramework_WidgetRenderer
{
    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])) {
            return new XenForo_Phrase('xengallery_recent_comments');
        }

        return parent::extraPrepareTitle($widget);
    }

    protected function _getConfiguration()
    {
        return array(
            'name' => 'XFMG: Recent Comments',
            'options' => array(
                'limit' => XenForo_Input::UINT,
            ),
            'useCache' => true,
            'useUserCache' => true,
            'cacheSeconds' => 300,
            'useWrapper' => false,
            'canAjaxLoad' => true,
        );
    }

    protected function _getOptionsTemplate()
    {
        return 'wf_widget_options_xfmg_comments';
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'xengallery_recent_comments_block';
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

        $limit = XenForo_Application::getOptions()->get('xengalleryShowRecentComments', 'limit');
        if (!empty($widget['options']['limit'])) {
            $limit = $widget['options']['limit'];
        }

        $renderTemplateObject->setParam('limit', $limit);
        if (!empty($widget['_runtime']['title'])) {
            $renderTemplateObject->setParam('title', $widget['_runtime']['title']);
        }

        return $renderTemplateObject->render();
    }
}