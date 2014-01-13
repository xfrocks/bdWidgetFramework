<?php

class WidgetFramework_ViewPublic_Helper_Layout
{

	public static function buildLayoutTree(XenForo_ViewPublic_Base $view, array &$widgets, array $options = array())
	{
		$widgetPage = &$options['widgetPage'];
		$rows = 0;
		$cols = 0;
		$cssClasses = array(
			'cols' => array(),
			'rows' => array(),
			'xOfY' => array(),
		);
		$widgetIds = array();

		foreach ($widgets as $widget)
		{
			if (empty($widget['active']))
			{
				continue;
			}
			if (!empty($widget['position']))
			{
				continue;
			}
			if (!isset($widget['options']['layout_row']))
			{
				continue;
			}
			if (!isset($widget['options']['layout_col']))
			{
				continue;
			}
			if (empty($widget['options']['layout_sizeRow']))
			{
				continue;
			}
			if (empty($widget['options']['layout_sizeCol']))
			{
				continue;
			}

			$rows = max($rows, $widget['options']['layout_row'] + $widget['options']['layout_sizeRow']);
			$cols = max($cols, $widget['options']['layout_col'] + $widget['options']['layout_sizeCol']);
			$widgetIds[] = $widget['widget_id'];
		}

		for ($col = 1; $col <= $cols; $col++)
		{
			$cssClasses['cols'][$col] = array('name' => sprintf('WF_Cols_%d_%d', $widgetPage['node_id'], $col));

			if (!empty($widgetPage['options']['column_width']) AND !empty($widgetPage['options']['column_gap']))
			{
				$cssClasses['cols'][$col]['width'] = ($col * $widgetPage['options']['column_width']) + (($col - 1) * $widgetPage['options']['column_gap']);
			}
		}

		for ($row = 1; $row <= $rows; $row++)
		{
			$cssClasses['rows'][$row] = array('name' => sprintf('WF_Rows_%d_%d', $widgetPage['node_id'], $row), );
		}

		$options = XenForo_Application::mapMerge(array(
			'params' => $view->getParams(),
			'templateObj' => $view->createOwnTemplateObject(),
			'positionCode' => md5(implode('_', $widgetIds)),
			'rows' => $rows,
			'cols' => $cols,
			'cssClasses' => $cssClasses,
			'layoutCount' => 0,
		), $options);

		$layout = new _Layout_Vertical($view, $widgets, $options, $widgetIds);

		$layout->prepare();
		$layout->render();

		return $layout;
	}

	public static function prepareSidebarWidgets(XenForo_ViewPublic_Base $view, array &$widgets, array $options = array())
	{
		$sidebarWidgets = array();

		foreach ($widgets as $widget)
		{
			if (empty($widget['active']))
			{
				continue;
			}

			if ($widget['position'] === 'sidebar')
			{
				$widget['position'] = 'wf_widget_page_index';
				$sidebarWidgets[] = $widget;
			}
		}

		WidgetFramework_Core::getInstance()->addWidgets($sidebarWidgets);
	}

}

class _Layout_Vertical extends _Layout_Multiple
{
	protected function _getFieldIndex()
	{
		return 'layout_col';
	}

	protected function _getFieldSize()
	{
		return 'layout_sizeCol';
	}

	protected function _newSingleLayout(array &$widget)
	{
		return new _Layout_Single($this->_view, $widget, $this->_options);
	}

	protected function _newSubLayout(array &$widgets, array $widgetIds, $depth)
	{
		return new _Layout_Horizontal($this->_view, $widgets, $this->_options, $widgetIds, $depth);
	}

