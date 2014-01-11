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
		{
			$row = max($row, $widget['options']['layout_row']);
		}
		if (isset($widget['options']['layout_col']))
		{
			$col = max($col, $widget['options']['layout_col']);
		}
		if (isset($widget['options']['layout_sizeRow']))
		{
			$sizeRow = max($sizeRow, $widget['options']['layout_sizeRow']);
		}
		if (isset($widget['options']['layout_sizeCol']))
		{
			$sizeCol = max($sizeCol, $widget['options']['layout_sizeCol']);
		}

		return call_user_func_array('sprintf', array(
			'width: %dpx; height: %dpx; top: %dpx; left: %dpx;',
			($width * $sizeCol) + (($sizeCol - 1) * $gap),
			($height * $sizeRow) + (($sizeRow - 1) * $gap),
			$row * ($height + $gap),
			$col * ($width + $gap),
		));
	}

	public static function generateCss(array $widgetPageOptions, $layoutTree)
	{
		if (!is_callable(array(
			$layoutTree,
			'getOption'
		)))
		{
			return '';
		}

		$cssClasses = $layoutTree->getOption('cssClasses');
		$css = array();
		$cssMedia = array();

		foreach ($cssClasses['cols'] as $cssClassCol)
		{
			if (!empty($cssClassCol['width']))
			{
				$css[] = sprintf('.%s { width: %dpx; }', $cssClassCol['name'], $cssClassCol['width']);
			}
			if (!empty($widgetPageOptions['column_gap']))
			{
				$css[] = sprintf('.%s > .margin { margin-right: %dpx; }', $cssClassCol['name'], $widgetPageOptions['column_gap']);
			}

			if (!empty($cssClassCol['width']))
			{
				$mediaStatement = sprintf('screen and (max-width: %dpx)', $cssClassCol['width']);

				$cssMedia[$mediaStatement][] = sprintf('.%s { width: 100%%; }', $cssClassCol['name']);
				$cssMedia[$mediaStatement][] = sprintf('.%s > .margin { margin-right: 0; }', $cssClassCol['name']);

				foreach ($cssClasses['xOfY'] as $cssClassXOfY)
				{
					if ($cssClassXOfY['cssClassLayoutCols'] == $cssClassCol['name'])
					{
						$mediaStatement = sprintf('screen and (min-width: %dpx)', $cssClassCol['width']);
						$cssMedia[$mediaStatement][] = sprintf('.%s > .%s { width: %d%%; }', $cssClassCol['name'], $cssClassXOfY['name'], $cssClassXOfY['widthPercent']);
					}
					elseif ($cssClassCol['width'] < $cssClassXOfY['cssClassLayoutColsWidth'])
					{
						$mediaStatement = sprintf('screen and (min-width: %dpx) and (max-width: %dpx)', $cssClassCol['width'], $cssClassXOfY['cssClassLayoutColsWidth']);
						$cssMedia[$mediaStatement][] = sprintf('.%s > .%s.%s { width: 100%%; }', $cssClassXOfY['cssClassLayoutCols'], $cssClassCol['name'], $cssClassXOfY['name']);
						$cssMedia[$mediaStatement][] = sprintf('.%s > .%s.%s > .margin { margin-right: 0; }', $cssClassXOfY['cssClassLayoutCols'], $cssClassCol['name'], $cssClassXOfY['name']);
					}
				}
			}
		}

		foreach ($cssClasses['rows'] as $cssClassRow)
		{
			if (!empty($widgetPageOptions['row_gap']))
			{
				$css[] = sprintf('.%s { margin-bottom: %dpx; }', $cssClassRow['name'], $widgetPageOptions['row_gap']);
			}
		}

		foreach ($cssMedia as $mediaStatement => $cssMediaRules)
		{
			$css[] = sprintf('@media %s { %s }', $mediaStatement, implode(" ", $cssMediaRules));
		}

		return implode("\n", $css);
	}

}
