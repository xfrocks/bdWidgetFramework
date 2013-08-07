<?php

class WidgetFramework_WidgetRenderer_XFRM_Resources extends WidgetFramework_WidgetRenderer
{
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
			case 'type':
				if (!in_array($optionValue, array(
					'new',
					'latest_update',
					'highest_rating',
					'most_downloaded'
				)))
				{
					throw new XenForo_Exception(new XenForo_Phrase('wf_widget_xfrm_resources_invalid_type'), true);
				}
				break;
			case 'limit':
				if (empty($optionValue))
					$optionValue = 5;
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
		$core = WidgetFramework_Core::getInstance();
		$resourceModel = $core->getModelFromCache('XenResource_Model_Resource');
		$visitor = XenForo_Visitor::getInstance();

		$resources = array();
		$canViewResource = $resourceModel->canViewResource($this->__getFakeResource('visible'), array());

		if ($canViewResource)
		{
			$canViewDeleted = $resourceModel->canViewResource($this->__getFakeResource('deleted'), array());
			$canViewModerated = $resourceModel->canViewResource($this->__getFakeResource('moderated'), array());

			$conditions = array(
				'deleted' => $canViewDeleted,
				'moderated' => $canViewModerated,
			);
			$fetchOptions = array(
				'limit' => $widget['options']['limit'],
				'join' => XenResource_Model_Resource::FETCH_USER | XenResource_Model_Resource::FETCH_CATEGORY | XenResource_Model_Resource::FETCH_DESCRIPTION,
			);

			if (!empty($widget['options']['categories']))
			{
				$conditions['resource_category_id'] = $widget['options']['categories'];
			}

			switch ($widget['options']['type'])
			{
				case 'new':
					$resources = $resourceModel->getResources($conditions, array_merge($fetchOptions, array(
						'order' => 'resource_date',
						'direction' => 'desc',
					)));
					break;
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
			}
		}

		$renderTemplateObject->setParam('resources', $resources);

		return $renderTemplateObject->render();
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
