<?php

class WidgetFramework_Route_PrefixAdmin_Widgets implements XenForo_Route_Interface
{
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'widget_id');
		return $router->getRouteMatch('WidgetFramework_ControllerAdmin_Widget', $action, 'widgetFramework');
	}

	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'widget_id');
	}

}
