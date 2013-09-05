<?php

class WidgetFramework_XenForo_View1 extends XFCP_WidgetFramework_XenForo_View1
{
	public function getParams()
	{
		$params = parent::getParams();

		$params[WidgetFramework_WidgetRenderer::PARAM_VIEW_OBJECT] = $this;

		return $params;
	}

}
