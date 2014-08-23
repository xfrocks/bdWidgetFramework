<?php

class WidgetFramework_ViewPublic_Widget_Render extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$core = WidgetFramework_Core::getInstance();
		$params = $this->_params;

		$widgetModel = $core->getModelFromCache('WidgetFramework_Model_Widget');
		$widget = $widgetModel->getWidgetById($params['_widgetId']);

		if (!empty($widget))
		{
			$params['widget'] = $widget;
			$params['html'] = $core->getRenderedHtmlByWidgetId($widget['widget_id']);
		}
		else
		{
			$params['widget'] = array();
		}

		$output = $this->_renderer->getDefaultOutputArray(get_class($this), $params, 'wf_layout_editor_widget');

		if (!empty($widget))
		{
			$widgetHtml = $core->getRenderedTemplateObjByWidgetId($widget['widget_id']);
			if (!empty($widgetHtml))
			{
				$output['templateHtml'] = $widgetHtml;
			}
		}
		else
		{
			$output['templateHtml'] = '';
		}

		if (!empty($params['_layoutEditorGroup']))
		{
			if (!empty($widget))
			{
				$groupId = $core->getRenderedGroupByWidgetId($widget['widget_id']);
			}
			else
			{
				$groupId = '';
			}

			if ($params['_layoutEditorGroup'] != $groupId)
			{
				$output['groupId'] = $params['_layoutEditorGroup'];
				$output['groupHtml'] = $core->getRenderedTemplateObjByGroupId($params['_layoutEditorGroup']);
			}
		}

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}

}
