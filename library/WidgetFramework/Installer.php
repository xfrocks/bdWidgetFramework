<?php
class WidgetFramework_Installer
{

	/* Start auto-generated lines of code. Change made will be overwriten... */

	protected static $_tables = array(
		'widget_page' => array(
			'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_widgetframework_widget_page` (
				`node_id` INT(10) UNSIGNED NOT NULL
				,`widgets` MEDIUMBLOB
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
				,`display_order` INT(11) NOT NULL DEFAULT \'0\'
				,`active` INT(10) UNSIGNED NOT NULL DEFAULT \'1\'
				,`options` MEDIUMBLOB
				,`template_for_hooks` MEDIUMBLOB
				, PRIMARY KEY (`widget_id`)
				
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
			'dropQuery' => 'DROP TABLE IF EXISTS `xf_widget`',
		),
	);
	protected static $_patches = array(
		array(
			'table' => 'xf_widget',
			'field' => 'template_for_hooks',
			'showTablesQuery' => 'SHOW TABLES LIKE \'xf_widget\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `xf_widget` LIKE \'template_for_hooks\'',
			'alterTableAddColumnQuery' => 'ALTER TABLE `xf_widget` ADD COLUMN `template_for_hooks` MEDIUMBLOB',
			'alterTableDropColumnQuery' => 'ALTER TABLE `xf_widget` DROP COLUMN `template_for_hooks`',
		),
		array(
			'table' => 'xf_widget',
			'field' => 'widget_page_id',
			'showTablesQuery' => 'SHOW TABLES LIKE \'xf_widget\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `xf_widget` LIKE \'widget_page_id\'',
			'alterTableAddColumnQuery' => 'ALTER TABLE `xf_widget` ADD COLUMN `widget_page_id` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'',
			'alterTableDropColumnQuery' => 'ALTER TABLE `xf_widget` DROP COLUMN `widget_page_id`',
		),
	);

	public static function install($existingAddOn, $addOnData)
	{
		$db = XenForo_Application::get('db');

		foreach (self::$_tables as $table)
		{
			$db->query($table['createQuery']);
		}

		foreach (self::$_patches as $patch)
		{
			$tableExisted = $db->fetchOne($patch['showTablesQuery']);
			if (empty($tableExisted))
			{
				continue;
			}

			$existed = $db->fetchOne($patch['showColumnsQuery']);
			if (empty($existed))
			{
				$db->query($patch['alterTableAddColumnQuery']);
			}
		}

		self::installCustomized($existingAddOn, $addOnData);
	}

	public static function uninstall()
	{
		$db = XenForo_Application::get('db');

		foreach (self::$_patches as $patch)
		{
			$tableExisted = $db->fetchOne($patch['showTablesQuery']);
			if (empty($tableExisted))
			{
				continue;
			}

			$existed = $db->fetchOne($patch['showColumnsQuery']);
			if (!empty($existed))
			{
				$db->query($patch['alterTableDropColumnQuery']);
			}
		}

		foreach (self::$_tables as $table)
		{
			$db->query($table['dropQuery']);
		}

		self::uninstallCustomized();
	}

	/* End auto-generated lines of code. Feel free to make changes below */

	private static function installCustomized($existingAddOn, $addOnData)
	{
		$db = XenForo_Application::getDb();

		$effectiveVersionId = 0;

		if (empty($existingAddOn))
		{
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

			if (XenForo_Application::$versionId > 1040000)
			{
				$db->query("
					INSERT INTO `xf_widget`
						(title, class, options, position, display_order)
					VALUES
						('', 'WidgetFramework_WidgetRenderer_ProfilePosts', 'a:1:{s:16:\"show_update_form\";s:1:\"1\";}', 'forum_list', 30)
				");
			}

			if (XenForo_Application::$versionId < 1020000)
			{
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
			}
			else
			{
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

			XenForo_Model::create('WidgetFramework_Model_Widget')->buildCache();
		}
		else
		{
			$effectiveVersionId = $existingAddOn['version_id'];
		}

		if ($effectiveVersionId < 55)
		{
			// node type definition
			// since 2.3
			$db->insert('xf_node_type', array(
				'node_type_id' => 'WF_WidgetPage',
				'handler_class' => 'WidgetFramework_NodeHandler_WidgetPage',
				'controller_admin_class' => 'WidgetFramework_ControllerAdmin_WidgetPage',
				'datawriter_class' => 'WidgetFramework_DataWriter_WidgetPage',
				'permission_group_id' => 'wfWidgetPage',
				'moderator_interface_group_id' => '',
				'public_route_prefix' => 'widget-pages',
			));
			XenForo_Model::create('XenForo_Model_Node')->rebuildNodeTypeCache();
		}

		if ($effectiveVersionId > 0 AND $effectiveVersionId < 74)
		{
			// change definition for widget.title and widget.class
			// since 2.4.4
			$db->query("ALTER TABLE `xf_widget` MODIFY COLUMN `title` TEXT");
			$db->query("ALTER TABLE `xf_widget` MODIFY COLUMN `class` TEXT NOT NULL");
		}
	}

	private static function uninstallCustomized()
	{
		$db = XenForo_Application::getDb();

		$db->query("DROP TABLE IF EXISTS `xf_widget`");
		$db->query("DROP TABLE IF EXISTS `xf_widget_cached`");
		$db->query("DELETE FROM `xf_data_registry` WHERE data_key LIKE '" . WidgetFramework_Model_Cache::CACHED_WIDGETS_BY_PCID_PREFIX . "%'");
		$db->query("DELETE FROM `xf_node_type` WHERE `node_type_id` = 'WF_WidgetPage'");
		$db->query("DELETE FROM `xf_node` WHERE `node_type_id` = 'WF_WidgetPage'");

		XenForo_Application::setSimpleCacheData(WidgetFramework_Helper_Index::SIMPLE_CACHE_CHILD_NODES, false);
		XenForo_Application::setSimpleCacheData(WidgetFramework_Model_Cache::INVALIDED_CACHE_ITEM_NAME, false);
		XenForo_Application::setSimpleCacheData(WidgetFramework_Model_Widget::SIMPLE_CACHE_KEY, false);
	}

}
