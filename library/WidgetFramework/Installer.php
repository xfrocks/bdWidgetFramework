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
				display_order INT(10) UNSIGNED DEFAULT 1,
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
	}
	
	public static function uninstall() {
		$db = XenForo_Application::get('db');
		
		$db->query("DROP TABLE IF EXISTS `xf_widget`");
		$db->query("DROP TABLE IF EXISTS `xf_widget_cached`");
	}
}