<?php

class WidgetFramework_XenForo_ControllerPublic_Thread extends XFCP_WidgetFramework_XenForo_ControllerPublic_Thread
{
	public function actionPollResults()
	{
		$response = parent::actionPollResults();

		if ($response instanceof XenForo_ControllerResponse_View)
		{
			if ($this->_input->filterSingle('_fromWidget', XenForo_Input::UINT))
			{
				$response->templateName = 'wf_widget_poll_thread_results';
			}
		}

		return $response;
	}

}
