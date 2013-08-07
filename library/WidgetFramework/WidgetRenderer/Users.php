<?php

class WidgetFramework_WidgetRenderer_Users extends WidgetFramework_WidgetRenderer
{
	protected function _getConfiguration()
	{
		return array(
			'name' => 'Users',
			'options' => array(
				'limit' => XenForo_Input::UINT,
				'order' => XenForo_Input::STRING,
				'direction' => XenForo_Input::STRING,

				// since 1.3
				'displayMode' => XenForo_Input::STRING,
			),
			'useCache' => true,
			'cacheSeconds' => 1800, // cache for 30 minutes
		);
	}

	protected function _getOptionsTemplate()
	{
		return 'wf_widget_options_users';
	}

	protected function _renderOptions(XenForo_Template_Abstract $template)
	{
		$template->setParam('_xfrmFound', WidgetFramework_Core::xfrmFound());

		return parent::_renderOptions($template);
	}

	protected function _validateOptionValue($optionKey, &$optionValue)
	{
		if ('limit' == $optionKey)
		{
			if (empty($optionValue))
				$optionValue = 5;
		}

		return parent::_validateOptionValue($optionKey, $optionValue);
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return 'wf_widget_users';
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		$users = false;

		// try to be smart and get the users data if they happen to be available
		if ($positionCode == 'member_list')
		{
			if ($widget['options']['limit'] == 12 && $widget['options']['order'] == 'message_count')
			{
				$users = $params['activeUsers'];
			}

			if ($widget['options']['limit'] == 8 && $widget['options']['order'] == 'register_date')
			{
				$users = $params['latestUsers'];
			}
		}

		if ($users === false)
		{
			$userModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_User');
			$conditions = array(
				// sondh@2012-09-13
				// do not display not confirmed or banned users
				'user_state' => 'valid',
				'is_banned' => 0
			);
			$fetchOptions = array(
				'limit' => $widget['options']['limit'],
				'order' => $widget['options']['order'],
				'direction' => $widget['options']['direction'],
			);
			$users = $userModel->getUsers($conditions, $fetchOptions);
		}

		$renderTemplateObject->setParam('users', $users);

		return $renderTemplateObject->render();
	}

}
