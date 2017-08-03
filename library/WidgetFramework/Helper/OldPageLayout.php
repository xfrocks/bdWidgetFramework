<?php

class WidgetFramework_Helper_OldPageLayout
{
    public static function buildLayoutTree(array &$widgets)
    {
        $rows = 0;
        $cols = 0;
        $widgetIds = array();

        foreach ($widgets as $widget) {
            if (!empty($widget['position'])) {
                continue;
            }
            if (!isset($widget['options']['layout_row'])) {
                continue;
            }
            if (!isset($widget['options']['layout_col'])) {
                continue;
            }
            if (empty($widget['options']['layout_sizeRow'])) {
                continue;
            }
            if (empty($widget['options']['layout_sizeCol'])) {
                continue;
            }

            $rows = max($rows, $widget['options']['layout_row'] + $widget['options']['layout_sizeRow']);
            $cols = max($cols, $widget['options']['layout_col'] + $widget['options']['layout_sizeCol']);
            $widgetIds[] = $widget['widget_id'];
        }

        $options = array(
            'position' => 'hook:wf_widget_page_contents',
            'group_id' => 0,

            'rows' => $rows,
            'cols' => $cols,
        );

        $layout = new _Layout_Columns($widgets, $options, $widgetIds);

        self::log(
            '%s/buildLayoutTree(%s): layout=%s',
            __CLASS__,
            implode(', ', $widgetIds),
            $layout
        );

        return $layout;
    }

    public static function log()
    {
        if (!defined('DEFERRED_CMD')
            || !XenForo_Application::debugMode()
        ) {
            return;
        }

        $args = func_get_args();
        $args[0] .= "\n";
        call_user_func_array('printf', $args);
    }
}

class _Layout_Columns extends _Layout_Multiple
{
    protected function _doLayout_postSplitGroups($groupIdsOrdered)
    {
        if (count($groupIdsOrdered) < 2) {
            return;
        }

        $firstWidget = reset($this->_widgets);
        $firstWidget['position'] = $this->_options['position'];
        $firstWidget['group_id'] = $this->_options['group_id'];
        $groupWidget = $this->_getWidgetModel()->createGroupContaining($firstWidget, array('layout' => 'columns'));

        $this->_options['group_id'] = $groupWidget['widget_id'];
        WidgetFramework_Helper_OldPageLayout::log(
            '%s/_doLayout_finishedGroup: new `group_id`=%s',
            get_class($this),
            $this->_options['group_id']
        );
    }

    protected function _getFieldIndex()
    {
        return 'layout_col';
    }

    protected function _getFieldSize()
    {
        return 'layout_sizeCol';
    }

    protected function _newSingleLayout(array &$widget)
    {
        return new _Layout_Single($widget, $this->_options);
    }

    protected function _newSubLayout(array &$widgets, array $widgetIds, $depth)
    {
        return new _Layout_Rows($widgets, $this->_options, $widgetIds, $depth);
    }
}

class _Layout_Rows extends _Layout_Multiple
{
    protected function _doLayout_postSplitGroups($groupIdsOrdered)
    {
        // intentionally left blank
    }

    protected function _getFieldIndex()
    {
        return 'layout_row';
    }

    protected function _getFieldSize()
    {
        return 'layout_sizeRow';
    }

    protected function _newSingleLayout(array &$widget)
    {
        return new _Layout_Single($widget, $this->_options);
    }

    protected function _newSubLayout(array &$widgets, array $widgetIds, $depth)
    {
        return new _Layout_Columns($widgets, $this->_options, $widgetIds, $depth);
    }
}

abstract class _Layout_Multiple
{
    protected $_widgets;
    protected $_options;
    protected $_widgetIds;
    protected $_depth;

    protected $_subLayouts = array();
    protected $_subLayoutIndeces = array();

    public function __construct(array &$widgets, array $options, array $widgetIds, $depth = 0)
    {
        $this->_widgets = &$widgets;
        $this->_options = $options;
        $this->_widgetIds = $widgetIds;
        $this->_depth = $depth;

        if ($depth < 10) {
            $this->_doLayout($widgetIds);
        }
    }

    public function __toString()
    {
        return sprintf(
            '%s(group #%d): [%s]',
            get_class($this),
            $this->_options['group_id'],
            implode(', ', $this->_subLayouts)
        );
    }

    public function getOption($key)
    {
        if (isset($this->_options[$key])) {
            return $this->_options[$key];
        }

        return null;
    }

    protected function _getHash()
    {
        return md5(implode('_', $this->_widgetIds));
    }

