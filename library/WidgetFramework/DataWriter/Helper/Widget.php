<?php
class WidgetFramework_DataWriter_Helper_Widget {
	public static function verifyWidgetId($widget_id, XenForo_DataWriter $dw, $fieldName = false) {
		$model = XenForo_Model::create('WidgetFramework_Model_Widget');
		$widget = $model->getWidgetById($widget_id);
		if (empty($widget)) {
			$dw->error(new XenForo_Phrase('wf_requested_widget_not_found'), $fieldName);
		} else {
			return true;
		}
	}
	
	public static function verifyClass($class, XenForo_DataWriter $dw, $fieldName= false) {
		$widgetRenderer = WidgetFramework_Core::getRenderer($class);
		if (empty($widgetRenderer)) {
			$dw->error(new XenForo_Phrase('wf_invalid_widget_renderer_x', array('renderer' => $class)), $fieldName);
		} else {
			return true;
		}
	}
	
	public static function verifyPosition($position, XenForo_DataWriter $dw, $fieldName = false) {
		$position = trim($position);
		
		if (empty($position)) {
			$dw->error(new XenForo_Phrase('wf_position_can_not_be_empty'), $fieldName);
		}
		
		if ('all' == $position) {
			return true;
		}
		
		$templateModel = XenForo_Model::create('XenForo_Model_Template');
		$templates = explode(',', $position);
		$foundAll = array();
		
		foreach ($templates as $template) {
			$template = trim($template);
			if (empty($template)) continue;
			
			$found = $templateModel->getTemplateInStyleByTitle($template);
			if (empty($found)) {
				$dw->error(new XenForo_Phrase('wf_invalid_position_x', array('position' => $position)), $fieldName);
			}
		}
		
		return true;
	}
}
