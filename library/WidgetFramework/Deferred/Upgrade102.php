<?php

class WidgetFramework_Deferred_Upgrade102 extends XenForo_Deferred_Abstract
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        /** @var WidgetFramework_Model_Widget $widgetModel */
        $widgetModel = XenForo_Model::create('WidgetFramework_Model_Widget');

        $this->_backupWidgets();
        $this->_upgradeWidgetPageWidgets($widgetModel);
        $this->_upgradeOptionTabGroup($widgetModel);

        $widgetModel->buildCache();
    }

    protected function _backupWidgets()
    {
        $backupTableName = 'xf_widget_' . XenForo_Application::$time;
        XenForo_Application::getDb()->query('
            CREATE TABLE IF NOT EXISTS `' . $backupTableName . '`
            SELECT * FROM `xf_widget`
        ');

        XenForo_Error::logError(sprintf(
            'xf_widget table has been backed up to %s during upgrade. '
            . 'Please verify updated widget position and rendering then clean up the backup table afterwards.',
            $backupTableName
        ));
    }

    protected function _upgradeWidgetPageWidgets(WidgetFramework_Model_Widget $widgetModel)
    {
        /** @var WidgetFramework_Model_WidgetPage $widgetPageModel */
        $widgetPageModel = $widgetModel->getModelFromCache('WidgetFramework_Model_WidgetPage');

        $widgetPages = $widgetPageModel->getWidgetPages();

        foreach ($widgetPages as $widgetPage) {
            $widgets = $widgetModel->getWidgets(array('widget_page_id' => $widgetPage['node_id']));

            foreach (array_keys($widgets) as $widgetId) {
                if ($widgets[$widgetId]['position'] == 'sidebar') {
                    // update sidebar widgets
                    $widgetDw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
                    $widgetDw->setImportMode(true);
                    $widgetDw->setExistingData($widgets[$widgetId], true);
                    $widgetDw->set('position', 'wf_widget_page');

                    $widgetDw->save();
                    unset($widgets[$widgetId]);
                } elseif (!empty($widgets[$widgetId]['position'])) {
                    // in older versions, page widgets' positions are either "sidebar" or empty
                    // it looks like this widget has been converted or something, ignore it
                    unset($widgets[$widgetId]);
                }
            }

            if (!empty($widgets)) {
                $widgetsCloned = $widgets;
                WidgetFramework_Helper_OldPageLayout::buildLayoutTree($widgetsCloned);

                foreach (array_keys($widgets) as $widgetId) {
                    // update layout widgets
                    $widgetDw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
                    $widgetDw->setImportMode(true);
                    $widgetDw->setExistingData($widgets[$widgetId], true);
                    $widgetDw->bulkSet($widgetsCloned[$widgetId], array(
                        'runVerificationCallback' => false,
                        'ignoreInvalidFields' => true,
                    ));
                    $widgetDw->save();
                }
            }
        }
    }

    protected function _upgradeOptionTabGroup(WidgetFramework_Model_Widget $widgetModel)
    {
        $widgets = $widgetModel->getWidgets(array('group_id' => 0));
        $tabGroups = array();

        foreach ($widgets as &$widgetRef) {
            if (empty($widgetRef['options']['tab_group'])) {
                continue;
            }

            $tabGroup = array(
                'widget_page_id' => 0,
                'position' => '',
                'tab_group' => '',
            );

            // TODO: support widget with multiple positions
            $tabGroup['position'] = $widgetRef['position'];

            $tabGroup['widget_page_id'] = intval($widgetRef['widget_page_id']);
            $tabGroup['tab_group'] = $widgetRef['options']['tab_group'];
            $md5 = md5(serialize($tabGroup));

            if (!isset($tabGroups[$md5])) {
                $tabGroups[$md5] = $tabGroup;
                $tabGroups[$md5]['widget_ids'] = array();
            }
            $tabGroups[$md5]['widget_ids'][] = $widgetRef['widget_id'];
        }

        foreach ($tabGroups as $tabGroup) {
            if (count($tabGroup['widget_ids']) < 2) {
                continue;
            }

            $firstWidgetId = reset($tabGroup['widget_ids']);
            $firstWidget = $widgets[$firstWidgetId];
            $layout = 'tabs';
            if (strpos($tabGroup['tab_group'], 'random') === 0) {
                $layout = 'random';
            } elseif (strpos($tabGroup['tab_group'], 'column') === 0) {
                $layout = 'columns';
            }

            $groupWidget = $widgetModel->createGroupContaining($firstWidget, array('layout' => $layout));

            foreach ($tabGroup['widget_ids'] as $widgetId) {
                $widgetDw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
                $widgetDw->setImportMode(true);
                $widgetDw->setExistingData($widgets[$widgetId], true);
                $widgetDw->set('group_id', $groupWidget['widget_id']);
                $widgetDw->save();
            }
        }
    }
}
