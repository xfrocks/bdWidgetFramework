<?php

class WidgetFramework_Helper_Sort
{
	public static function widgetGroups($a, $b)
	{
		return $a['display_order'] - $b['display_order'];
	}

}
