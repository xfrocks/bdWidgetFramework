<?php

class WidgetFramework_Helper_LayoutEditor
{
    protected static $_widgetChanges = array();

    public static function keepWidgetChanges($widgetId, WidgetFramework_DataWriter_Widget $dw, array $newData)
    {
        if (!XenForo_Application::debugMode()) {
            return false;
        }

        foreach ($newData as $key => $value) {
            if ($key == 'options') {
                $existingOptions = $dw->getWidgetOptions(true);
                $options = $dw->getWidgetOptions();
                $optionKeys = array_unique(array_merge(array_keys($existingOptions), array_keys($options)));

                foreach ($optionKeys as $optionKey) {
                    $existingSerialized = null;
                    if (isset($existingOptions[$optionKey])) {
                        $existingSerialized = $existingOptions[$optionKey];
                    }
                    if (!is_string($existingSerialized)) {
                        $existingSerialized = serialize($existingSerialized);
                    }

                    $optionSerialized = null;
                    if (isset($options[$optionKey])) {
                        $optionSerialized = $options[$optionKey];
                    }
                    if (!is_string($optionSerialized)) {
                        $optionSerialized = serialize($optionSerialized);
                    }

                    if ($existingSerialized !== $optionSerialized) {
                        self::$_widgetChanges[$widgetId]['options'][$optionKey] = array(
                            $existingSerialized,
                            $optionSerialized
                        );
                    }
                }
            } else {
                self::$_widgetChanges[$widgetId][$key] = array(
                    $dw->getExisting($key),
                    $value
                );
            }
        }

        return true;
    }

    public static function getWidgetChanges()
    {
        return self::$_widgetChanges;
    }

    public static function getChangedRenderedId(WidgetFramework_DataWriter_Widget $dw, array $changed = array())
    {
        if ($dw->isChanged('position')
            || $dw->isChanged('display_order')
            || $dw->isChanged('group_id')
        ) {
            $changed[] = $dw->get('widget_id');

            $existingPosition = $dw->getExisting('position');
            $existingGroupId = $dw->getExisting('group_id');
            $newPosition = $dw->get('position');
            $newGroupId = $dw->get('group_id');

            if ($existingPosition !== $newPosition
                || $existingGroupId !== $newGroupId
            ) {
                if (!empty($existingGroupId)) {
                    $changed[] = $existingGroupId;
                }

                if (!empty($newGroupId)) {
                    $changed[] = $newGroupId;
                }
            }
        } elseif ($dw->isDelete()) {
            $changed[] = $dw->get('widget_id');

            $groupId = $dw->get('group_id');
            if (!empty($groupId)) {
                $changed[] = $groupId;
            }
        }

        return $changed;
    }

}
