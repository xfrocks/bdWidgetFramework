<?php

class WidgetFramework_WidgetRenderer_HtmlWithoutWrapper extends WidgetFramework_WidgetRenderer_TemplateWithoutWrapper
{
    protected function _getConfiguration()
    {
        $configuration = parent::_getConfiguration();
        $configuration['isHidden'] = true;

        return $configuration;
    }

}
