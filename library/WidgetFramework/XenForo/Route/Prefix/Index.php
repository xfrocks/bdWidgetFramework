<?php

class WidgetFramework_XenForo_Route_Prefix_Index extends XFCP_WidgetFramework_XenForo_Route_Prefix_Index
{
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return WidgetFramework_Helper_Index::buildBasicLink($outputPrefix, $action, $extension);
	}

}
