<?php

class WidgetFramework_ViewAdmin_Widget_List extends XenForo_ViewAdmin_Base
{
    public function prepareParams()
    {
        parent::prepareParams();

        if (isset($this->_params['positions'])
            || empty($this->_params['widgets'])
        ) {
            return;
        }

        $positions = array();

        WidgetFramework_Helper_Sort::addWidgetToPositions($positions, $this->_params['widgets']);

        foreach ($positions as $positionCode => &$positionRef) {
            foreach ($positionRef['widgetsByIds'] as $widgetId => &$widgetRef) {
                if (!empty($widgetRef['renderer'])) {
                    /** @var WidgetFramework_WidgetRenderer $renderer */
                    $renderer = $widgetRef['renderer'];
                    $widgetRef['title'] = strip_tags($renderer->extraPrepareTitle($widgetRef));
                }
            }
        }

        $this->_params['positions'] = $positions;
    }

}