	public function __toString()
	{
		$cssClasses = &$this->_options['cssClasses'];

		switch (count($this->_subLayouts))
		{
			case 0:
				$html = '';
				break;
			case 1:
				$subLayout = strval(reset($this->_subLayouts));

				$html = sprintf('<!-- WidgetFramework_WidgetPage_LayoutVertical-%s -->', $this->_getHash());
				$html .= $subLayout;
				$html .= sprintf('<!-- /WidgetFramework_WidgetPage_LayoutVertical-%s -->', $this->_getHash());
				break;
			default:
				$totalColumnsCount = 0;
				foreach (array_keys($this->_subLayouts) as $layoutId)
				{
					$totalColumnsCount += count($this->_subLayoutIndeces[$layoutId]);
				}
				$cssClassLayoutCols = $cssClasses['cols'][$totalColumnsCount]['name'];

				$html = sprintf('<ul class="WidgetFramework_WidgetPage_LayoutVertical %s">', $cssClassLayoutCols);

				$i = 0;
				foreach (array_keys($this->_subLayouts) as $layoutId)
				{
					$i++;
					$subLayout = &$this->_subLayouts[$layoutId];
					$columnsCount = count($this->_subLayoutIndeces[$layoutId]);

					$cssClassCols = $cssClasses['cols'][$columnsCount]['name'];
					$cssClassIsLast = ($i == count($this->_subLayouts) ? ' isLast' : '');

					$columnWidthPercent = round($columnsCount / $totalColumnsCount * 100, 2);
					$cssClassXOfY = sprintf('_%dOf%d', preg_replace('/[^0-9]/', '', $columnWidthPercent), $totalColumnsCount);
					if (!isset($cssClasses['xOfY'][$cssClassXOfY]))
					{
						$cssClasses['xOfY'][$cssClassXOfY] = array(
							'name' => $cssClassXOfY,
							'cssClassLayoutCols' => $cssClassLayoutCols,
							'cssClassLayoutColsWidth' => $cssClasses['cols'][$totalColumnsCount]['width'],
							'widthPercent' => $columnWidthPercent
						);
					}

					$html .= call_user_func_array('sprintf', array(
						'<li class="WidgetFramework_WidgetPage_LayoutColumn %s %s%s"><div class="margin">',
						$cssClassCols,
						$cssClassXOfY,
						$cssClassIsLast,
					));
					$html .= $subLayout;
					$html .= '</div></li>';
				}

				$html .= '</ul>';
		}

		return $html;
	}

}

class _Layout_Horizontal extends _Layout_Multiple
{
	protected function _doLayout(array $widgetIds)
	{
		parent::_doLayout($widgetIds);

		$groupIds = array_keys($this->_subLayouts);

		$mergedGroupCount = 0;
		$mergeableGroupIds = array();

		foreach ($groupIds as $groupId)
		{
			if (is_array($this->_subLayouts[$groupId]))
			{
				if (empty($mergeableGroupIds[$mergedGroupCount]))
				{
					$mergeableGroupIds[$mergedGroupCount] = array($groupId);
				}
				else
				{
					$mergeableGroupIds[$mergedGroupCount][] = $groupId;
				}
			}
			else
			{
				$mergedGroupCount++;
			}
		}

		for ($mergedGroupId = 0; $mergedGroupId < ($mergedGroupCount + 1); $mergedGroupId++)
		{
			if (empty($mergeableGroupIds[$mergedGroupId]))
			{
				continue;
			}

			$firstMergeableGroupId = reset($mergeableGroupIds[$mergedGroupId]);
			$widgets = array();
			$indeces = array();
			foreach ($mergeableGroupIds[$mergedGroupId] as $mergeableGroupId)
			{
				$widgets[] = $this->_subLayouts[$mergeableGroupId]['widget'];

				foreach ($this->_subLayoutIndeces[$mergeableGroupId] as $index)
				{
					$indeces[] = $index;
				}

				if ($mergeableGroupId != $firstMergeableGroupId)
				{
					unset($this->_subLayouts[$mergeableGroupId]);
					unset($this->_subLayoutIndeces[$mergeableGroupId]);
				}
			}

			$options = $this->_options;
			$options['singleHookName'] = sprintf('%s_%s', $this->_getHash(), implode('_', $indeces));

			foreach ($widgets as $widget)
			{
				$this->_subLayouts[$firstMergeableGroupId] = new _Layout_Single($this->_view, $widget, $options);
			}

			$this->_subLayoutIndeces[$firstMergeableGroupId] = $indeces;
		}
	}

