<?php

class WidgetFramework_WidgetRenderer_ShareThisPage extends WidgetFramework_WidgetRenderer
{
	public function extraPrepareTitle(array $widget)
	{
		if (empty($widget['title']))
		{
			return new XenForo_Phrase('share_this_page');
		}

		return parent::extraPrepareTitle($widget);
	}

	protected function _getConfiguration()
	{
		return array(
			'name' => 'Share This Page',
			'useWrapper' => false
		);
	}

	protected function _getOptionsTemplate()
	{
		return false;
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return 'wf_widget_share_page';
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		$renderTemplateObject->setParams($params);

		if (!isset($params['url']))
		{
			// try to detect the correct url for different templates
			$autoDetectedUrl = false;

			switch ($positionCode)
			{
				case 'forum_view':
					$autoDetectedUrl = XenForo_Link::buildPublicLink('canonical:forums', $params['forum']);
					break;
				case 'member_view':
					// this widget on member_view, seriously?
					$autoDetectedUrl = XenForo_Link::buildPublicLink('canonical:members', $params['user']);
					break;
				case 'thread_view':
					$autoDetectedUrl = XenForo_Link::buildPublicLink('canonical:threads', $params['thread']);
					break;
			}

			if ($autoDetectedUrl !== false)
			{
				$renderTemplateObject->setParam('url', $autoDetectedUrl);
			}
		}

		return $renderTemplateObject->render();
	}

}
