<?php
class WidgetFramework_Installer {
	public static function install() {
		$db = XenForo_Application::get('db');
		
		$db->query("
			CREATE TABLE IF NOT EXISTS `xf_widget` (
				widget_id INT(10) UNSIGNED AUTO_INCREMENT,
				title VARCHAR(75) DEFAULT NULL,
				class VARCHAR(75) NOT NULL,
				options MEDIUMBLOB,
				position VARCHAR(50) NOT NULL,
				display_order INT(11) DEFAULT 0,
				active TINYINT(3) UNSIGNED DEFAULT 1,
				PRIMARY KEY (widget_id)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
		");
		
		$anything = $db->fetchOne("SELECT COUNT(*) FROM `xf_widget`");
		if (empty($anything)) {
			$db->query("
				INSERT INTO `xf_widget`
				(title, class, options, position, display_order)
				VALUES
				('', 'WidgetFramework_WidgetRenderer_Empty', 0x613A303A7B7D, 'forum_list', '0')
				,('Staff Online Now', 'WidgetFramework_WidgetRenderer_OnlineStaff', 0x613A303A7B7D, 'forum_list', '0')
				,('Users Online Now', 'WidgetFramework_WidgetRenderer_OnlineUsers', 0x613A303A7B7D, 'forum_list', '0')
				,('Forum Statistics', 'WidgetFramework_WidgetRenderer_Stats', 0x613A303A7B7D, 'forum_list', '0')
				,('', 'WidgetFramework_WidgetRenderer_ShareThisPage', 0x613A303A7B7D, 'forum_list', '0')
			");
			
			// since 1.5
			$db->query("
				INSERT INTO `xf_widget`
				(title, class, options, position, display_order)
				VALUES
				('', 'WidgetFramework_WidgetRenderer_Empty', 0x613A303A7B7D, 'member_list', '0')
				,('Find Member', 'WidgetFramework_WidgetRenderer_UsersFind', 0x613A303A7B7D, 'member_list', '0')
				,('Highest-Posting Members', 'WidgetFramework_WidgetRenderer_Users', 0x613A373A7B733A353A226C696D6974223B693A31323B733A353A226F72646572223B733A31333A226D6573736167655F636F756E74223B733A393A22646972656374696F6E223B733A343A2244455343223B733A31313A22646973706C61794D6F6465223B733A31363A226176617461724F6E6C79426967676572223B733A393A227461625F67726F7570223B733A303A22223B733A31303A2265787072657373696F6E223B733A303A22223B733A31363A2265787072657373696F6E5F6465627567223B693A303B7D, 'member_list', '0')
				,('Newest Members', 'WidgetFramework_WidgetRenderer_Users', 0x613A373A7B733A353A226C696D6974223B693A383B733A353A226F72646572223B733A31333A2272656769737465725F64617465223B733A393A22646972656374696F6E223B733A343A2244455343223B733A31313A22646973706C61794D6F6465223B733A31363A226176617461724F6E6C79426967676572223B733A393A227461625F67726F7570223B733A303A22223B733A31303A2265787072657373696F6E223B733A303A22223B733A31363A2265787072657373696F6E5F6465627567223B693A303B7D, 'member_list', '0')
			");
			
			XenForo_Model::create('WidgetFramework_Model_Widget')->buildCache();
		}
		
		// support longer position
		// since 1.0.9
		$db->query("ALTER TABLE `xf_widget` MODIFY COLUMN `position` TEXT");
		
		// cache by permission id
		// since 1.0.9
		// removed in 1.3
		/*
		$db->query("
			CREATE TABLE IF NOT EXISTS `xf_widget_cached` (
				data_id INT(10) UNSIGNED,
				data MEDIUMBLOB,
				PRIMARY KEY (data_id)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
		");
		*/
		$db->query("DROP TABLE IF EXISTS `xf_widget_cached`");
		
		// clear cache (in db only)
		$db->query("DELETE FROM xf_data_registry WHERE data_key LIKE 'wf%'");
		
		// add template for hooks support in widget
		// since 2.0
		if (!$db->fetchOne("SHOW COLUMNS FROM `xf_widget` LIKE 'template_for_hooks'")) {
			$db->query("ALTER TABLE `xf_widget` ADD COLUMN `template_for_hooks` MEDIUMBLOB");
		}
		
		// support negative display order
		// since 2.0
		$db->query("ALTER TABLE `xf_widget` MODIFY COLUMN `display_order` INT(11) DEFAULT 0");
	}
	
	public static function uninstall() {
		$db = XenForo_Application::get('db');
		
		$db->query("DROP TABLE IF EXISTS `xf_widget`");
		$db->query("DROP TABLE IF EXISTS `xf_widget_cached`");
	}
}