	protected function _getFieldIndex()
	{
		return 'layout_row';
	}

	protected function _getFieldSize()
	{
		return 'layout_sizeRow';
	}

	protected function _newSingleLayout(array &$widget)
	{
		return array('widget' => $widget);
	}

	protected function _newSubLayout(array &$widgets, array $widgetIds, $depth)
	{
		return new _Layout_Vertical($this->_view, $widgets, $this->_options, $widgetIds, $depth);
	}

	public function __toString()
	{
		$cssClasses = &$this->_options['cssClasses'];

		switch (count($this->_subLayouts))
		{
			case 0:
				$html = '';
				break;
			case 1:
				$subLayout = strval(reset($this->_subLayouts));

				$html = sprintf('<!-- WidgetFramework_WidgetPage_LayoutHorizontal-%s -->', $this->_getHash());
				$html .= $subLayout;
				$html .= sprintf('<!-- /WidgetFramework_WidgetPage_LayoutHorizontal-%s -->', $this->_getHash());
				break;
			default:
				$html = '<ul class="WidgetFramework_WidgetPage_LayoutHorizontal">';

				foreach (array_keys($this->_subLayouts) as $layoutId)
				{
					$subLayout = strval($this->_subLayouts[$layoutId]);
					$rowsCount = count($this->_subLayoutIndeces[$layoutId]);

					$cssClassRows = $cssClasses['rows'][$rowsCount]['name'];
					$cssClassIsLast = ($i == count($this->_subLayouts) ? 'WidgetFramework_WidgetPage_LayoutRowLast' : '');

					$html .= sprintf('<li class="WidgetFramework_WidgetPage_LayoutRow %s %s">', $cssClassRows, $cssClassIsLast);
					$html .= $subLayout;
					$html .= '</li>';
				}

				$html .= '</ul>';
		}

		return $html;
	}

}

abstract class _Layout_Multiple
{
	protected $_view;
	protected $_widgets;
	protected $_options;
	protected $_widgetIds;
	protected $_depth;

	protected $_subLayouts = array();
	protected $_subLayoutIndeces = array();

	public function __construct(XenForo_ViewPublic_Base $view, array &$widgets, array &$options, array $widgetIds, $depth = 0)
	{
		$this->_view = $view;
		$this->_widgets = &$widgets;
		$this->_options = &$options;
		$this->_widgetIds = $widgetIds;
		$this->_depth = $depth;

		$this->_options['layoutCount']++;

		if ($depth < 10)
		{
			$this->_doLayout($widgetIds);
		}
	}

	public function getOption($key)
	{
		if (isset($this->_options[$key]))
		{
			return $this->_options[$key];
		}

		return null;
	}

	public function prepare()
	{
		foreach ($this->_subLayouts as $subLayout)
		{
			$subLayout->prepare();
		}
	}

	public function render()
	{
		foreach ($this->_subLayouts as $subLayout)
		{
			$subLayout->render();
		}
	}

	protected function _getHash()
	{
		return md5(implode('_', $this->_widgetIds));
	}

