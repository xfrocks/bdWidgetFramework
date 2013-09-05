<?php

class WidgetFramework_ViewPublic_WidgetPage_Index extends XenForo_ViewPublic_Base
{

	public function renderHtml()
	{
		$this->_params['layoutTree'] = WidgetFramework_ViewPublic_Helper_Layout::buildLayoutTree($this, $this->_params['widgets'], array('widgetPage' => $this->_params['widgetPage'], ));

		$this->_params['layoutTreeCssClasses'] = $this->_params['layoutTree']->getOption('cssClasses');
	}

}
