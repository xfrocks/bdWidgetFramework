<?php

class WidgetFramework_Helper_LayoutEditor
{
	public static function getChangedRenderedId(WidgetFramework_DataWriter_Widget $dw, array $changed = array())
	{
		if ($dw->isChanged('position') OR $dw->isChanged('display_order') OR $dw->isChanged('options'))
		{
			$changed[] = $dw->get('widget_id');

			$existingPosition = $dw->getExisting('position');
			$existingOptions = @unserialize($dw->getExisting('options'));
			$existingGroup = '';
			if (!empty($existingOptions['tab_group']))
			{
				$existingGroup .= $existingOptions['tab_group'];
			}

			$newPosition = $dw->get('position');
			$newOptions = @unserialize($dw->get('options'));
			$newGroup = '';
			if (!empty($newOptions['tab_group']))
			{
				$newGroup .= $newOptions['tab_group'];
			}

			if ($existingPosition !== $newPosition OR $existingGroup !== $newGroup)
			{
				if (!empty($existingGroup))
				{
					$changed[] = $existingGroup;
				}
				else
				{
					$changed[] = 'group-' . $dw->get('widget_id');
				}

				if (!empty($newGroup))
				{
					$changed[] = $newGroup;
				}
			}
		}
		elseif ($dw->isDelete())
		{
			$changed[] = $dw->get('widget_id');
		}

		return $changed;
	}

	public static function generateWidgetGroupCss(array &$containerData, $count)
	{
		$head = array();

		for ($i = $count - 1; $i <= $count + 1; $i++)
		{
			if ($i <= 0)
			{
				continue;
			}

			$containerDataHeadKey = __METHOD__ . $i;
			if (isset($containerData['head'][$containerDataHeadKey]))
			{
				continue;
			}

			$widgetGroupCssClass = sprintf('widget-group-%d-widgets', $i);
			$sidebarWidth = intval(XenForo_Template_Helper_Core::styleProperty('sidebar.width'));

			$widgetMinWidth = 100 / $i;
			$widgetWidth = $sidebarWidth - 50;
			$controlsMinWidth = 100 / ($i * 2 + 1);
			$controlsWidth = $widgetWidth / 2;
			$widgetsMinWidth = 100 - $controlsMinWidth;
			$widgetsWidth = $i * $widgetWidth;
			$stretcherWidth = $widgetsWidth + $controlsWidth;

			$head[$containerDataHeadKey] = <<<EOF
<style>
.{$widgetGroupCssClass} > .stretcher
{
	min-width: 100%;
	width: {$stretcherWidth}px;
}

.{$widgetGroupCssClass} > .stretcher > .widgets
{
	min-width: {$widgetsMinWidth}%;
	width: {$widgetsWidth}px;
}
	.{$widgetGroupCssClass} > .stretcher > .widgets > div
	{
		min-width: {$widgetMinWidth}%;
		width: {$widgetWidth}px;
	}

.{$widgetGroupCssClass} > .stretcher > .controls
{
	min-width: {$controlsMinWidth}%;
	width: {$controlsWidth}px;
}
</style>
EOF;
		}

		if (!empty($head))
		{
			return array('head' => $head);
		}
	}

}
