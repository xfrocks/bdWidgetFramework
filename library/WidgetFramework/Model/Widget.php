<?php

class WidgetFramework_Model_Widget extends XenForo_Model
{
    const SIMPLE_CACHE_KEY = 'widgets';

    public function createGroupContaining(array $widget, array $groupOptions = array())
    {
        $groupDw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
        $groupDw->bulkSet(array(
            'class' => 'WidgetFramework_WidgetGroup',
            'widget_page_id' => $widget['widget_page_id'],
            'position' => $widget['position'],
            'display_order' => $widget['display_order'],
            'active' => 1,
            'options' => $groupOptions,
        ));
        $groupDw->setExtraData(WidgetFramework_DataWriter_Widget::EXTRA_DATA_SKIP_REBUILD, true);
        $groupDw->save();

        return $groupDw->getMergedData();
    }

    public function getWidgetTitlePhrase($widgetId)
    {
        if ($widgetId > 0) {
            return '_widget_title_' . $widgetId;
        } else {
            throw new XenForo_Exception('Cannot get widget title phrase for widget without ID.');
        }
    }

    public function getSiblingWidgets(array $widgets, $widgetId)
    {
        foreach (array_keys($widgets) as $_widgetId) {
            if ($_widgetId === $widgetId) {
                return $widgets;
            }

            if (isset($widgets[$_widgetId]['widgets'])) {
                $response = $this->getSiblingWidgets($widgets[$_widgetId]['widgets'], $widgetId);

                if (!empty($response)) {
                    return $response;
                }
            }
        }

        return array();
    }

    public function countRealWidgetInWidgets(array $widgets)
    {
        $count = 0;

        foreach (array_keys($widgets) as $_widgetId) {
            if (isset($widgets[$_widgetId]['widgets'])) {
                $count += $this->countRealWidgetInWidgets($widgets[$_widgetId]['widgets']);
            } else {
                $count++;
            }
        }

        return $count;
    }

    public function getLastDisplayOrder($widgetsAtPosition, $groupId = 0)
    {
        if ($groupId > 0) {
            // put into a group
            $siblingWidgets = $this->getSiblingWidgets($widgetsAtPosition, $groupId);
            if (!empty($siblingWidgets[$groupId]['widgets'])) {
                $siblingWidgets = $siblingWidgets[$groupId]['widgets'];
            }
        } else {
            // put into a position
            $siblingWidgets = $widgetsAtPosition;
        }

        $maxDisplayOrder = false;
        foreach ($siblingWidgets as $siblingWidget) {
            if ($maxDisplayOrder === false
                || $maxDisplayOrder < $siblingWidget['display_order']
            ) {
                $maxDisplayOrder = $siblingWidget['display_order'];
            }
        }

        return floor($maxDisplayOrder / 10) * 10 + 10;
    }

    public function getDisplayOrderFromRelative(
        $widgetId,
        $groupId,
        $relativeDisplayOrder,
        $widgetsAtPosition,
        array &$widgetsNeedUpdate = array()
    ) {
        if ($groupId > 0) {
            // put into a group
            $siblingWidgets = $this->getSiblingWidgets($widgetsAtPosition, $groupId);
            if (!empty($siblingWidgets[$groupId]['widgets'])) {
                $siblingWidgets = $siblingWidgets[$groupId]['widgets'];
            }
        } else {
            // put into a position
            $siblingWidgets = $widgetsAtPosition;
        }

        // sort asc by display order (ignore negative/positive)
        uasort($siblingWidgets, array(
            'WidgetFramework_Helper_Sort',
            'widgetsByDisplayOrderAsc'
        ));
        $isNegative = $relativeDisplayOrder < 0;
        foreach (array_keys($siblingWidgets) as $siblingWidgetId) {
            if (($siblingWidgets[$siblingWidgetId]['display_order'] < 0) == $isNegative) {
                // same negative/positive
            } else {
                unset($siblingWidgets[$siblingWidgetId]);
            }
        }

        $reorderedWidgets = array();
        $thisWidget = false;
        $smallestDisplayOrder = false;
        if (isset($siblingWidgets[$widgetId])) {
            $smallestDisplayOrder = $siblingWidgets[$widgetId]['display_order'];
            $thisWidget = $siblingWidgets[$widgetId];

            // ignore current widget before calculating display order
            unset($siblingWidgets[$widgetId]);
        }

        $iStart = -1;
        foreach ($siblingWidgets as $siblingWidgetId => $sameDisplayOrderLevel) {
            if ($sameDisplayOrderLevel['display_order'] < 0) {
                // calculate correct starting relative order for negative orders
                $iStart--;
            }
        }

        $i = $iStart;
        foreach ($siblingWidgets as $siblingWidgetId => $sameDisplayOrderLevel) {
            $i++;

            if ($i == $relativeDisplayOrder) {
                // insert our widget
                $reorderedWidgets[$widgetId] = $thisWidget;
            }

            $reorderedWidgets[$siblingWidgetId] = $sameDisplayOrderLevel;

            if ($smallestDisplayOrder === false
                || $smallestDisplayOrder > $sameDisplayOrderLevel['display_order']
            ) {
                $smallestDisplayOrder = $sameDisplayOrderLevel['display_order'];
            }
        }
        if (!isset($reorderedWidgets[$widgetId])) {
            // our widget is the last in the reordered list
            $reorderedWidgets[$widgetId] = $thisWidget;
        }

        $currentDisplayOrder = $smallestDisplayOrder;
        if ($isNegative) {
            // for negative orders, we have to make sure display order does not reach 0
            $currentDisplayOrder = min($currentDisplayOrder, $this->countRealWidgetInWidgets($reorderedWidgets) * -10);
        }

        $foundDisplayOrder = PHP_INT_MAX;
        foreach ($reorderedWidgets as $reorderedWidgetId => $reorderedWidget) {
            if ($currentDisplayOrder != $reorderedWidget['display_order']) {
                $widgetsNeedUpdate[$reorderedWidgetId]['display_order'] = $currentDisplayOrder;
            }

            if ($reorderedWidgetId == $widgetId) {
                $foundDisplayOrder = $currentDisplayOrder;
            }

            $currentDisplayOrder += 10;
        }

        return $foundDisplayOrder;
    }

