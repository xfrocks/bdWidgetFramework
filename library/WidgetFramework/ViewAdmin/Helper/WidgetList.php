<?php

class WidgetFramework_ViewAdmin_Helper_WidgetList
{
    public static function renderItems(
        /** @noinspection PhpUnusedParameterInspection */
        $contents,
        array $params,
        XenForo_Template_Abstract $template
    ) {
        $itemsTemplate = $template->create('wf_widget_list_items', $template->getParams());

        if (isset($params['widgets'])) {
            $itemsTemplate->setParam('widgets', $params['widgets']);
        }

        if (isset($params['canToggle'])) {
            $itemsTemplate->setParam('canToggle', $params['canToggle']);
        }

        if (isset($params['level'])) {
            $itemsTemplate->setParam('level', $params['level']);
        } else {
            $itemsTemplate->setParam('level', 0);
        }

        return strval($itemsTemplate);
    }
}