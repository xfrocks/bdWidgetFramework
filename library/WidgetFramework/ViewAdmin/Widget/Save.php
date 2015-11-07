<?php

class WidgetFramework_ViewAdmin_Widget_Save extends XenForo_ViewAdmin_Base
{
    public function renderJson()
    {
        return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array(
            '_hasRenderData' => count($this->_params['changedRenderedId']) > 0 ? 1 : 0,
            '_getRender' => 1,
            '_renderedIds' => implode(',', $this->_params['changedRenderedId']),
            'saveMessage' => new XenForo_Phrase('wf_widget_saved_successfully'),
        ));
    }

}
