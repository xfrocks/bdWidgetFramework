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

	public static function generateCss(array $widgetPageOptions, array $widgets, $layoutTree)
	{
		if (!is_callable(array(
			$layoutTree,
			'getOption'
		)))
		{
			// layout tree is not a layout object? What?!
			return '';
		}

		$cssClasses = $layoutTree->getOption('cssClasses');
		$css = array();
		$cssMedia = array();

		$hasSidebar = false;
		foreach ($widgets as $widget)
		{
			if ($widget['position'] === 'sidebar')
			{
				$hasSidebar = true;
			}
		}

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
				self::generateCssMedia(0, $cssClassCol['width'], $hasSidebar, $cssMedia, array(
					sprintf('.%s { width: 100%%; }', $cssClassCol['name']),
					sprintf('.%s > .margin { margin-right: 0; }', $cssClassCol['name']),
				));

				foreach ($cssClasses['xOfY'] as $cssClassXOfY)
				{
					if ($cssClassXOfY['cssClassLayoutCols'] == $cssClassCol['name'])
					{
						self::generateCssMedia($cssClassCol['width'], 0, $hasSidebar, $cssMedia, array(sprintf('.%s > .%s { width: %f%%; }', $cssClassCol['name'], $cssClassXOfY['name'], $cssClassXOfY['widthPercent'])));
					}
					elseif ($cssClassCol['width'] < $cssClassXOfY['cssClassLayoutColsWidth'])
					{
						self::generateCssMedia($cssClassCol['width'], $cssClassXOfY['cssClassLayoutColsWidth'], $hasSidebar, $cssMedia, array(
							sprintf('.%s > .%s.%s { width: 100%%; }', $cssClassXOfY['cssClassLayoutCols'], $cssClassCol['name'], $cssClassXOfY['name']),
							sprintf('.%s > .%s.%s > .margin { margin-right: 0; }', $cssClassXOfY['cssClassLayoutCols'], $cssClassCol['name'], $cssClassXOfY['name']),
						));
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

		if (!empty($widgetPageOptions['break_container']))
		{
			$css[] = XenForo_CssOutput::translateCssRules('.mainContent { background: transparent; border: 0; border-radius: 0; box-shadow: none; padding: 0; }');

			$sectionMainMarginTop = XenForo_Template_Helper_Core::styleProperty('sectionMain.margin-top');
			if (!empty($sectionMainMarginTop))
			{
				$css[] = sprintf('#WidgetPageContent { margin-top: -%s; }', $sectionMainMarginTop);
			}
		}

		foreach ($cssMedia as $mediaStatement => $cssMediaRules)
		{
			$css[] = sprintf("@media %s\n{\n%s\n}", $mediaStatement, implode("\n", $cssMediaRules));
		}

		asort($css);

		return implode("\n", $css);
	}

	public static function generateCssMedia($minWidth, $maxWidth, $hasSidebar, array &$cssMedia, array $rules)
	{
		$mediaStatements = array();

		if ($minWidth === 0)
		{
			$maxMediaWidths = self::getMediaWidths($maxWidth, $hasSidebar);

			foreach ($maxMediaWidths as $maxMediaWidth)
			{
				if (is_array($maxMediaWidth))
				{
					if ($maxMediaWidth[2])
					{
						// has sidebar
						$mediaStatements[] = sprintf('screen and (min-width: %dpx) and (max-width: %dpx)', $maxMediaWidth[0], $maxMediaWidth[1]);
					}
					else
					{
						$mediaStatements[] = sprintf('screen and (max-width: %dpx)', $maxMediaWidth[1]);
					}
				}
				else
				{
					$mediaStatements[] = sprintf('screen and (max-width: %dpx)', $maxMediaWidth);
				}
			}
		}
		elseif ($maxWidth === 0)
		{
			$minMediaWidths = self::getMediaWidths($minWidth, $hasSidebar);

			foreach ($minMediaWidths as $minMediaWidth)
			{
				if (is_array($minMediaWidth))
				{
					if ($minMediaWidth[2])
					{
						// has sidebar
						$mediaStatements[] = sprintf('screen and (min-width: %dpx)', $minMediaWidth[1]);
					}
					else
					{
						$mediaStatements[] = sprintf('screen and (min-width: %dpx) and (max-width: %dpx)', $minMediaWidth[0], $minMediaWidth[1]);
					}
				}
				else
				{
					$mediaStatements[] = sprintf('screen and (min-width: %dpx)', $minMediaWidth);
				}
			}
		}
		else
		{
			$minMediaWidths = self::getMediaWidths($minWidth, $hasSidebar);
			$maxMediaWidths = self::getMediaWidths($maxWidth, $hasSidebar);
			$ranges = array();

			foreach ($minMediaWidths as $minMediaWidth)
			{
				foreach ($maxMediaWidths as $maxMediaWidth)
				{
					if (is_array($minMediaWidth) AND is_array($maxMediaWidth) AND $minMediaWidth[2] != $maxMediaWidth[2])
					{
						// split into two ranges because sidebar appearance does not match
						$ranges[] = array(
							$minMediaWidth[0],
							$minMediaWidth[1]
						);
						$ranges[] = array(
							$maxMediaWidth[0],
							$maxMediaWidth[1]
						);
						continue;
					}

					if (is_array($minMediaWidth))
					{
						if ($minMediaWidth[2])
						{
							// has sidebar
							$min = $minMediaWidth[1];
						}
						else
						{
							// no sidebar
							$min = $minMediaWidth[0];
						}
					}
					else
					{
						$min = $minMediaWidth;
					}

					if (is_array($maxMediaWidth))
					{
						if ($maxMediaWidth[2])
						{
							// has sidebar
							$max = $maxMediaWidth[1];
						}
						else
						{
							// no sidebar
							$max = $maxMediaWidth[0];
						}
					}
					else
					{
						$max = $maxMediaWidth;
					}

					$ranges[] = array(
						$min,
						$max
					);
				}
			}

			foreach ($ranges as $range)
			{
				if ($range[0] > 0)
				{
					$mediaStatements[] = sprintf('screen and (min-width: %dpx) and (max-width: %dpx)', $range[0], $range[1]);
				}
				else
				{
					$mediaStatements[] = sprintf('screen and (max-width: %dpx)', $range[1]);
				}
			}
		}

		foreach ($mediaStatements as $mediaStatement)
		{
			foreach ($rules as $rule)
			{
				$cssMedia[$mediaStatement][] = $rule;
			}
		}
	}

	public static function getMediaWidths($width, $hasSidebar)
	{
		static $results = array();

		$hash = md5(sprintf('%d-%d', $width, $hasSidebar ? 1 : 0));
		if (isset($results[$hash]))
		{
			// try to return cached results
			return $results[$hash];
		}
		$results[$hash] = array();
		$mediaWidths = &$results[$hash];

		static $styleProperties = null;
		if ($styleProperties === null)
		{
			// do this only once because
			// XenForo_Template_Helper_Core::styleProperty is super complicated
			$styleProperties = array();

			$styleProperties['sidebarWidth'] = intval(XenForo_Template_Helper_Core::styleProperty('sidebar.width'));
			$styleProperties['pageWidth'] = intval(XenForo_Template_Helper_Core::styleProperty('pageWidth.width'));
			$styleProperties['enableResponsive'] = !!XenForo_Template_Helper_Core::styleProperty('enableResponsive');
			$styleProperties['maxResponsiveWideWidth'] = intval(XenForo_Template_Helper_Core::styleProperty('maxResponsiveWideWidth'));

			$widthDelta = 0;
			$widthDelta += intval(XenForo_Template_Helper_Core::styleProperty('pageWidth.padding-left'));
			$widthDelta += intval(XenForo_Template_Helper_Core::styleProperty('pageWidth.padding-right'));
			$widthDelta += intval(XenForo_Template_Helper_Core::styleProperty('content.padding-left'));
			$widthDelta += intval(XenForo_Template_Helper_Core::styleProperty('content.padding-right'));

			$styleProperties['widthDelta'] = $widthDelta;
		}

		if (empty($styleProperties['enableResponsive']))
		{
			// not responsive, do nothing
			return $mediaWidths;
		}

		if (!empty($styleProperties['pageWidth']))
		{
			// fixed width, do nothing
			return $mediaWidths;
		}

		$width += $styleProperties['widthDelta'];

		if (!$hasSidebar)
		{
			$mediaWidths[] = $width;
		}
		else
		{
			if ($styleProperties['maxResponsiveWideWidth'] > $width)
			{
				// $width is small enough, sidebar is going to be hidden
				$mediaWidths[] = array(
					$width,
					$styleProperties['maxResponsiveWideWidth'],

					// false means sidebar is hidden
					false,
				);
			}

			$tmpWidth = $width + $styleProperties['sidebarWidth'];
			if ($tmpWidth > $styleProperties['maxResponsiveWideWidth'])
			{
				// $tmpWidth is large enough, sidebar is shown and left enough space for content
				$mediaWidths[] = array(
					$styleProperties['maxResponsiveWideWidth'],
					$tmpWidth,

					// true means sidebar is shown
					true,
				);
			}
		}

		return $mediaWidths;
	}

}
