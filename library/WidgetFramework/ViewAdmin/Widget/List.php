<?php

class WidgetFramework_ViewAdmin_Widget_List extends XenForo_ViewAdmin_Base
{
	public function renderHtml()
	{
		$positions = array();

		foreach ($this->_params['widgets'] as &$widget)
		{
			$widgetPositions = explode(',', $widget['position']);

			foreach ($widgetPositions as $position)
			{
				$position = trim($position);
				if (empty($position))
					continue;

				if (!isset($positions[$position]))
				{
					$positions[$position] = array(
						'position' => $position,
						'widgets' => array(),
					);
				}

				if (!empty($widget['options']['tab_group']))
				{
					if (!isset($positions[$position]['widgets'][$widget['options']['tab_group']]))
					{
						$positions[$position]['widgets'][$widget['options']['tab_group']] = array(
							'tabGroup' => $widget['options']['tab_group'],
							'widgets' => array(),
						);
					}
					$positions[$position]['widgets'][$widget['options']['tab_group']]['widgets'][] = &$widget;
				}
				else
				{
					$positions[$position]['widgets'][] = &$widget;
				}
			}
			
			if (!empty($widget['renderer']))
			{
				$widget['title'] = strip_tags($widget['renderer']->extraPrepareTitle($widget));
			}
		}

		usort($positions, array(
			$this,
			'sort'
		));

		$this->_params['positions'] = $positions;
	}

	protected function sort($a, $b)
	{
		$aIsHook = (substr($a['position'], 0, 5) == 'hook:');
		$bIsHook = (substr($b['position'], 0, 5) == 'hook:');

		if ($aIsHook AND !$bIsHook)
		{
			return -1;
		}
		elseif (!$aIsHook AND $bIsHook)
		{
			return 1;
		}
		else
		{
			return strcmp($a['position'], $b['position']);
		}
	}

}
