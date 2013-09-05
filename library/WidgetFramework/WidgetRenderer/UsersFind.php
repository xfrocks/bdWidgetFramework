<?php

class WidgetFramework_WidgetRenderer_UsersFind extends WidgetFramework_WidgetRenderer
{
	public function extraPrepareTitle(array $widget)
	{
		if (empty($widget['title']))
		{
			return new XenForo_Phrase('find_member');
		}

		return parent::extraPrepareTitle($widget);
	}

	protected function _getConfiguration()
	{
		return array(
			'name' => 'Users: Find',
			'useWrapper' => false,
		);
	}

	protected function _getOptionsTemplate()
	{
		return false;
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return 'wf_widget_users_find';
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		return $renderTemplateObject->render();
	}

	protected function _getExtraDataLink(array $widget)
	{
		return XenForo_Link::buildPublicLink('online');
	}

}
