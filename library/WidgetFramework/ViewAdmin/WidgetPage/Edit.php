<?php

class WidgetFramework_ViewAdmin_WidgetPage_Edit extends XenForo_ViewAdmin_Base
{
	public function renderHtml()
	{
		$rows = 0;
		$cols = 0;

		foreach ($this->_params['widgets'] as &$widget)
		{
			if (isset($widget['options']['layout_row']) AND isset($widget['options']['layout_sizeRow']))
			{
				$rows = max($rows, $widget['options']['layout_row'] + $widget['options']['layout_sizeRow']);
			}

			if (isset($widget['options']['layout_col']) AND isset($widget['options']['layout_sizeCol']))
			{
				$cols = max($cols, $widget['options']['layout_col'] + $widget['options']['layout_sizeCol']);
			}

			if (!empty($widget['renderer']))
			{
				$widget['title'] = $widget['renderer']->extraPrepareTitle($widget);
			}
		}

		$sidebarWidgetContainer = array('options' => array(
				'layout_row' => 0,
				'layout_col' => $cols,
				'layout_sizeRow' => $rows,
				'layout_sizeCol' => 1,
			));
		$this->_params['sidebarWidgetContainer'] = $sidebarWidgetContainer;
	}

}
