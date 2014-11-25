<?php

class WidgetFramework_DataWriter_Helper_Widget
{
    public static function verifyWidgetId($widget_id, XenForo_DataWriter $dw, $fieldName = false)
    {
        /** @var WidgetFramework_Model_Widget $model */
        $model = XenForo_Model::create('WidgetFramework_Model_Widget');
        $widget = $model->getWidgetById($widget_id);
        if (empty($widget)) {
            $dw->error(new XenForo_Phrase('wf_requested_widget_not_found'), $fieldName);
            return false;
        } else {
            return true;
        }
    }

    public static function verifyClass($class, XenForo_DataWriter $dw, $fieldName = false)
    {
        $widgetRenderer = WidgetFramework_Core::getRenderer($class);
        if (empty($widgetRenderer)) {
            $dw->error(new XenForo_Phrase('wf_invalid_widget_renderer_x', array('renderer' => $class)), $fieldName);
            return false;
        } else {
            return true;
        }
    }

    public static function verifyPosition(&$positions, XenForo_DataWriter $dw, $fieldName = false)
    {
        $positions = trim($positions);

        if (empty($positions)) {
            $dw->error(new XenForo_Phrase('wf_position_can_not_be_empty'), $fieldName);
        }

        if ('all' == $positions) {
            return true;
        }

        /** @var XenForo_Model_Template $templateModel */
        $templateModel = $dw->getModelFromCache('XenForo_Model_Template');
        $db = XenForo_Application::getDb();
        $positionsArray = explode(',', $positions);
        $positionsGood = array();
        $templateForHooks = array();

        foreach ($positionsArray as $position) {
            $position = trim($position);
            if (empty($position)) {
                continue;
            }

            if (in_array($position, array(
                    'wf_widget_page',
                    'hook:wf_widget_page_contents'
                ), true) AND !$dw->get('widget_page_id')
            ) {
                $dw->error(new XenForo_Phrase('wf_position_x_requires_widget_page', array('position' => $position)), $fieldName);
                return false;
            }

            // sondh@2012-08-25
            // added support for hook:hook_name
            if (substr($position, 0, 5) == 'hook:') {
                // accept all kind of hooks, just need to get parent templates for them
                $templates = $db->fetchAll("
					SELECT title
					FROM `xf_template_compiled`
					WHERE template_compiled LIKE " . XenForo_Db::quoteLike('callTemplateHook(\'' . substr($position, 5) . '\',', 'lr') . "
				");

                if (empty($templates) AND $position === 'hook:wf_widget_page_contents') {
                    $templates = array(array('title' => 'wf_widget_page'));
                }

                if (count($templates) > 0) {
                    $templateForHooks[$position] = array();
                    foreach ($templates as $template) {
                        $templateForHooks[$position][] = $template['title'];
                    }
                    $templateForHooks[$position] = array_unique($templateForHooks[$position]);
                } else {
                    $dw->error(new XenForo_Phrase('wf_non_existent_hook_x', array('hook' => substr($position, 5))), $fieldName);
                    return false;
                }
            } else {
                if ($position !== 'wf_widget_page' AND !$templateModel->getTemplateInStyleByTitle($position)) {
                    $dw->error(new XenForo_Phrase('wf_invalid_position_x', array('position' => $position)), $fieldName);
                    return false;
                }
            }

            $positionsGood[] = $position;
        }

        $dw->setExtraData(WidgetFramework_DataWriter_Widget::EXTRA_DATA_TEMPLATE_FOR_HOOKS, $templateForHooks);
        asort($positionsGood);
        $positions = implode(', ', $positionsGood);

        return true;
    }

}
