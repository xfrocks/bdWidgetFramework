<?php

class WidgetFramework_XenForo_ControllerPublic_Index extends XFCP_WidgetFramework_XenForo_ControllerPublic_Index
{
	public function actionIndex()
	{
		$response = WidgetFramework_Helper_Index::getControllerResponse($this);
		if (!empty($response))
		{
			return $response;
		}

		return parent::actionIndex();
	}

}
