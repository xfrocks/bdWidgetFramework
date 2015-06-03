<?php

class WidgetFramework_WidgetRenderer_Html extends WidgetFramework_WidgetRenderer_Template
{
    protected function _getConfiguration()
    {
        $configuration = parent::_getConfiguration();
        $configuration['isHidden'] = true;

        return $configuration;
    }

}
