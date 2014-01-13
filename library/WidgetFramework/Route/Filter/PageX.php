<?php

class WidgetFramework_Route_Filter_PageX implements XenForo_Route_Interface
{
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$indexNodeId = WidgetFramework_Option::get('indexNodeId');

		if (empty($indexNodeId))
		{
			return false;
		}

		if (preg_match('#^page-(\d+)($|/)#i', $routePath, $matches))
		{
			$match = $router->getRouteMatch();
			$match->setModifiedRoutePath(sprintf('widget-page-index/page-%d', $matches[1]));
			return $match;
		}

		return false;
	}

}
