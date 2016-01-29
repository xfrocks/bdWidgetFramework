<?php

class WidgetFramework_Installer
{

    /* Start auto-generated lines of code. Change made will be overwriten... */

    protected static $_tables = array(
        'widget_page' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_widgetframework_widget_page` (
                `node_id` INT(10) UNSIGNED NOT NULL
                ,`options` MEDIUMBLOB
                , PRIMARY KEY (`node_id`)
                
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_widgetframework_widget_page`',
        ),
        'xf_widget' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_widget` (
                `widget_id` INT(10) UNSIGNED AUTO_INCREMENT
                ,`title` TEXT
                ,`class` TEXT NOT NULL
                ,`position` TEXT
                ,`group_id` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'
                ,`display_order` INT(11) NOT NULL DEFAULT \'0\'
                ,`active` INT(10) UNSIGNED NOT NULL DEFAULT \'1\'
                ,`options` MEDIUMBLOB
                ,`template_for_hooks` MEDIUMBLOB
                , PRIMARY KEY (`widget_id`)
                
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_widget`',
        ),
        'cache' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_widgetframework_cache` (
                `cache_id` VARCHAR(255) NOT NULL
                ,`cache_date` INT(10) UNSIGNED NOT NULL
                ,`data` MEDIUMBLOB
                , PRIMARY KEY (`cache_id`)
                
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_widgetframework_cache`',
        ),
    );
    protected static $_patches = array(
        array(
            'table' => 'xf_widget',
            'tableCheckQuery' => 'SHOW TABLES LIKE \'xf_widget\'',
            'field' => 'template_for_hooks',
            'checkQuery' => 'SHOW COLUMNS FROM `xf_widget` LIKE \'template_for_hooks\'',
            'addQuery' => 'ALTER TABLE `xf_widget` ADD COLUMN `template_for_hooks` MEDIUMBLOB',
            'dropQuery' => 'ALTER TABLE `xf_widget` DROP COLUMN `template_for_hooks`',
        ),
        array(
            'table' => 'xf_widget',
            'tableCheckQuery' => 'SHOW TABLES LIKE \'xf_widget\'',
            'field' => 'widget_page_id',
            'checkQuery' => 'SHOW COLUMNS FROM `xf_widget` LIKE \'widget_page_id\'',
            'addQuery' => 'ALTER TABLE `xf_widget` ADD COLUMN `widget_page_id` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'',
            'dropQuery' => 'ALTER TABLE `xf_widget` DROP COLUMN `widget_page_id`',
        ),
        array(
            'table' => 'xf_widget',
            'tableCheckQuery' => 'SHOW TABLES LIKE \'xf_widget\'',
            'field' => 'group_id',
            'checkQuery' => 'SHOW COLUMNS FROM `xf_widget` LIKE \'group_id\'',
            'addQuery' => 'ALTER TABLE `xf_widget` ADD COLUMN `group_id` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'',
            'dropQuery' => 'ALTER TABLE `xf_widget` DROP COLUMN `group_id`',
        ),
    );

    public static function install($existingAddOn, $addOnData)
    {
        $db = XenForo_Application::get('db');

        foreach (self::$_tables as $table) {
            $db->query($table['createQuery']);
        }

        foreach (self::$_patches as $patch) {
            $tableExisted = $db->fetchOne($patch['tableCheckQuery']);
            if (empty($tableExisted)) {
                continue;
            }

            $existed = $db->fetchOne($patch['checkQuery']);
            if (empty($existed)) {
                $db->query($patch['addQuery']);
            }
        }

        self::installCustomized($existingAddOn, $addOnData);
    }

    public static function uninstall()
    {
        $db = XenForo_Application::get('db');

        foreach (self::$_patches as $patch) {
            $tableExisted = $db->fetchOne($patch['tableCheckQuery']);
            if (empty($tableExisted)) {
                continue;
            }

            $existed = $db->fetchOne($patch['checkQuery']);
            if (!empty($existed)) {
                $db->query($patch['dropQuery']);
            }
        }

        foreach (self::$_tables as $table) {
            $db->query($table['dropQuery']);
        }

        self::uninstallCustomized();
    }

    /* End auto-generated lines of code. Feel free to make changes below */

    public static function installCustomized(
        /** @noinspection PhpUnusedParameterInspection */
        $existingAddOn,
        $addOnData
    ) {
        $db = XenForo_Application::getDb();

        $effectiveVersionId = 0;

        if (empty($existingAddOn)) {
            $db->query("
				INSERT INTO `xf_widget`
					(title, class, options, position, display_order)
				VALUES
					('', 'WidgetFramework_WidgetRenderer_Empty', 0x613A303A7B7D, 'forum_list', 10),
					('', 'WidgetFramework_WidgetRenderer_OnlineStaff', 0x613A303A7B7D, 'forum_list', 20),
					('', 'WidgetFramework_WidgetRenderer_OnlineUsers', 0x613A303A7B7D, 'forum_list', 30),
					('', 'WidgetFramework_WidgetRenderer_Stats', 0x613A303A7B7D, 'forum_list', 40),
					('', 'WidgetFramework_WidgetRenderer_ShareThisPage', 0x613A303A7B7D, 'forum_list', 50)
			");

            if (XenForo_Application::$versionId > 1040000) {
                $db->query("
					INSERT INTO `xf_widget`
						(title, class, options, position, display_order)
					VALUES
						('', 'WidgetFramework_WidgetRenderer_ProfilePosts', 'a:1:{s:16:\"show_update_form\";s:1:\"1\";}', 'forum_list', 30)
				");
            }

            if (XenForo_Application::$versionId > 1050000) {
                $db->query("
					INSERT INTO `xf_widget`
						(title, class, options, position, display_order)
					VALUES
						('', 'WidgetFramework_WidgetRenderer_Threads', 'a:2:{s:4:\"type\";s:6:\"recent\";s:5:\"limit\";i:5;}', 'forum_list', 20)
				");
            }

            if (XenForo_Application::$versionId < 1020000) {
                $db->query("
					INSERT INTO `xf_widget`
						(title, class, options, position, display_order)
					VALUES
						('', 'WidgetFramework_WidgetRenderer_Empty', 0x613A303A7B7D, 'member_list', 10),
						('', 'WidgetFramework_WidgetRenderer_UsersFind', 0x613A303A7B7D, 'member_list', 20),
						('Highest-Posting Members', 'WidgetFramework_WidgetRenderer_Users', 0x613A373A7B733A353A226C696D6974223B693A31323B733A353A226F72646572223B733A31333A226D6573736167655F636F756E74223B733A393A22646972656374696F6E223B733A343A2244455343223B733A31313A22646973706C61794D6F6465223B733A31363A226176617461724F6E6C79426967676572223B733A393A227461625F67726F7570223B733A303A22223B733A31303A2265787072657373696F6E223B733A303A22223B733A31363A2265787072657373696F6E5F6465627567223B693A303B7D, 'member_list', 30),
						('Newest Members', 'WidgetFramework_WidgetRenderer_Users', 0x613A373A7B733A353A226C696D6974223B693A383B733A353A226F72646572223B733A31333A2272656769737465725F64617465223B733A393A22646972656374696F6E223B733A343A2244455343223B733A31313A22646973706C61794D6F6465223B733A31363A226176617461724F6E6C79426967676572223B733A393A227461625F67726F7570223B733A303A22223B733A31303A2265787072657373696F6E223B733A303A22223B733A31363A2265787072657373696F6E5F6465627567223B693A303B7D, 'member_list', 40),
						('', 'WidgetFramework_WidgetRenderer_FacebookFacepile', 0x613A303A7B7D, 'member_list', 50)
				");
            } else {
                $db->query("
					INSERT INTO `xf_widget`
						(title, class, options, position, display_order)
					VALUES
						('', 'WidgetFramework_WidgetRenderer_Empty', 0x613A303A7B7D, 'member_notable', 10),
						('', 'WidgetFramework_WidgetRenderer_UsersFind', 0x613A303A7B7D, 'member_notable', 20),
						('', 'WidgetFramework_WidgetRenderer_Birthday', 0x613A303A7B7D, 'member_notable', 30),
						('', 'WidgetFramework_WidgetRenderer_UsersStaff', 0x613A303A7B7D, 'member_notable', 40),
						('', 'WidgetFramework_WidgetRenderer_FacebookFacepile', 0x613A303A7B7D, 'member_notable', 50)
				");
            }

            /** @var WidgetFramework_Model_Widget $widgetModel */
            $widgetModel = XenForo_Model::create('WidgetFramework_Model_Widget');
            $widgetModel->buildCache();
        } else {
            $effectiveVersionId = $existingAddOn['version_id'];
        }

        if ($effectiveVersionId < 55) {
            // node type definition
            // since 2.3
            // new XenForo_Phrase('node_type_WF_WidgetPage')
            $db->insert('xf_node_type', array(
                'node_type_id' => 'WF_WidgetPage',
                'handler_class' => 'WidgetFramework_NodeHandler_WidgetPage',
                'controller_admin_class' => 'WidgetFramework_ControllerAdmin_WidgetPage',
                'datawriter_class' => 'WidgetFramework_DataWriter_WidgetPage',
                'permission_group_id' => 'wfWidgetPage',
                'moderator_interface_group_id' => '',
                'public_route_prefix' => 'widget-pages',
            ));

            /** @var XenForo_Model_Node $nodeModel */
            $nodeModel = XenForo_Model::create('XenForo_Model_Node');
            $nodeModel->rebuildNodeTypeCache();
        }

        if ($effectiveVersionId > 0
            && $effectiveVersionId < 74
        ) {
            // change definition for widget.title and widget.class
            // since 2.4.4
            $db->query("ALTER TABLE `xf_widget` MODIFY COLUMN `title` TEXT");
            $db->query("ALTER TABLE `xf_widget` MODIFY COLUMN `class` TEXT NOT NULL");
        }

        if ($effectiveVersionId > 0) {
            if ($effectiveVersionId <= 102) {
                // update widget within widget pages to use group/display order
                // instead of layout row/column
                self::_updatePositionGroupAndDisplayOrderForWidgetsOfPages();
            }

            if ($effectiveVersionId <= 112) {
                // update widget tab_group option to use group_id instead
                self::_updateWidgetGroupIds();
            }
        }
    }

    public static function uninstallCustomized()
    {
        $db = XenForo_Application::getDb();

        $db->query("DROP TABLE IF EXISTS `xf_widget`");
        $db->query("DROP TABLE IF EXISTS `xf_widget_cached`");
        $db->query("DELETE FROM `xf_data_registry` WHERE data_key LIKE 'wfc_%'");
        $db->query("DELETE FROM `xf_node_type` WHERE `node_type_id` = 'WF_WidgetPage'");
        $db->query("DELETE FROM `xf_node` WHERE `node_type_id` = 'WF_WidgetPage'");

        XenForo_Application::setSimpleCacheData(WidgetFramework_Helper_Index::SIMPLE_CACHE_CHILD_NODES, false);
        XenForo_Application::setSimpleCacheData(WidgetFramework_Model_Cache::INVALIDED_CACHE_ITEM_NAME, false);
        XenForo_Application::setSimpleCacheData(WidgetFramework_Model_Widget::SIMPLE_CACHE_KEY, false);

        WidgetFramework_ShippableHelper_Updater::onUninstall(WidgetFramework_Option::UPDATER_URL, 'widget_framework');
    }

    protected static function _updatePositionGroupAndDisplayOrderForWidgetsOfPages()
    {
        /** @var WidgetFramework_Model_Widget $widgetModel */
        $widgetModel = XenForo_Model::create('WidgetFramework_Model_Widget');
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
                    $widgetDw->set('position', 'wf_widget_page', '', array(
                        'runVerificationCallback' => false,
                    ));

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

        $widgetModel->buildCache();
    }

    protected static function _updateWidgetGroupIds()
    {
        // TODO
    }

}
