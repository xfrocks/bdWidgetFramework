<?php

class WidgetFramework_WidgetRenderer_OnlineUsers extends WidgetFramework_WidgetRenderer
{
	const APPLICATION_KEY = 'WidgetFramework_onlineUsers';

	public function extraPrepareTitle(array $widget)
	{
		if (empty($widget['title']))
		{
			return new XenForo_Phrase('members_online_now');
		}

		$preparedTitle = parent::extraPrepareTitle($widget);

		if ($preparedTitle instanceof XenForo_Phrase)
		{
			$onlineUsers = $this->_getOnlineUsers();

			$params = $preparedTitle->getParams();
			foreach ($onlineUsers as $key => $value)
			{
				if (is_numeric($value))
				{
					$params[$key] = XenForo_Template_Helper_Core::numberFormat($value);
				}
			}
			$preparedTitle->setParams($params);
		}

		return $preparedTitle;
	}

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

			XenForo_Application::set(self::APPLICATION_KEY, $params['onlineUsers']);
		}
		else
		{
			$visitor = XenForo_Visitor::getInstance();
			$onlineUsers = $this->_getOnlineUsers();

			$renderTemplateObject->setParam('onlineUsers', $onlineUsers);
			$renderTemplateObject->setParam('visitor', $visitor);
		}

		return $renderTemplateObject->render();
	}

	protected function _getExtraDataLink(array $widget)
	{
		return XenForo_Link::buildPublicLink('online');
	}

	protected function _getOnlineUsers()
	{
		try
		{
			$onlineUsers = XenForo_Application::get(self::APPLICATION_KEY);
		}
		catch (Exception $e)
		{
			$onlineUsers = false;
		}

		if (empty($onlineUsers))
		{
			$visitor = XenForo_Visitor::getInstance();

			/* @var $sessionModel XenForo_Model_Session */
			$sessionModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Session');

			$onlineUsers = $sessionModel->getSessionActivityQuickList($visitor->toArray(), array('cutOff' => array(
					'>',
					$sessionModel->getOnlineStatusTimeout()
				)), ($visitor['user_id'] ? $visitor->toArray() : null));

			XenForo_Application::set(self::APPLICATION_KEY, $onlineUsers);
		}

		return $onlineUsers;
	}

}
