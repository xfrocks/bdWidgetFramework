<?php

class WidgetFramework_ViewPublic_WidgetPage_View extends XenForo_ViewPublic_Base
{
    public function prepareParams()
    {
        parent::prepareParams();

        if (isset($this->_params[__METHOD__])) {
            return;
        }
        $this->_params[__METHOD__] = true;

        if (empty($this->_params['widgets'])) {
            return;
        }

        $core = WidgetFramework_Core::getInstance();
        $core->addWidgets($this->_params['widgets']);
    }

}
