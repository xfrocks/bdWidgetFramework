<?php

class WidgetFramework_XenForo_ControllerPublic_Misc extends XFCP_WidgetFramework_XenForo_ControllerPublic_Misc
{
	public function actionWfDisableReveal()
	{
		$session = XenForo_Application::get('session');

		if (!$session->get('_WidgetFramework_reveal'))
		{
			return $this->responseNoPermission();
		}

		$session->set('_WidgetFramework_reveal', false);

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $this->getDynamicRedirect());
	}

}
