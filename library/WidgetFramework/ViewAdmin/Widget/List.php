<?php

class WidgetFramework_ViewAdmin_Widget_List extends XenForo_ViewAdmin_Base
{
    public function prepareParams()
    {
        parent::prepareParams();

        if (isset($this->_params['positions'])) {
            return;
        }

        $positions = array();

        WidgetFramework_Helper_Sort::addWidgetToPositions($positions, $this->_params['widgets'], true);

        foreach ($positions as $positionCode => &$positionRef) {
            foreach ($positionRef['widgetsByIds'] as $widgetId => &$widgetRef) {
                $renderer = WidgetFramework_WidgetRenderer::create($widgetRef['class']);
                $widgetRef['_runtime']['title'] = WidgetFramework_Helper_String::createWidgetTitleDelayed(
                    $renderer, $widgetRef);
            }
        }

        $this->_params['positions'] = $positions;
    }

}
