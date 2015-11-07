<?php

class WidgetFramework_Helper_LayoutEditor
{
    protected static $_widgetChanges = array();

    public static function keepWidgetChanges($widgetId, WidgetFramework_DataWriter_Widget $dw, array $newData = array())
    {
        foreach ($newData as $key => $value) {
            self::$_widgetChanges[$widgetId][$key] = true;
        }

        if ($dw->isDelete()) {
            self::$_widgetChanges[$widgetId]['_isDelete'] = true;
        }

        return true;
    }

    public static function getChangedWidgetIds()
    {
        $changedWidgetIds = array();

        foreach (self::$_widgetChanges as $widgetId => $changes) {
            if (isset($changes['_isDelete'])
                || isset($changes['position'])
                || isset($changes['group_id'])
                || isset($changes['display_order'])
                || isset($changes['options'])
            ) {
                $changedWidgetIds[] = $widgetId;
            }
        }

        return $changedWidgetIds;
    }

}
