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
		);
		$widgetIds = array();

		foreach ($widgets as $widget)
		{
			if (!isset($widget['options']['layout_row']))
				continue;
			if (!isset($widget['options']['layout_col']))
				continue;
			if (empty($widget['options']['layout_sizeRow']))
				continue;
			if (empty($widget['options']['layout_sizeCol']))
				continue;

			$rows = max($rows, $widget['options']['layout_row'] + $widget['options']['layout_sizeRow']);
			$cols = max($cols, $widget['options']['layout_col'] + $widget['options']['layout_sizeCol']);
			$widgetIds[] = $widget['widget_id'];
		}

		for ($col = 1; $col <= $cols; $col++)
		{
			$cssClasses['cols'][$col] = array('name' => sprintf('WF_Cols_%d_%d', $widgetPage['node_id'], $col), );

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
			'positionCode' => md5(serialize($widgets)),
			'rows' => $rows,
			'cols' => $cols,
			'cssClasses' => $cssClasses,
		), $options);

		return new _Layout_Vertical($view, $widgets, $options, $widgetIds);
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

	protected function _newSubLayout(XenForo_ViewPublic_Base $view, array &$widgets, array &$options, array $widgetIds, $depth)
	{
		return new _Layout_Horizontal($view, $widgets, $options, $widgetIds, $depth);
	}

	public function __toString()
	{
		$cssClasses = $this->getOption('cssClasses');

		switch (count($this->_subLayouts))
		{
			case 0:
				$html = '';
				break;
			case 1:
				$subLayout = strval(reset($this->_subLayouts));
				$hash = md5($subLayout);

				$html = sprintf('<!-- WidgetFramework_WidgetPage_LayoutVertical-%s -->', $hash);
				$html .= $subLayout;
				$html .= sprintf('<!-- /WidgetFramework_WidgetPage_LayoutVertical-%s -->', $hash);
				break;
			default:
				$html = '<ul class="WidgetFramework_WidgetPage_LayoutVertical">';

				$i = 0;
				foreach (array_keys($this->_subLayouts) as $layoutId)
				{
					$i++;
					$subLayout = &$this->_subLayouts[$layoutId];
					$columnsCount = count($this->_subLayoutIndeces[$layoutId]);

					$cssClassCols = $cssClasses['cols'][$columnsCount]['name'];
					$cssClassIsLast = ($i == count($this->_subLayouts) ? 'WidgetFramework_WidgetPage_LayoutColumnLast' : '');

					$html .= sprintf('<li class="WidgetFramework_WidgetPage_LayoutColumn %s %s">', $cssClassCols, $cssClassIsLast);
					$html .= $subLayout;
					$html .= '</li>';
				}

				$html .= '</ul>';
		}

		return $html;
	}

}

class _Layout_Horizontal extends _Layout_Multiple
{

	protected function _getFieldIndex()
	{
		return 'layout_row';
	}

	protected function _getFieldSize()
	{
		return 'layout_sizeRow';
	}

	protected function _newSubLayout(XenForo_ViewPublic_Base $view, array &$widgets, array &$options, array $widgetIds, $depth)
	{
		return new _Layout_Vertical($view, $widgets, $options, $widgetIds, $depth);
	}

	public function __toString()
	{
		$cssClasses = $this->getOption('cssClasses');

		switch (count($this->_subLayouts))
		{
			case 0:
				$html = '';
				break;
			case 1:
				$subLayout = strval(reset($this->_subLayouts));
				$hash = md5($subLayout);

				$html = sprintf('<!-- WidgetFramework_WidgetPage_LayoutHorizontal-%s -->', $hash);
				$html .= $subLayout;
				$html .= sprintf('<!-- /WidgetFramework_WidgetPage_LayoutHorizontal-%s -->', $hash);
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
	protected $_view = null;
	protected $_widgets = null;
	protected $_options = null;
	protected $_depth = null;

	protected $_subLayouts = array();
	protected $_subLayoutIndeces = array();

	public function __construct(XenForo_ViewPublic_Base $view, array &$widgets, array &$options, array $widgetIds, $depth = 0)
	{
		$this->_view = $view;
		$this->_widgets = &$widgets;
		$this->_options = &$options;
		$this->_depth = $depth;

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
				continue;

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
				$this->_subLayouts[$groupId] = new _Layout_Single($this->_view, $this->_widgets[$firstWidgetId], $this->_options);
				$this->_subLayoutIndeces[$groupId] = $indeces;
			}
			else
			{
				$this->_subLayouts[$groupId] = $this->_newSubLayout($this->_view, $this->_widgets, $this->_options, $subLayoutWidgetIds, $this->_depth + 1);
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
	abstract protected function _newSubLayout(XenForo_ViewPublic_Base $view, array &$widgets, array &$options, array $widgetIds, $depth);
}

class _Layout_Single
{

	protected $_view = null;
	protected $_widget = null;
	protected $_options = null;

	public function __construct(XenForo_ViewPublic_Base $view, array &$widget, array &$options)
	{
		$this->_view = $view;
		$this->_widget = &$widget;
		$this->_options = &$options;

		$this->_prepare();
	}

	protected function _prepare()
	{
		$renderer = WidgetFramework_Core::getRenderer($this->_widget['class'], false);
		if ($renderer)
		{
			$renderer->prepare($this->_widget, $this->_options['positionCode'], $this->_options['params'], $this->_options['templateObj']);
		}
	}

	public function __toString()
	{
		$renderer = WidgetFramework_Core::getRenderer($this->_widget['class'], false);
		if ($renderer)
		{
			$html = '';

			$params = $this->_options['params'];
			$params[WidgetFramework_WidgetRenderer::PARAM_POSITION_CODE] = 'hook:' . $this->_options['positionCode'] . '_' . md5(serialize($this->_widget));
			$params[WidgetFramework_WidgetRenderer::PARAM_IS_HOOK] = true;
			$params[WidgetFramework_WidgetRenderer::PARAM_PARENT_TEMPLATE] = $this->_options['positionCode'];
			$params[WidgetFramework_WidgetRenderer::PARAM_VIEW_OBJECT] = $this->_view;
			
			$widgetHtml = $renderer->render($this->_widget, $this->_options['positionCode'], $params, $this->_options['templateObj'], $html);

			$renderer->extraPrepare($this->_widget, $widgetHtml);
		}
		else
		{
			$widgetHtml = '';
		}

		return $widgetHtml;
	}

}
