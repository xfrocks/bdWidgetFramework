<?php

class WidgetFramework_Helper_Sort
{
    public static function addWidgetToPositions(array &$positions, array &$widgets, $sortNaive = false)
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

            self::sortPositionWidgets($positionRef['widgets'], $sortNaive);
        }

        return array(
            'positionCodes' => $positionCodes,
        );
    }

    public static function sortPositionWidgets(array &$widgets, $sortNaive)
    {
        uasort($widgets, array(
            __CLASS__,
            $sortNaive ? 'widgetsByDisplayOrderNaive'
                : 'widgetsByDisplayOrderNegativeAware'
        ));

        foreach ($widgets as &$widgetRef) {
            if (isset($widgetRef['widgets'])) {
                self::sortPositionWidgets($widgetRef['widgets'], $sortNaive);
            }
        }
    }

    public static function widgetsByDisplayOrderNegativeAware($a, $b)
    {
        $doa = $a['display_order'];
        $dob = $b['display_order'];

        if ($doa < 0 && $dob < 0) {
            // both negative display order
            $result = $dob - $doa;
        } elseif ($doa < 0) {
            // practically this branch is not needed because it implies that: ($doa < 0) and ($dob > 0),
            // in that case ($result = $doa - $dob) will always be a negative value
            // we keep it here because the below branch ($dob < 0) is actually required
            // and it may be non-trivial to understand in the future without this branch
            $result = -1;
        } elseif ($dob < 0) {
            $result = 1;
        } else {
            $result = $doa - $dob;
        }

        if ($result === 0) {
            $result = $a['widget_id'] - $b['widget_id'];
        }

        return $result;
    }

    public static function widgetsByDisplayOrderNaive($a, $b)
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
