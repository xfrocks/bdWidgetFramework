<?php

class WidgetFramework_ViewPublic_Widget_Render extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		return '';
	}

	public function renderJson()
	{
		$core = WidgetFramework_Core::getInstance();
		$params = $this->_params;

		$output = $this->_renderer->getDefaultOutputArray(__CLASS__, $params, '');

		$output = array_intersect_key($output, array_flip(array(
			'css',
			'js'
		)));

		if (!empty($params['_renderedIds']))
		{
			foreach ($params['_renderedIds'] as $renderedId)
			{
				$rendered = WidgetFramework_Listener::getLayoutEditorRendered($renderedId);

				if (is_string($rendered))
				{
					$output['rendered'][$renderedId] = $rendered;
				}
				elseif (is_array($rendered))
				{
					if (!empty($rendered['normalizedGroupId']))
					{
						$groupRendered = WidgetFramework_Listener::getLayoutEditorRendered($rendered['normalizedGroupId']);
						if (is_string($groupRendered))
						{
							$output['rendered'][$rendered['normalizedGroupId']] = $groupRendered;
						}
					}
				}
			}
		}

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}

}