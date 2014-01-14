<?php

class WidgetFramework_Helper_Index
{
	const SIMPLE_CACHE_CHILD_NODES = 'wf_childNodes';

	protected static $_setup11x = false;
	protected static $_setup11x_forumsWasHit = false;

	public static function setup()
	{
		if (XenForo_Application::$versionId > 1020000)
		{
			self::_setupForXenForo1_2_x();
		}
		else
		{
			self::_setupForXenForo1_1_x();
		}
	}

	public static function buildBasicLink($prefix, $action, $extension)
	{
		if (self::$_setup11x)
		{
			switch ($prefix)
			{
				case 'widget-page-index':
					$prefix = 'index';
					break;
				case 'index':
					$prefix = 'forums';
					break;
			}

		}

		return XenForo_Link::buildBasicLink($prefix, $action, $extension);
	}

	public static function getControllerResponse(XenForo_ControllerPublic_Abstract $controller)
	{
		if (self::$_setup11x)
		{
			if ($controller instanceof XenForo_ControllerPublic_Index)
			{
				if (!self::$_setup11x_forumsWasHit)
				{
					return $controller->responseReroute('WidgetFramework_ControllerPublic_WidgetPage', 'as-index');
				}
			}
			elseif ($controller instanceof XenForo_ControllerPublic_Forum)
			{
				self::$_setup11x_forumsWasHit = true;
				return $controller->responseReroute('XenForo_ControllerPublic_Index', 'index');
			}
		}

		return false;
	}

	public static function setNavtabSelected(array &$tabs, array &$extraTabs)
	{
		$selected = false;

		if (!empty($tabs['forums']))
		{
			// found "Forums" navtab, select it now
			$tabs['forums']['selected'] = true;
			$selected = true;
		}
		else
		{
			// try to select the first one from $tabs
			foreach ($tabs as &$tab)
			{
				$tab['selected'] = true;
				$selected = true;
				break;
			}

			if (!$selected)
			{
				// still not selected!?
				// try with $extraTabs now
				foreach ($extraTabs as &$tabs)
				{
					$tab['selected'] = true;
					$selected = true;
					break 2;
				}
			}
		}

		return $selected;
	}

	public static function rebuildChildNodesCache()
	{
		$nodeModel = XenForo_Model::create('XenForo_Model_Node');
		$nodeId = WidgetFramework_Option::get('indexNodeId');
		$childNodes = array();

		if ($nodeId > 0)
		{
			$widgetPage = $nodeModel->getNodeById($nodeId);

			if (!empty($widgetPage))
			{
				$childNodes = $nodeModel->getChildNodes($widgetPage, true);

				XenForo_Application::setSimpleCacheData(self::SIMPLE_CACHE_CHILD_NODES, $childNodes);
			}
		}

		return $childNodes;
	}

	public static function getChildNodes()
	{
		$childNodes = XenForo_Application::getSimpleCacheData(self::SIMPLE_CACHE_CHILD_NODES);

		if ($childNodes === false)
		{
			return self::rebuildChildNodesCache();
		}

		return $childNodes;
	}

	protected static function _setupForXenForo1_2_x()
	{
		// ONLY ONE LINE TO CHANGE THE INDEX ROUTE FOR XENFORO 1.2.x
		// COMPARE TO THE SPAGHETTI CODE TO ACHIVE THE SAME THING
		// IN XENFORO 1.1.x, OH GOD WHY?
		XenForo_Link::setIndexRoute('widget-page-index/');
	}

	protected static function _setupForXenForo1_1_x()
	{
		self::$_setup11x = true;

		if (XenForo_Application::$versionId < 1020000)
		{
			// dirty trick to get the public routes
			$className = 'a' . md5(uniqid());
			eval(sprintf('class %s extends XenForo_Link{
				public static function getHandlerInfoForGroup($group){
				if (empty(XenForo_Link::$_handlerCache[$group]))return false;
				return XenForo_Link::$_handlerCache[$group];}}', $className));
			$routesPublic = call_user_func(array(
				$className,
				'getHandlerInfoForGroup'
			), 'public');
		}
		else
		{
			$routesPublic = XenForo_Link::getHandlerInfoForGroup('public');
		}

		if (!empty($routesPublic))
		{
			foreach ($routesPublic as $routePrefix => &$handlerInfo)
			{
				if ($routePrefix === 'index')
				{
					$handlerInfo['build_link'] = 'all';
				}
			}

			XenForo_Link::setHandlerInfoForGroup('public', $routesPublic);
		}
	}

}