    public function importFromFile($fileName, $widgetPage = null, $deleteAll = false)
    {
        if (!file_exists($fileName)
            || !is_readable($fileName)
        ) {
            throw new XenForo_Exception(
                new XenForo_Phrase('please_enter_valid_file_name_requested_file_not_read'), true);
        }

        try {
            $document = new SimpleXMLElement($fileName, 0, true);
        } catch (Exception $e) {
            throw new XenForo_Exception(new XenForo_Phrase('provided_file_was_not_valid_xml_file'), true);
        }

        if ($document->getName() != 'widget_framework'
            || empty($document->widget)
        ) {
            throw new XenForo_Exception(new XenForo_Phrase('wf_provided_file_is_not_an_widgets_xml_file'), true);
        }

        $xmlIsPageWidgets = (intval($document['is_page_widgets']) > 0);
        if ($xmlIsPageWidgets) {
            if ($widgetPage === null) {
                throw new XenForo_Exception(new XenForo_Phrase('wf_provided_file_is_page_widgets_xml_file'), true);
            }
        } else {
            if ($widgetPage !== null) {
                throw new XenForo_Exception(new XenForo_Phrase('wf_provided_file_is_global_widgets_xml_file'), true);
            }
        }

        XenForo_Db::beginTransaction();

        if ($deleteAll) {
            // get existing widgets from database and delete them all
            $widgetsConditions = array();
            if (!empty($widgetPage['node_id'])) {
                $widgetsConditions['widget_page_id'] = $widgetPage['node_id'];
            }
            $existingWidgets = $this->getWidgets($widgetsConditions);

            foreach ($existingWidgets as $existingWidget) {
                $dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
                $dw->setExtraData(WidgetFramework_DataWriter_Widget::EXTRA_DATA_SKIP_REBUILD, true);
                $dw->setExistingData($existingWidget, true);
                $dw->delete();
            }
        }

        $xmlWidgets = array();
        if (isset($document->widget)) {
            $xmlWidgets = XenForo_Helper_DevelopmentXml::fixPhpBug50670($document->widget);
        }

        $widgets = array();
        foreach ($xmlWidgets as $xmlWidget) {
            $widget = array();

            /** @var SimpleXMLElement $xmlWidget */
            foreach ($xmlWidget->attributes() as $attrKey => $attrValue) {
                $widget[$attrKey] = strval($attrValue);
            }

            if (isset($xmlWidget->options)) {
                $options = XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($xmlWidget->options);
                $widget['options'] = unserialize($options);
            }

            if (!isset($widget['widget_id'])) {
                $widget['widget_id'] = 0;
            }
            if (!isset($widget['group_id'])) {
                $widget['group_id'] = 0;
            }

            $widgets[] = $widget;
        }

        $widgetIdsMapping = array();
        while (!empty($widgets)) {
            $widgetImported = 0;

            foreach (array_keys($widgets) as $key) {
                $widget = $widgets[$key];
                if (isset($widget['group_id'])
                    && $widget['group_id'] > 0
                    && !isset($widgetIdsMapping[$widget['group_id']])
                ) {
                    continue;
                }

                $dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
                $dw->setExtraData(WidgetFramework_DataWriter_Widget::EXTRA_DATA_SKIP_REBUILD, true);

                if ($widgetPage !== null) {
                    $dw->set('widget_page_id', $widgetPage['node_id']);
                }

                foreach (array(
                             'title',
                             'class',
                             'position',
                             'display_order',
                             'active',
                             'options',
                         ) as $field) {
                    if (isset($widget[$field])) {
                        $dw->set($field, $widget[$field]);
                    }
                }

                if (isset($widget['group_id'])
                    && $widget['group_id'] > 0
                ) {
                    $dw->set('group_id', $widgetIdsMapping[$widget['group_id']]);
                }

                $dw->save();

                if (isset($widget['widget_id'])) {
                    $widgetIdsMapping[$widget['widget_id']] = $dw->get('widget_id');
                }

                unset($widgets[$key]);
                $widgetImported++;
            }

            if ($widgetImported === 0) {
                throw new XenForo_Exception(new XenForo_Phrase('wf_widget_import_error'), true);
            }
        }

        XenForo_Db::commit();

        $this->buildCache();
    }

