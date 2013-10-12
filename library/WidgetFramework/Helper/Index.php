<?php

class WidgetFramework_Helper_Index
{
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