	protected function _doLayout(array $widgetIds)
	{
		$groups = array();
		$mapping = array();

		$fieldIndex = $this->_getFieldIndex();
		$fieldSize = $this->_getFieldSize();

		foreach ($widgetIds as $widgetId)
		{
			$widgetRef = &$this->_widgets[$widgetId];

			$this->_splitGroups($groups, $mapping, $widgetRef['options'][$fieldIndex], $widgetRef['options'][$fieldIndex] + $widgetRef['options'][$fieldSize] - 1);
		}

		$groupIdsOrdered = array();
		ksort($mapping);
		foreach ($mapping as $index => $groupId)
		{
			if (!in_array($groupId, $groupIdsOrdered))
			{
				$groupIdsOrdered[] = $groupId;
			}
		}

		foreach ($groupIdsOrdered as $groupId)
		{
			$indeces = $groups[$groupId];
			if (empty($indeces))
			{
				continue;
			}

			$subLayoutWidgetIds = array();

			foreach ($widgetIds as $widgetId)
			{
				$widgetRef = &$this->_widgets[$widgetId];

				if (in_array($widgetRef['options'][$fieldIndex], $indeces))
				{
					$subLayoutWidgetIds[] = $widgetId;
				}
			}

			if (empty($subLayoutWidgetIds))
			{
				// really?
				continue;
			}
			elseif (count($subLayoutWidgetIds) == 1)
			{
				$firstWidgetId = reset($subLayoutWidgetIds);
				$this->_subLayouts[$groupId] = $this->_newSingleLayout($this->_widgets[$firstWidgetId]);
				$this->_subLayoutIndeces[$groupId] = $indeces;
			}
			else
			{
				$this->_subLayouts[$groupId] = $this->_newSubLayout($this->_widgets, $subLayoutWidgetIds, $this->_depth + 1);
				$this->_subLayoutIndeces[$groupId] = $indeces;
			}
		}
	}

	protected function _splitGroups(array &$groups, array &$mapping, $x0, $x1)
	{
		$groupId = false;
		for ($x = $x0; $x <= $x1; $x++)
		{
			if ($groupId === false)
			{
				// first col
				if (!isset($mapping[$x]))
				{
					// new col
					$groups[] = array($x);
					$mapping[$x] = count($groups) - 1;
				}
				$groupId = $mapping[$x];
			}
			else
			{
				// second col and beyond
				if (!isset($mapping[$x]))
				{
					// no group yet, great
					$groups[$groupId][] = $x;
					$mapping[$x] = $groupId;
				}
				elseif ($mapping[$x] != $groupId)
				{
					// merge group...
					$_groupId = $mapping[$x];
					foreach ($groups[$_groupId] as $_x)
					{
						$groups[$groupId][] = $_x;
						$mapping[$_x] = $groupId;
					}
					$groups[$_groupId] = array();
					// empty the other group
				}
			}
		}
	}

	abstract protected function _getFieldIndex();
	abstract protected function _getFieldSize();
	abstract protected function _newSingleLayout(array &$widget);
	abstract protected function _newSubLayout(array &$widgets, array $widgetIds, $depth);
}

class _Layout_Single
{
	protected $_hookName;
	protected $_options;

	protected $_prepared = false;
	protected $_rendered = false;

	public function __construct(XenForo_ViewPublic_Base $view, array &$widget, array &$options)
	{
		if (!empty($options['singleHookName']))
		{
			$this->_hookName = $options['singleHookName'];
		}
		else
		{
			$this->_hookName = sprintf('%s_%d', $options['positionCode'], $widget['widget_id']);
		}

		$this->_options = &$options;
		$this->_options['layoutCount']++;

		$widgets = array($widget);
		$widgets[0]['position'] = 'hook:' . $this->_hookName;

		WidgetFramework_Core::getInstance()->addWidgets($widgets);
	}

	public function prepare()
	{
		if ($this->_prepared !== false)
		{
			// already prepared
			return false;
		}

		WidgetFramework_Core::getInstance()->prepareWidgetsForHook($this->_hookName, $this->_options['params'], $this->_options['templateObj']);

		$this->_prepared = true;

		return true;
	}

	public function render()
	{
		$this->prepare();

		if ($this->_rendered !== false)
		{
			// already rendered
			return false;
		}

		$this->_rendered = '';

		WidgetFramework_Core::getInstance()->renderWidgetsForHook($this->_hookName, $this->_options['params'], $this->_options['templateObj'], $this->_rendered);

		return true;
	}

	public function __toString()
	{
		$this->render();

		return strval($this->_rendered);
	}

}
