<?php

class WidgetFramework_ViewAdmin_Widget_Save extends XenForo_ViewAdmin_Base
{
	public function renderJson()
	{
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array(
			'widgetId' => $this->_params['widget']['widget_id'],
			'saveMessage' => new XenForo_Phrase('wf_widget_saved_successfully'),
		));
	}

}
