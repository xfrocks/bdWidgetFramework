<?php

class WidgetFramework_XenForo_View2 extends XFCP_WidgetFramework_XenForo_View2
{
	public function getParams()
	{
		$params = parent::getParams();

		$params[WidgetFramework_WidgetRenderer::PARAM_VIEW_OBJECT] = $this;

		return $params;
	}

}
