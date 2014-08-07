<?php

class WidgetFramework_XenForo_ViewAdmin_StyleProperty_List extends XFCP_WidgetFramework_XenForo_ViewAdmin_StyleProperty_List
{
	public function renderHtml()
	{
		parent::renderHtml();

		if (!empty($this->_params['group']['group_name']) AND $this->_params['group']['group_name'] === 'widget_framework')
		{
			ksort($this->_params['scalars']);
		}
	}

}
