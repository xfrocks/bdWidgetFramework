<?php

class WidgetFramework_WidgetRenderer_XFRM_Resources extends WidgetFramework_WidgetRenderer
{
	public function extraPrepareTitle(array $widget)
	{
		if (empty($widget['title']))
		{
			if (empty($widget['options']['type']))
			{
				$widget['options']['type'] = 'new';
			}

			switch ($widget['options']['type'])
			{
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

		$categoriesRaw = WidgetFramework_Core::getInstance()->getModelFromCache('XenResource_Model_Category')->getAllCategories();
		$categories = array();
		foreach ($categoriesRaw as $categoryId => &$categoryRaw)
		{
			$category = array(
				'value' => $categoryId,
				'label' => $categoryRaw['category_title'],
				'depth' => $categoryRaw['depth'],
			);

			if (!empty($params['options']['categories']) AND in_array($category['value'], $params['options']['categories']))
			{
				$category['selected'] = true;
			}

			$categories[] = $category;
		}

		$template->setParam('categories', $categories);

		return parent::_renderOptions($template);
	}

	protected function _validateOptionValue($optionKey, &$optionValue)
	{
		switch ($optionKey)
		{
			case 'limit':
				if (empty($optionValue))
				{
					$optionValue = 5;
				}
				break;
			case 'type':
				if (empty($optionValue))
				{
					$optionValue = 'new';
				}
				break;
		}

		return parent::_validateOptionValue($optionKey, $optionValue);
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return 'wf_widget_xfrm_resources';
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		if (empty($widget['options']['limit']))
		{
			$widget['options']['limit'] = 5;
		}
		if (empty($widget['options']['type']))
		{
			$widget['options']['type'] = 'new';
		}

		$resources = $this->_getResources($widget, $positionCode, $params, $renderTemplateObject);
		$renderTemplateObject->setParam('resources', $resources);

		return $renderTemplateObject->render();
	}

	protected function _getResources(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		$core = WidgetFramework_Core::getInstance();
		$categoryModel = $core->getModelFromCache('XenResource_Model_Category');
		$resourceModel = $core->getModelFromCache('XenResource_Model_Resource');
		$visitor = XenForo_Visitor::getInstance();

		$categoryIds = array();
		$resources = array();

		$viewableCategories = $categoryModel->getViewableCategories();
		foreach ($viewableCategories as $category)
		{
			if (!empty($widget['options']['categories']))
			{
				if (in_array($category['resource_category_id'], $widget['options']['categories']))
				{
					// configured with some category id
					// only include those that were selected
					$categoryIds[] = $category['resource_category_id'];
				}
			}
			else
			{
				$categoryIds[] = $category['resource_category_id'];
			}
		}

		if (!empty($categoryIds))
		{
			$conditions = array(
				'deleted' => $visitor->isSuperAdmin(),
				'moderated' => $visitor->isSuperAdmin(),
				'resource_category_id' => $categoryIds,
			);
			$fetchOptions = array(
				'limit' => $widget['options']['limit'],
				'join' => XenResource_Model_Resource::FETCH_USER | XenResource_Model_Resource::FETCH_CATEGORY | XenResource_Model_Resource::FETCH_DESCRIPTION,
			);

			switch ($widget['options']['type'])
			{
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

	private function __getFakeResource($state)
	{
		$resource = array('resource_state' => $state, );

		if (XenForo_Visitor::getUserId() == 0)
		{
			$resource['user_id'] = 1;
		}
		else
		{
			$resource['user_id'] = 0;
		}

		return $resource;
	}

}
