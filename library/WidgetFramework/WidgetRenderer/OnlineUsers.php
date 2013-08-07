<?php

class WidgetFramework_WidgetRenderer_OnlineUsers extends WidgetFramework_WidgetRenderer
{
	protected function _getConfiguration()
	{
		return array(
			'name' => 'Users Online Now',
			'options' => array(
				'hide_following' => XenForo_Input::UINT,
				'rich' => XenForo_Input::UINT,
			),
		);
	}

	protected function _getOptionsTemplate()
	{
		return 'wf_widget_options_online_users';
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return 'wf_widget_online_users';
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		if ('forum_list' == $positionCode)
		{
			$renderTemplateObject->setParam('onlineUsers', $params['onlineUsers']);
			$renderTemplateObject->setParam('visitor', $params['visitor']);
		}
		else
		{
			$visitor = XenForo_Visitor::getInstance();
			if (empty($GLOBALS['WidgetFramework_onlineUsers']))
			{
				$sessionModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Session');

				$GLOBALS['WidgetFramework_onlineUsers'] = $sessionModel->getSessionActivityQuickList($visitor->toArray(), array('cutOff' => array(
						'>',
						$sessionModel->getOnlineStatusTimeout()
					)), ($visitor['user_id'] ? $visitor->toArray() : null));
			}

			$renderTemplateObject->setParam('onlineUsers', $GLOBALS['WidgetFramework_onlineUsers']);
			$renderTemplateObject->setParam('visitor', $visitor);
		}

		return $renderTemplateObject->render();
	}

	protected function _getExtraDataLink(array $widget)
	{
		return XenForo_Link::buildPublicLink('online');
	}

}
