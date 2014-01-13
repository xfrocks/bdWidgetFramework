<?php

class WidgetFramework_XenForo_ViewAdmin_StyleProperty_List extends XFCP_WidgetFramework_XenForo_ViewAdmin_StyleProperty_List
{
	public function renderHtml()
	{
		parent::renderHtml();

		ksort($this->_params['scalars']);
	}

}
