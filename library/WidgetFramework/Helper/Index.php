<?php

class WidgetFramework_Helper_Index
{
    public static function setup()
    {
        XenForo_Link::setIndexRoute('widget-page-index/');
    }

    public static function getControllerResponse(XenForo_ControllerPublic_Abstract $controller)
    {
        return false;
    }

    public static function setNavtabSelected(array &$tabs, array &$extraTabs)
    {
        $selected = false;

        if (!empty($tabs['forums'])) {
            // found "Forums" navtab, select it now
            $tabs['forums']['selected'] = true;
            $selected = true;
        } else {
            // try to select the first one from $tabs
            foreach ($tabs as &$tab) {
                $tab['selected'] = true;
                $selected = true;
                break;
            }

            if (!$selected) {
                // still not selected!?
                // try with $extraTabs now
                foreach ($extraTabs as &$tab) {
                    $tab['selected'] = true;
                    $selected = true;
                    break;
                }
            }
        }

        return $selected;
    }

    public static function rebuildChildNodesCache()
    {
        /** @var XenForo_Model_Node $nodeModel */
        $nodeModel = XenForo_Model::create('XenForo_Model_Node');
        $nodeId = WidgetFramework_Option::get('indexNodeId');
        $childNodes = array();

        if ($nodeId > 0) {
            $widgetPage = $nodeModel->getNodeById($nodeId);

            if (!empty($widgetPage)) {
                $childNodes = $nodeModel->getChildNodes($widgetPage, true);

                XenForo_Application::setSimpleCacheData(
                    WidgetFramework_Core::SIMPLE_CACHE_CHILD_NODES,
                    $childNodes
                );
            }
        }

        return $childNodes;
    }

    public static function getChildNodes()
    {
        $childNodes = XenForo_Application::getSimpleCacheData(WidgetFramework_Core::SIMPLE_CACHE_CHILD_NODES);

        if ($childNodes === false) {
            return self::rebuildChildNodesCache();
        }

        return $childNodes;
    }
}
