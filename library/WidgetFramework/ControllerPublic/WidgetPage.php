<?php

class WidgetFramework_ControllerPublic_WidgetPage extends XenForo_ControllerPublic_Abstract
{

	protected function _postDispatch($controllerResponse, $controllerName, $action)
	{
		if (XenForo_Application::isRegistered('nodesAsTabsAPI'))
		{
			$nodeId = (isset($controllerResponse->params['widgetPage']['node_id']) ? $controllerResponse->params['widgetPage']['node_id'] : 0);

			NodesAsTabs_API::postDispatch($this, $nodeId, $controllerResponse, $controllerName, $action);
		}
	}

	public function actionIndex()
	{
		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$nodeName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);
		$widgetPage = $this->_getWidgetPageOrError($nodeId ? $nodeId : $nodeName);

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

	public function actionAsIndex()
	{
		$this->_request->setParam('node_id', WidgetFramework_Option::get('indexNodeId'));

		$this->_routeMatch->setSections(WidgetFramework_Option::get('indexTabId'));

		return $this->responseReroute(__CLASS__, 'index');
	}

	protected function _getWidgetPageOrError($nodeIdOrName)
	{
		$visitor = XenForo_Visitor::getInstance();
		$fetchOptions = array('permissionCombinationId' => $visitor['permission_combination_id']);

		if (is_numeric($nodeIdOrName))
		{
			$widgetPage = $this->_getWidgetPageModel()->getWidgetPageById($nodeIdOrName, $fetchOptions);
		}
		else
		{
			$widgetPage = $this->_getWidgetPageModel()->getWidgetPageByName($nodeIdOrName, $fetchOptions);
		}

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
