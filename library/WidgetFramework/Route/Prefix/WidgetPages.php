<?php

class WidgetFramework_Route_Prefix_WidgetPages implements XenForo_Route_Interface
{

	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithStringParam($routePath, $request, 'node_name');
		$action = $router->resolveActionAsPageNumber($action, $request);

		return $router->getRouteMatch('WidgetFramework_ControllerPublic_WidgetPage', 'index', 'forums');
	}

	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		if (!empty($data['node_id']) AND $data['node_id'] == WidgetFramework_Option::get('indexNodeId'))
		{
			return WidgetFramework_Helper_Index::buildBasicLink('widget-page-index', '', $extension);
		}
		
		$action = XenForo_Link::getPageNumberAsAction($action, $extraParams);

		return XenForo_Link::buildBasicLinkWithStringParam($outputPrefix, $action, $extension, $data, 'node_name');
	}

}
