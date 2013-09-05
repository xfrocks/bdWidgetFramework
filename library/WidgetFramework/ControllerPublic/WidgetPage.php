<?php

class WidgetFramework_ControllerPublic_WidgetPage extends XenForo_ControllerPublic_Abstract
{

	public function actionIndex()
	{
		$nodeName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);
		$widgetPage = $this->_getWidgetPageOrError($nodeName);

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink('widget-pages', $widgetPage, array('page' => $page)));

		$widgets = $this->_getWidgetModel()->getWidgetPageWidgets($widgetPage['node_id']);

		$nodeBreadCrumbs = $this->_getNodeModel()->getNodeBreadCrumbs($widgetPage, false);

		$viewParams = array(
			'widgetPage' => $widgetPage,
			'widgets' => $widgets,

			'nodeBreadCrumbs' => $nodeBreadCrumbs,
			'page' => $page,
		);

		if (class_exists('bdCache_ControllerHelper_Cache'))
		{
			$this->getHelper('bdCache_ControllerHelper_Cache')->markViewParamsAsCacheable($viewParams);
		}

		return $this->responseView('WidgetFramework_ViewPublic_WidgetPage_Index', 'wf_widget_page_index', $viewParams);
	}

	protected function _getWidgetPageOrError($nodeName)
	{
		$visitor = XenForo_Visitor::getInstance();
		$fetchOptions = array('permissionCombinationId' => $visitor['permission_combination_id']);

		$widgetPage = $this->_getWidgetPageModel()->getWidgetPageByName($nodeName, $fetchOptions);
		if (!$widgetPage || $widgetPage['node_type_id'] != 'WF_WidgetPage')
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('wf_requested_widget_page_not_found'), 404));
		}

		if (isset($widgetPage['node_permission_cache']))
		{
			$visitor->setNodePermissions($widgetPage['node_id'], $widgetPage['node_permission_cache']);
			unset($widgetPage['node_permission_cache']);
		}

		if (!$this->_getWidgetPageModel()->canViewWidgetPage($widgetPage, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		if ($widgetPage['effective_style_id'])
		{
			$this->setViewStateChange('styleId', $widgetPage['effective_style_id']);
		}

		return $widgetPage;
	}

	/**
	 * @return WidgetFramework_Model_WidgetPage
	 */
	protected function _getWidgetPageModel()
	{
		return $this->getModelFromCache('WidgetFramework_Model_WidgetPage');
	}

	/**
	 * @return WidgetFramework_Model_Widget
	 */
	protected function _getWidgetModel()
	{
		return $this->getModelFromCache('WidgetFramework_Model_Widget');
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}

}
