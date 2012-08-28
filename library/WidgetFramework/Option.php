<?php

class WidgetFramework_Option {
	
	public static function get($key) {
		$options = XenForo_Application::get('options');
		
		switch ($key) {
			case 'cacheCutoffDays': return 7;
		}
		
		return $options->get('WidgetFramework_' . $key);
	}
	
}