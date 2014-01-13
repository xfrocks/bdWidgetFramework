<?php

class WidgetFramework_Route_Prefix_WidgetPageIndex implements XenForo_Route_Interface
{

	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionAsPageNumber($routePath, $request);

		return $router->getRouteMatch('WidgetFramework_ControllerPublic_WidgetPage', 'as-index');
	}

}
