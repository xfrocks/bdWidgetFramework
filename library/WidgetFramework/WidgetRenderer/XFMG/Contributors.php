<?php

class WidgetFramework_WidgetRenderer_XFMG_Contributors extends WidgetFramework_WidgetRenderer
{
    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])) {
            return new XenForo_Phrase('wf_top_contributors');
        }

        return parent::extraPrepareTitle($widget);
    }

    protected function _getConfiguration()
    {
        return array(
            'name' => 'XFMG: Top Contributors',
            'options' => array(
                'limit' => XenForo_Input::UINT,
            ),
            'useCache' => true,
            'useUserCache' => true,
            'cacheSeconds' => 300,
            'canAjaxLoad' => true,
        );
    }

    protected function _getOptionsTemplate()
    {
        return 'wf_widget_xfmg_contributors';
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'wf_widget_xfmg_contributors';
    }

    protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
    {
        $limit = XenForo_Application::getOptions()->get('xengalleryShowTopContributors', 'limit');
        if (!empty($widget['options']['limit'])) {
            $limit = $widget['options']['limit'];
        }

        /** @var XenGallery_Model_Media $mediaModel */
        $mediaModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenGallery_Model_Media');
        $users = $mediaModel->getTopContributors($limit);
        $renderTemplateObject->setParam('users', $users);

        return $renderTemplateObject->render();
    }
}