<?php
class WidgetFramework_Listener {
	public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data) {
		if ($dependencies instanceof XenForo_Dependencies_Public) {
			// we only boot up if we are in the front end
			WidgetFramework_Core::getInstance()->bootstrap();
		}
	}
	
	public static function template_create(&$templateName, array &$params, XenForo_Template_Abstract $template) {
		if (defined('WIDGET_FRAMEWORK_LOADED') AND $template instanceof XenForo_Template_Public) {
			// we only work if the framework is ready AND this template is a public template
			WidgetFramework_Core::getInstance()->prepareWidgetsFor($templateName, $params, $template);
		}
	}
	
	public static function template_post_render($templateName, &$output, array &$containerData, XenForo_Template_Abstract $template) {
		if (defined('WIDGET_FRAMEWORK_LOADED') AND $template instanceof XenForo_Template_Public) {
			// we only work if the framework is ready AND this template is a public template
			WidgetFramework_Core::getInstance()->renderWidgetsFor($templateName, $template->getParams(), $template, $containerData);
		}
	}
	
	public static function front_controller_post_view(XenForo_FrontController $fc, &$output) {
		if (defined('WIDGET_FRAMEWORK_LOADED')) {
			WidgetFramework_Core::getInstance()->shutdown();
		}
	}
	
	public static function load_class($class, array &$extend) {
		static $classesNeedsExtending = array(
			'XenForo_ControllerPublic_Thread',
		
			'XenForo_DataWriter_Discussion_Thread',
			'XenForo_DataWriter_DiscussionMessage_Post',
			'XenForo_DataWriter_DiscussionMessage_ProfilePost',
			'XenForo_DataWriter_User',
			
			'XenForo_Model_Thread',
			'XenForo_Model_User',
		);
		
		if (in_array($class, $classesNeedsExtending)) {
			$extend[] = str_replace('XenForo_', 'WidgetFramework_Extend_', $class);
		}
	}
	
	public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes) {
		$ourHashes = WidgetFramework_FileSums::getHashes();
		$hashes = array_merge($hashes, $ourHashes);
	}
}