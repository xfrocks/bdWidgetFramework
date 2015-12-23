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

    public static function verifyPosition(&$position, XenForo_DataWriter $dw, $fieldName = false)
    {
        $position = trim($position);

        if (empty($position)) {
            $dw->error(new XenForo_Phrase('wf_position_can_not_be_empty'), $fieldName);
        }

        if ('all' == $position) {
            return true;
        }

        /** @var XenForo_Model_Template $templateModel */
        $templateModel = $dw->getModelFromCache('XenForo_Model_Template');
        $db = XenForo_Application::getDb();
        $positionCodes = WidgetFramework_Helper_String::splitPositionCodes($position);
        $verifiedPositionCodes = array();
        $templateForHooks = array();

        foreach ($positionCodes as $positionCode) {
            $positionCode = trim($positionCode);
            if (empty($positionCode)) {
                continue;
            }

            if (in_array($positionCode, array(
                    'wf_widget_page',
                    'hook:wf_widget_page_contents'
                ), true) && !$dw->get('widget_page_id')
            ) {
                $dw->error(new XenForo_Phrase('wf_position_x_requires_widget_page',
                    array('position' => $positionCode)), $fieldName);
                return false;
            }

            if (in_array($positionCode, array(
                'wf_widget_ajax',
            ), true)) {
                $dw->error(new XenForo_Phrase('wf_invalid_position_x',
                    array('position' => $positionCode)), $fieldName);
                return false;
            }

            // sondh@2012-08-25
            // added support for hook:hook_name
            if (substr($positionCode, 0, 5) == 'hook:') {
                // accept all kind of hooks, just need to get parent templates for them
                $templates = $db->fetchAll('
                    SELECT title
                    FROM `xf_template_compiled`
                    WHERE template_compiled LIKE '
                    . XenForo_Db::quoteLike('callTemplateHook(\'' . substr($positionCode, 5) . '\',', 'lr') . '
                ');

                if (count($templates) > 0) {
                    $templateForHooks[$positionCode] = array();
                    foreach ($templates as $template) {
                        $templateForHooks[$positionCode][] = $template['title'];
                    }
                    $templateForHooks[$positionCode] = array_unique($templateForHooks[$positionCode]);
                } elseif ($positionCode === 'hook:wf_widget_page_contents') {
                    // ignore
                } else {
                    $dw->error(new XenForo_Phrase('wf_non_existent_hook_x',
                        array('hook' => substr($positionCode, 5))), $fieldName);
                    return false;
                }
            } elseif (!$templateModel->getTemplateInStyleByTitle($positionCode)) {
                $dw->error(new XenForo_Phrase('wf_invalid_position_x',
                    array('position' => $positionCode)), $fieldName);
                return false;
            }

            $verifiedPositionCodes[] = $positionCode;
        }

        $dw->setExtraData(WidgetFramework_DataWriter_Widget::EXTRA_DATA_TEMPLATE_FOR_HOOKS, $templateForHooks);
        asort($verifiedPositionCodes);
        $position = implode(', ', $verifiedPositionCodes);

        return true;
    }

}
