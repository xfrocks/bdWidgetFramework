<?php

class WidgetFramework_XenForo_ViewAdmin_StyleProperty_List extends XFCP_WidgetFramework_XenForo_ViewAdmin_StyleProperty_List
{
    public function renderHtml()
    {
        parent::renderHtml();

        if (!empty($this->_params['group']['group_name'])
            && strpos($this->_params['group']['group_name'], 'WidgetFramework') === 0
        ) {
            ksort($this->_params['scalars']);
        }
    }

}
