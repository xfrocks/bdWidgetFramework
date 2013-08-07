<?php

class WidgetFramework_Extend_bdCache_Model_Cache extends XFCP_WidgetFramework_Extend_bdCache_Model_Cache
{

	public function isSupportedRoute($controllerName, $action)
	{
		$supported = parent::isSupportedRoute($controllerName, $action);

		if (!$supported)
		{
			$this->_normalizeControllerNameAndAction($controllerName, $action);

			if ($controllerName === 'widgetframework_controllerpublic_widgetpage' AND $action === 'index')
			{
				return true;
			}
		}

		return $supported;
	}

}