    protected function _doLayout(array $widgetIds)
    {
        WidgetFramework_Helper_OldPageLayout::log(
            '%s/_doLayout: widgetIds=%s',
            get_class($this),
            implode(', ', $widgetIds)
        );

        $groups = array();
        $mapping = array();

        $fieldIndex = $this->_getFieldIndex();
        $fieldSize = $this->_getFieldSize();

        WidgetFramework_Helper_OldPageLayout::log(
            '%s/_doLayout: fieldIndex=%s, fieldSize=%s',
            get_class($this),
            $fieldIndex,
            $fieldSize
        );

        foreach ($widgetIds as $widgetId) {
            $widgetRef = &$this->_widgets[$widgetId];

            $this->_splitGroups(
                $groups,
                $mapping,
                $widgetRef['options'][$fieldIndex],
                $widgetRef['options'][$fieldIndex] + $widgetRef['options'][$fieldSize] - 1
            );
        }

        $groupIdsOrdered = array();
        ksort($mapping);
        foreach ($mapping as $index => $groupId) {
            if (!in_array($groupId, $groupIdsOrdered)) {
                $groupIdsOrdered[] = $groupId;
            }
        }

        WidgetFramework_Helper_OldPageLayout::log(
            '%s/_doLayout(%s): groupIds=%s',
            get_class($this),
            implode(', ', $widgetIds),
            implode(', ', $groupIdsOrdered)
        );

        $this->_doLayout_postSplitGroups($groupIdsOrdered);

        foreach ($groupIdsOrdered as $groupId) {
            $indeces = $groups[$groupId];
            if (empty($indeces)) {
                continue;
            }

            $subLayoutWidgetIds = array();

            foreach ($widgetIds as $widgetId) {
                $widgetRef = &$this->_widgets[$widgetId];

                if (in_array($widgetRef['options'][$fieldIndex], $indeces)) {
                    $subLayoutWidgetIds[] = $widgetId;
                }
            }
            WidgetFramework_Helper_OldPageLayout::log(
                '%s/_doLayout: groupId=%d, widgetIds=%s',
                get_class($this),
                $groupId,
                implode(', ', $subLayoutWidgetIds)
            );

            if (empty($subLayoutWidgetIds)) {
                // really?
                continue;
            } elseif (count($subLayoutWidgetIds) == 1) {
                $firstWidgetId = reset($subLayoutWidgetIds);
                $this->_subLayouts[$groupId] = $this->_newSingleLayout($this->_widgets[$firstWidgetId]);
                $this->_subLayoutIndeces[$groupId] = $indeces;
            } else {
                $this->_subLayouts[$groupId] = $this->_newSubLayout(
                    $this->_widgets,
                    $subLayoutWidgetIds,
                    $this->_depth + 1
                );
                $this->_subLayoutIndeces[$groupId] = $indeces;
            }
        }
    }

    protected function _splitGroups(array &$groups, array &$mapping, $x0, $x1)
    {
        $groupId = false;
        for ($x = $x0; $x <= $x1; $x++) {
            if ($groupId === false) {
                // first col
                if (!isset($mapping[$x])) {
                    // new col
                    $groups[] = array($x);
                    $mapping[$x] = count($groups) - 1;
                }
                $groupId = $mapping[$x];
            } else {
                // second col and beyond
                if (!isset($mapping[$x])) {
                    // no group yet, great
                    $groups[$groupId][] = $x;
                    $mapping[$x] = $groupId;
                } elseif ($mapping[$x] != $groupId) {
                    // merge group...
                    $_groupId = $mapping[$x];
                    foreach ($groups[$_groupId] as $_x) {
                        $groups[$groupId][] = $_x;
                        $mapping[$_x] = $groupId;
                    }
                    $groups[$_groupId] = array();
                    // empty the other group
                }
            }
        }
    }

    /**
     * @return WidgetFramework_Model_Widget
     */
    protected function _getWidgetModel()
    {
        return WidgetFramework_Core::getInstance()->getModelFromCache('WidgetFramework_Model_Widget');
    }

    abstract protected function _getFieldIndex();

    abstract protected function _getFieldSize();

    abstract protected function _newSingleLayout(array &$widget);

    abstract protected function _newSubLayout(array &$widgets, array $widgetIds, $depth);

    abstract protected function _doLayout_postSplitGroups($groupIdsOrdered);
}

class _Layout_Single
{
    protected static $widgetCount = 0;
    protected $_widget;

    public function __construct(array &$widget, array $options)
    {
        $widget['position'] = $options['position'];
        $widget['group_id'] = $options['group_id'];
        $widget['display_order'] = (++self::$widgetCount) * 10;
        $widget['template_for_hooks'] = array(
            $options['position'] => array(
                'wf_widget_page',
            ),
        );

        $this->_widget = $widget;
    }

    public function __toString()
    {
        return sprintf(
            '%s(#%d, group #%d, order #%d)',
            get_class($this),
            $this->_widget['widget_id'],
            $this->_widget['group_id'],
            $this->_widget['display_order']
        );
    }
}
