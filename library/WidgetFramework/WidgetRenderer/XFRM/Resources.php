<?php

class WidgetFramework_WidgetRenderer_XFRM_Resources extends WidgetFramework_WidgetRenderer
{
    const CATEGORIES_OPTION_SPECIAL_CURRENT = 'current_category';
    const CATEGORIES_OPTION_SPECIAL_CURRENT_AND_CHILDREN = 'current_category_and_children';
    const CATEGORIES_OPTION_SPECIAL_PARENT = 'parent_category';
    const CATEGORIES_OPTION_SPECIAL_PARENT_AND_CHILDREN = 'parent_category_and_children';

    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])) {
            if (empty($widget['options']['type'])) {
                $widget['options']['type'] = 'new';
            }

            switch ($widget['options']['type']) {
                case 'latest_update':
                    return new XenForo_Phrase('wf_widget_xfrm_resources_type_latest_update');
                case 'highest_rating':
                    return new XenForo_Phrase('wf_widget_xfrm_resources_type_highest_rating');
                case 'most_downloaded':
                    return new XenForo_Phrase('wf_widget_xfrm_resources_type_most_downloaded');
                case 'new':
                default:
                    return new XenForo_Phrase('wf_widget_xfrm_resources_type_new');
            }
        }

        return parent::extraPrepareTitle($widget);
    }

    protected function _getConfiguration()
    {
        return array(
            'name' => 'XFRM: Resources',
            'options' => array(
                'categories' => XenForo_Input::ARRAY_SIMPLE,
                'limit' => XenForo_Input::UINT,
                'type' => XenForo_Input::STRING,
                'use_wrapper' => XenForo_Input::UINT,
                'custom_template' => XenForo_Input::STRING
            ),
            'useCache' => true,
            'useUserCache' => true,
            'cacheSeconds' => 300, // cache for 5 minutes
        );
    }

    protected function _getOptionsTemplate()
    {
        return 'wf_widget_options_xfrm_resources';
    }

    protected function _renderOptions(XenForo_Template_Abstract $template)
    {
        $params = $template->getParams();

        /** @var XenResource_Model_Category $categoryModel */
        $categoryModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenResource_Model_Category');
        $categoriesRaw = $categoryModel->getAllCategories();
        $categories = array();

        foreach (array(
                     self::CATEGORIES_OPTION_SPECIAL_CURRENT,
                     self::CATEGORIES_OPTION_SPECIAL_CURRENT_AND_CHILDREN,
                     self::CATEGORIES_OPTION_SPECIAL_PARENT,
                     self::CATEGORIES_OPTION_SPECIAL_PARENT_AND_CHILDREN,
                 ) as $specialId) {
            $categories[] = array(
                'value' => $specialId,
                'label' => new XenForo_Phrase(sprintf('wf_%s', $specialId)),
                'selected' => in_array($specialId, $params['options']['categories']),
            );
        }

        foreach ($categoriesRaw as $categoryId => &$categoryRaw) {
            $category = array(
                'value' => $categoryId,
                'label' => $categoryRaw['category_title'],
                'depth' => $categoryRaw['depth'],
            );

            if (!empty($params['options']['categories'])
                && in_array($category['value'], $params['options']['categories'])
            ) {
                $category['selected'] = true;
            }

            $categories[] = $category;
        }

        $template->setParam('categories', $categories);

        return parent::_renderOptions($template);
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        if (!empty($widget['options']['custom_template'])) {
            return $widget['options']['custom_template'];
        }

        return 'wf_widget_xfrm_resources';
    }

    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        if (empty($widget['options']['limit'])) {
            $widget['options']['limit'] = 5;
        }
        if (empty($widget['options']['type'])) {
            $widget['options']['type'] = 'new';
        }

        $resources = $this->_getResources($widget, $positionCode, $params, $renderTemplateObject);
        $renderTemplateObject->setParam('resources', $resources);

        return $renderTemplateObject->render();
    }

    protected function _getResources(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        $core = WidgetFramework_Core::getInstance();

        /** @var XenResource_Model_Resource $resourceModel */
        $resourceModel = $core->getModelFromCache('XenResource_Model_Resource');
        $visitor = XenForo_Visitor::getInstance();

        $categoryIds = $this->_getCategoryIds(
            $widget,
            !empty($params['category']) ? $params['category'] : array()
        );
        $resources = array();

        if (!empty($categoryIds)) {
            $conditions = array(
                'deleted' => $visitor->isSuperAdmin(),
                'moderated' => $visitor->isSuperAdmin(),
                'resource_category_id' => $categoryIds,
            );
            $fetchOptions = array(
                'limit' => $widget['options']['limit'],
                'join' => XenResource_Model_Resource::FETCH_USER
                    | XenResource_Model_Resource::FETCH_CATEGORY
                    | XenResource_Model_Resource::FETCH_DESCRIPTION,
            );

            switch ($widget['options']['type']) {
                case 'latest_update':
                    $resources = $resourceModel->getResources($conditions, array_merge($fetchOptions, array(
                        'order' => 'last_update',
                        'direction' => 'desc',
                    )));
                    break;
                case 'highest_rating':
                    $resources = $resourceModel->getResources($conditions, array_merge($fetchOptions, array(
                        'order' => 'rating_weighted',
                        'direction' => 'desc',
                    )));
                    break;
                case 'most_downloaded':
                    $resources = $resourceModel->getResources($conditions, array_merge($fetchOptions, array(
                        'order' => 'download_count',
                        'direction' => 'desc',
                    )));
                    break;
                case 'new':
                default:
                    $resources = $resourceModel->getResources($conditions, array_merge($fetchOptions, array(
                        'order' => 'resource_date',
                        'direction' => 'desc',
                    )));
                    break;
            }

            $resources = $resourceModel->prepareResources($resources);
        }

        return $resources;
    }

    protected function _getCategoryIds(array $widget, array $currentCategory = array())
    {
        $core = WidgetFramework_Core::getInstance();
        /** @var XenResource_Model_Category $categoryModel */
        $categoryModel = $core->getModelFromCache('XenResource_Model_Category');

        $categoryIds = array();
        $viewableCategories = $categoryModel->getViewableCategories();

        foreach ($viewableCategories as $category) {
            if (!empty($widget['options']['categories'])) {
                if (in_array($category['resource_category_id'], $widget['options']['categories'])) {
                    // configured with some category id
                    // only include those that were selected
                    $categoryIds[] = $category['resource_category_id'];
                }
            } else {
                $categoryIds[] = $category['resource_category_id'];
            }
        }

        foreach ($widget['options']['categories'] as $categoryId) {
            switch ($categoryId) {
                case self::CATEGORIES_OPTION_SPECIAL_CURRENT:
                    $categoryIds[] = isset($currentCategory['resource_category_id']) ? $currentCategory['resource_category_id'] : 0;
                    break;
                case self::CATEGORIES_OPTION_SPECIAL_CURRENT_AND_CHILDREN:
                    if (isset($currentCategory['resource_category_id'])) {
                        $categoryIds[] = $currentCategory['resource_category_id'];
                        $categoryIds = array_merge($categoryIds, $this->_getChildrenCategoryIds($currentCategory, $viewableCategories));
                    }
                    break;
                case self::CATEGORIES_OPTION_SPECIAL_PARENT:
                    $categoryIds[] = isset($currentCategory['parent_category_id']) ? $currentCategory['parent_category_id'] : 0;
                    break;
                case self::CATEGORIES_OPTION_SPECIAL_PARENT_AND_CHILDREN:
                    if (isset($currentCategory['parent_category_id'])
                        && isset($viewableCategories[$currentCategory['parent_category_id']])
                    ) {
                        $categoryIds[] = $currentCategory['parent_category_id'];
                        $categoryIds = array_merge(
                            $categoryIds,
                            $this->_getChildrenCategoryIds($viewableCategories[$currentCategory['parent_category_id']], $viewableCategories)
                        );
                    }
                    break;
            }
        }

        $categoryIds = array_unique($categoryIds);

        return $categoryIds;
    }

    protected function _getChildrenCategoryIds(array $category, array $viewableCategories)
    {
        $categoryIds = array();
        foreach ($viewableCategories as $viewableCategory) {
            if ($viewableCategory['lft'] > $category['lft']
                && $viewableCategory['lft'] < $category['rgt']
            ) {
                $categoryIds[] = $viewableCategory['resource_category_id'];
            }
        }

        return $categoryIds;
    }

    public function useWrapper(array $widget)
    {
        if (array_key_exists('use_wrapper', $widget['options'])) {
            return $widget['options']['use_wrapper'];
        }

        return parent::useWrapper($widget);
    }
}