    public function getWidgetById($widgetId, array $fetchOptions = array())
    {
        $widgets = $this->getWidgets(array(
            'widget_id' => $widgetId,
        ), $fetchOptions);

        return reset($widgets);
    }

    public function getWidgets(array $conditions = array(), array $fetchOptions = array())
    {
        $whereClause = $this->prepareWidgetConditions($conditions, $fetchOptions);
        $joinOptions = $this->prepareWidgetFetchOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        $widgets = $this->fetchAllKeyed($this->limitQueryResults(
            '
                SELECT widget.*
                ' . $joinOptions['selectFields'] . '
                FROM xf_widget AS widget
                ' . $joinOptions['joinTables'] . '
                WHERE ' . $whereClause . '
            ', $limitOptions['limit'], $limitOptions['offset']
        ), 'widget_id');

        foreach ($widgets as &$widgetRef) {
            $widgetRef['positionCodes'] = WidgetFramework_Helper_String::splitPositionCodes($widgetRef['position']);

            if (!is_array($widgetRef['options'])) {
                $widgetRef['options'] = @unserialize($widgetRef['options']);
            }
            if (empty($widgetRef['options'])) {
                $widgetRef['options'] = array();
            }

            if (!is_array($widgetRef['template_for_hooks'])) {
                $widgetRef['template_for_hooks'] = @unserialize($widgetRef['template_for_hooks']);
            }
            if (empty($widgetRef['template_for_hooks'])) {
                $widgetRef['template_for_hooks'] = array();
            }
        }

        return $widgets;
    }

    public function getCachedWidgets()
    {
        $widgets = XenForo_Application::getSimpleCacheData(self::SIMPLE_CACHE_KEY);

        if (empty($widgets)) {
            return $this->buildCache();
        }

        return $widgets;
    }

    public function buildCache()
    {
        $widgets = $this->getWidgets(array(
            'widget_page_id' => 0,
            'active' => 1,
        ));

        XenForo_Application::setSimpleCacheData(self::SIMPLE_CACHE_KEY, $widgets);

        return $widgets;
    }

    public function prepareWidgetConditions(
        /** @noinspection PhpUnusedParameterInspection */
        array $conditions,
        array &$fetchOptions
    ) {
        $db = $this->_getDb();
        $sqlConditions = array();

        if (isset($conditions['widget_id'])) {
            if (is_array($conditions['widget_id'])
                && count($conditions['widget_id']) > 0
            ) {
                $sqlConditions[] = 'widget.widget_id IN(' . $db->quote($conditions['widget_id']) . ')';
            } else {
                $sqlConditions[] = 'widget.widget_id = ' . $db->quote($conditions['widget_id']);
            }
        }

        if (isset($conditions['widget_page_id'])) {
            if (is_array($conditions['widget_page_id'])
                && count($conditions['widget_page_id']) > 0
            ) {
                $sqlConditions[] = 'widget.widget_page_id IN(' . $db->quote($conditions['widget_page_id']) . ')';
            } else {
                $sqlConditions[] = 'widget.widget_page_id = ' . $db->quote($conditions['widget_page_id']);
            }
        }

        if (isset($conditions['group_id'])) {
            if (is_array($conditions['group_id'])
                && count($conditions['group_id']) > 0
            ) {
                $sqlConditions[] = 'widget.group_id IN(' . $db->quote($conditions['group_id']) . ')';
            } else {
                $sqlConditions[] = 'widget.group_id = ' . $db->quote($conditions['group_id']);
            }
        }

        if (isset($conditions['active'])) {
            $sqlConditions[] = 'widget.active = ' . intval($conditions['active']);
        }

        return $this->getConditionsForClause($sqlConditions);
    }

    public function prepareWidgetFetchOptions(
        /** @noinspection PhpUnusedParameterInspection */
        array $fetchOptions
    ) {
        $selectFields = '';
        $joinTables = '';

        return array(
            'selectFields' => $selectFields,
            'joinTables' => $joinTables
        );
    }
}
