<?php

class WidgetFramework_Route_Prefix_WidgetPageIndex implements XenForo_Route_Interface
{

	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		return $router->getRouteMatch('WidgetFramework_ControllerPublic_WidgetPage', 'as-index');
	}

}
