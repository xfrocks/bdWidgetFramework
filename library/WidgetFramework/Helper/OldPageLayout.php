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

        $layout = new _Layout_Vertical($widgets, $options, $widgetIds);

        return $layout;
    }

}

class _Layout_Vertical extends _Layout_Multiple
{
    protected function _doLayout_finishedGroup($groupIdsOrdered)
    {
        if (count($groupIdsOrdered) > 1) {
            $firstWidget = reset($this->_widgets);
            $firstWidget['position'] = $this->_options['position'];
            $firstWidget['group_id'] = $this->_options['group_id'];
            $groupWidget = $this->_getWidgetModel()->createGroupContaining($firstWidget);

            $this->_options['group_id'] = $groupWidget['widget_id'];
        }
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
        return new _Layout_Horizontal($widgets, $this->_options, $widgetIds, $depth);
    }

}

class _Layout_Horizontal extends _Layout_Multiple
{
    protected function _doLayout_finishedGroup($groupIdsOrdered)
    {
        $firstWidget = reset($this->_widgets);
        $firstWidget['position'] = $this->_options['position'];
        $firstWidget['group_id'] = $this->_options['group_id'];
        $groupWidget = $this->_getWidgetModel()->createGroupContaining($firstWidget, array('layout' => 'columns'));

        $this->_options['group_id'] = $groupWidget['widget_id'];
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
        return new _Layout_Vertical($widgets, $this->_options, $widgetIds, $depth);
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
        $groups = array();
        $mapping = array();

        $fieldIndex = $this->_getFieldIndex();
        $fieldSize = $this->_getFieldSize();

        foreach ($widgetIds as $widgetId) {
            $widgetRef = &$this->_widgets[$widgetId];

            $this->_splitGroups($groups, $mapping, $widgetRef['options'][$fieldIndex],
                $widgetRef['options'][$fieldIndex] + $widgetRef['options'][$fieldSize] - 1);
        }

        $groupIdsOrdered = array();
        ksort($mapping);
        foreach ($mapping as $index => $groupId) {
            if (!in_array($groupId, $groupIdsOrdered)) {
                $groupIdsOrdered[] = $groupId;
            }
        }

        $this->_doLayout_finishedGroup($groupIdsOrdered);

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

            if (empty($subLayoutWidgetIds)) {
                // really?
                continue;
            } elseif (count($subLayoutWidgetIds) == 1) {
                $firstWidgetId = reset($subLayoutWidgetIds);
                $this->_subLayouts[$groupId] = $this->_newSingleLayout($this->_widgets[$firstWidgetId]);
                $this->_subLayoutIndeces[$groupId] = $indeces;
            } else {
                $this->_subLayouts[$groupId] = $this->_newSubLayout(
                    $this->_widgets, $subLayoutWidgetIds, $this->_depth + 1);
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

    abstract protected function _doLayout_finishedGroup($groupIdsOrdered);
}

class _Layout_Single
{
    protected $_options;

    public function __construct(array &$widget, array $options)
    {
        $this->_options = $options;

        $widget['position'] = $options['position'];
        $widget['group_id'] = $options['group_id'];
        $widget['template_for_hooks'] = array(
            $options['position'] => array(
                'wf_widget_page',
            ),
        );
    }

}
