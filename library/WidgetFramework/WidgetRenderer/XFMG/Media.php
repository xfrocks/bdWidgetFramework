<?php

class WidgetFramework_WidgetRenderer_XFMG_Media extends WidgetFramework_WidgetRenderer
{
    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])
            && isset($widget['options']['order'])
        ) {
            switch ($widget['options']['order']) {
                case 'rand':
                    return new XenForo_Phrase('xengallery_random_media');
                case 'new':
                default:
                    return new XenForo_Phrase('xengallery_new_media');
            }
        }

        return parent::extraPrepareTitle($widget);
    }

    protected function _getConfiguration()
    {
        return array(
            'name' => 'XFMG: Media',
            'options' => array(
                'order' => XenForo_Input::STRING,
                'categoryIds' => XenForo_Input::ARRAY_SIMPLE,
                'albums' => XenForo_Input::UINT,
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
        return 'wf_widget_options_xfmg_media';
    }

    protected function _renderOptions(XenForo_Template_Abstract $template)
    {
        $existingCategoryIds = array();
        $existingOptions = $template->getParam('options');
        if (!empty($existingOptions['categoryIds'])) {
            $existingCategoryIds = $existingOptions['categoryIds'];
        }

        /** @var XenGallery_Model_Category $categoryModel */
        $categoryModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenGallery_Model_Category');
        $rows = $categoryModel->getCategoryStructure();
        $categories = array();
        foreach ($rows as $category) {
            $categories[] = array(
                'value' => $category['category_id'],
                'label' => $category['category_title'],
                'depth' => $category['depth'],
                'selected' => in_array($category['category_id'], $existingCategoryIds)
            );
        }
        $template->setParam('categories', $categories);

        return parent::_renderOptions($template);
    }


    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'wf_widget_xfmg_media';
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

        $order = 'new';
        if (!empty($widget['options']['order'])
            && in_array($widget['options']['order'], array(
                'new',
                'rand',
            ), true)
        ) {
            $order = $widget['options']['order'];
        }

        $limit = XenForo_Application::getOptions()->get('xengalleryRecentMediaLimit');
        if (!empty($widget['options']['limit'])) {
            $limit = $widget['options']['limit'];
        }

        $renderTemplateObject->setParam('order', $order);
        $renderTemplateObject->setParam('limit', $limit);
        if (!empty($widget['_runtime']['title'])) {
            $renderTemplateObject->setParam('blockPhrase', $widget['_runtime']['title']);
        }
        $renderTemplateObject->setParam('isSidebarBlock', strpos($positionCode, 'hook:') !== 0);

        return $renderTemplateObject->render();
    }
}