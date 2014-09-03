<?php

class WidgetFramework_ViewPublic_WidgetPage_View extends XenForo_ViewPublic_Base
{
	public function prepareParams()
	{
		$widgets = array();
		foreach ($this->_params['widgets'] as $widget)
		{
			$widgets[] = $widget;
		}

		$core = WidgetFramework_Core::getInstance();
		$core->addWidgets($widgets);

		return parent::prepareParams();
	}

}
