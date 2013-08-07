<?php

class WidgetFramework_Template_Helper_Layout
{

	public static function getContainerSize(array $widgets, $width = 200, $height = 200, $gap = 10)
	{
		$maxRow = 0;
		$maxCol = 0;

		foreach ($widgets as $widget)
		{
			if (isset($widget['options']['layout_row']) AND isset($widget['options']['layout_sizeRow']))
			{
				$maxRow = max($maxRow, $widget['options']['layout_row'] + $widget['options']['layout_sizeRow']);
			}

			if (isset($widget['options']['layout_col']) AND isset($widget['options']['layout_sizeCol']))
			{
				$maxCol = max($maxCol, $widget['options']['layout_col'] + $widget['options']['layout_sizeCol']);
			}
		}

		return sprintf('width: %dpx; height: %dpx;', $maxCol * ($width + $gap) - $gap, $maxRow * ($height + $gap) - $gap);
	}

	public static function getWidgetPositionAndSize(array $widget, $width = 200, $height = 200, $gap = 10)
	{
		$row = 0;
		$col = 0;
		$sizeRow = 1;
		$sizeCol = 1;

		if (isset($widget['options']['layout_row']))
			$row = max($row, $widget['options']['layout_row']);
		if (isset($widget['options']['layout_col']))
			$col = max($col, $widget['options']['layout_col']);
		if (isset($widget['options']['layout_sizeRow']))
			$sizeRow = max($sizeRow, $widget['options']['layout_sizeRow']);
		if (isset($widget['options']['layout_sizeCol']))
			$sizeCol = max($sizeCol, $widget['options']['layout_sizeCol']);

		return sprintf('width: %dpx; height: %dpx; top: %dpx; left: %dpx;', ($width * $sizeCol) + (($sizeCol - 1) * $gap), ($height * $sizeRow) + (($sizeRow - 1) * $gap), $row * ($height + $gap), $col * ($width + $gap));
	}

}
