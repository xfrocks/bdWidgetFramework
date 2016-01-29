<?php

class WidgetFramework_Helper_Sort
{
    public static function addWidgetToPositions(array &$positions, array &$widgets)
    {
        $positionCodes = array();

        foreach ($widgets as $widget) {
            if (isset($widget['positionCodes'])) {
                $widgetPositionCodes = $widget['positionCodes'];
            } else {
                $widgetPositionCodes = WidgetFramework_Helper_String::splitPositionCodes($widget['position']);
            }

            foreach ($widgetPositionCodes as $positionCode) {
                $positionCode = trim($positionCode);
                if (empty($positionCode)) {
                    continue;
                }
                $positionCodes[] = $positionCode;

                if (!isset($positions[$positionCode])) {
                    $positions[$positionCode] = array(
                        'position' => $positionCode,
                        'widgets' => array(),
                        'widgetsByIds' => array(),
                    );
                }

                $positions[$positionCode]['widgetsByIds'][$widget['widget_id']] = $widget;
            }
        }

        $positionCodes = array_unique($positionCodes);

        foreach ($positionCodes as $positionCode) {
            $positionRef =& $positions[$positionCode];

            foreach ($positionRef['widgetsByIds'] as $widgetId => &$widgetRef) {
                if (empty($widgetRef['group_id'])) {
                    $positionRef['widgets'][$widgetId] = &$widgetRef;
                } elseif (isset($positionRef['widgetsByIds'][$widgetRef['group_id']])) {
                    $positionRef['widgetsByIds'][$widgetRef['group_id']]['widgets'][$widgetId] =& $widgetRef;
                }
            }

            WidgetFramework_Helper_Sort::sortPositionWidgets($positionRef['widgets']);
        }

        return array(
            'positionCodes' => $positionCodes,
        );
    }

    public static function sortPositionWidgets(array &$widgets)
    {
        uasort($widgets, array(
            'WidgetFramework_Helper_Sort',
            'widgetsByDisplayOrderDesc'
        ));

        foreach ($widgets as &$widgetRef) {
            if (isset($widgetRef['widgets'])) {
                self::sortPositionWidgets($widgetRef['widgets']);
            }
        }
    }

    public static function widgetsByDisplayOrderDesc($a, $b)
    {
        $doa = $a['display_order'];
        $dob = $b['display_order'];

        if ($doa < 0
            && $doa < 0
        ) {
            // both are negative display order
            $result = $dob - $doa;
        } else {
            $result = $doa - $dob;
        }

        if ($result === 0) {
            $result = $a['widget_id'] - $b['widget_id'];
        }

        return $result;
    }

    public static function widgetsByDisplayOrderAsc($a, $b)
    {
        $doa = $a['display_order'];
        $dob = $b['display_order'];

        $result = $doa - $dob;

        if ($result === 0) {
            $result = $a['widget_id'] - $b['widget_id'];
        }

        return $result;
    }

    public static function widgetsByLabel($a, $b)
    {
        return strcmp($a['label'], $b['label']);
    }

    public static function widgetsByGroupId($a, $b)
    {
        $ga = $a['group_id'];
        $gb = $b['group_id'];

        $result = $ga - $gb;

        if ($result === 0) {
            $result = $a['widget_id'] - $b['widget_id'];
        }

        return $result;
    }
}
