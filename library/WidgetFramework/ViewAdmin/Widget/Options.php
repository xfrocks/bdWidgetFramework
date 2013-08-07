<?php

class WidgetFramework_ViewAdmin_Widget_Options extends XenForo_ViewAdmin_Base
{
	public function renderHtml()
	{
		$renderer = WidgetFramework_Core::getRenderer($this->_params['class']);
		;
		$renderer->renderOptions($this->_renderer, $this->_params);
	}

}
