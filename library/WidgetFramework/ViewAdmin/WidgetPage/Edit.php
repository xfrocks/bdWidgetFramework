<?php

class WidgetFramework_ViewAdmin_WidgetPage_Edit extends XenForo_ViewAdmin_Base
{
	public function renderHtml()
	{
		foreach ($this->_params['widgets'] as &$widget)
		{
			if (!empty($widget['renderer']))
			{
				$widget['title'] = $widget['renderer']->extraPrepareTitle($widget);
			}
		}
	}

}
