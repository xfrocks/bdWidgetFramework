<?php

class WidgetFramework_Route_PrefixAdmin_WidgetPages extends XenForo_Route_PrefixAdmin_Nodes
{

	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'node_id');
		return $router->getRouteMatch('WidgetFramework_ControllerAdmin_WidgetPage', $action, 'nodeTree');
	}

	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'node_id', 'title');
	}

}
