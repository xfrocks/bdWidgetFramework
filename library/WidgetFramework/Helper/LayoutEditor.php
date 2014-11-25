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

    public static function splitGroupParts($groupName)
    {
        return preg_split('#/#', $groupName, -1, PREG_SPLIT_NO_EMPTY);
    }

    public static function getChangedRenderedId(WidgetFramework_DataWriter_Widget $dw, array $changed = array())
    {
        if ($dw->isChanged('position') OR $dw->isChanged('display_order') OR $dw->isChanged('options')) {
            $changed[] = $dw->get('widget_id');

            $existingPosition = $dw->getExisting('position');
            $existingOptions = $dw->getWidgetOptions(true);
            $existingGroup = '';
            if (!empty($existingOptions['tab_group'])) {
                $existingGroup .= $existingOptions['tab_group'];
            }

            $newPosition = $dw->get('position');
            $newOptions = $dw->getWidgetOptions();
            $newGroup = '';
            if (!empty($newOptions['tab_group'])) {
                $newGroup .= $newOptions['tab_group'];
            }

            if ($existingPosition !== $newPosition OR $existingGroup !== $newGroup) {
                if (!empty($existingGroup)) {
                    $changed[] = WidgetFramework_Helper_String::normalizeHtmlElementId($existingGroup);
                }

                if (!empty($newGroup)) {
                    $changed[] = WidgetFramework_Helper_String::normalizeHtmlElementId($newGroup);
                }
            }
        } elseif ($dw->isDelete()) {
            $changed[] = $dw->get('widget_id');

            $options = $dw->getWidgetOptions();
            if (!empty($options['tab_group'])) {
                $changed[] = WidgetFramework_Helper_String::normalizeHtmlElementId($options['tab_group']);
            }
        }

        return $changed;
    }

    public static function generateWidgetGroupCss(array &$containerData, $count)
    {
        $head = array();

        for ($i = $count - 1; $i <= $count + 1; $i++) {
            if ($i <= 0) {
                continue;
            }

            $containerDataHeadKey = __METHOD__ . $i;
            if (isset($containerData['head'][$containerDataHeadKey])) {
                continue;
            }

            $widgetGroupCssClass = sprintf('widget-group-%d-widgets', $i);
            $sidebarWidth = intval(XenForo_Template_Helper_Core::styleProperty('sidebar.width'));

            $widgetMinWidth = 100 / $i;
            $widgetWidth = $sidebarWidth - 50;
            $controlsMinWidth = 100 / ($i * 2 + 1);
            $controlsWidth = $widgetWidth / 2;
            $widgetsMinWidth = 100 - $controlsMinWidth;
            $widgetsWidth = $i * $widgetWidth;
            $stretcherWidth = $widgetsWidth + $controlsWidth;

            $head[$containerDataHeadKey] = <<<EOF
<style>
.{$widgetGroupCssClass}.columns > .stretcher
{
	min-width: 100%;
	width: {$stretcherWidth}px;
}

.{$widgetGroupCssClass}.columns > .stretcher > .widgets
{
	min-width: {$widgetsMinWidth}%;
	width: {$widgetsWidth}px;
}
	.{$widgetGroupCssClass}.columns > .stretcher > .widgets > div
	{
		min-width: {$widgetMinWidth}%;
		width: {$widgetWidth}px;
	}

.{$widgetGroupCssClass}.columns > .stretcher > .controls
{
	min-width: {$controlsMinWidth}%;
	width: {$controlsWidth}px;
}
</style>
EOF;
        }

        if (!empty($head)) {
            return array('head' => $head);
        } else {
            return array();
        }
    }

}
