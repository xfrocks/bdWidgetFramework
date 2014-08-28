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
			$result = $dob - $doa;
		}
		else
		{
			$result = $doa - $dob;
		}

		if ($result == 0)
		{
			$result = $a['widget_id'] - $b['widget_id'];
		}

		return $result;
	}

	public static function widgetGroupsAsc($a, $b)
	{
		$doa = $a['display_order'];
		$dob = $b['display_order'];

		$result = $doa - $dob;

		if ($result == 0)
		{
			$result = $a['widget_id'] - $b['widget_id'];
		}

		return $result;
	}

}
