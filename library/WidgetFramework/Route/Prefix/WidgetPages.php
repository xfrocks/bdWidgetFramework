<?php

class WidgetFramework_Route_Prefix_WidgetPages implements XenForo_Route_Interface
{

	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerOrStringParam($routePath, $request, 'node_id', 'node_name');
		$action = $router->resolveActionAsPageNumber($action, $request);

		return $router->getRouteMatch('WidgetFramework_ControllerPublic_WidgetPage', $action, 'forums');
	}

	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		$action = XenForo_Link::getPageNumberAsAction($action, $extraParams);

		if (!empty($data['node_id']) AND $data['node_id'] == WidgetFramework_Option::get('indexNodeId'))
		{
			if (XenForo_Application::$versionId > 1020000 AND !empty($action) AND preg_match('#^page-(\d+)$#i', $action))
			{
				// support http://domain.com/xenforo/page-2/ uris
				// XenForo 1.2.0 and up only
				return WidgetFramework_Helper_Index::buildBasicLink($action, '', $extension);
			}
			elseif (empty($action))
			{
				return WidgetFramework_Helper_Index::buildBasicLink('widget-page-index', '', $extension);
			}
		}

		return XenForo_Link::buildBasicLinkWithStringParam($outputPrefix, $action, $extension, $data, 'node_name');
	}

}
