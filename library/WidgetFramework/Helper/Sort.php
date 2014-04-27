<?php

class WidgetFramework_Helper_Sort
{
	public static function widgetGroups($a, $b)
	{
		$doa = $a['display_order'];
		$dob = $b['display_order'];

		if ($doa < 0 AND $doa < 0)
		{
			// both are negative display order
			return $dob - $doa;
		}
		else
		{
			return $doa - $dob;
		}
	}

}
