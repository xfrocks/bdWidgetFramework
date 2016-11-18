<?php

class WidgetFramework_ViewPublic_WidgetPage_Index extends XenForo_ViewPublic_Base
{

    public function renderHtml()
    {
        $options = array('widgetPage' => $this->_params['widgetPage']);

        $layoutTree = WidgetFramework_ViewPublic_Helper_Layout::buildLayoutTree($this,
            $this->_params['widgets'], $options);
        $this->_params['layoutTree'] = $layoutTree;

        WidgetFramework_ViewPublic_Helper_Layout::prepareSidebarWidgets($this, $this->_params['widgets'], $options);

        $this->_params['layoutTreeCssClasses'] = $layoutTree->getOption('cssClasses');
    }

}
