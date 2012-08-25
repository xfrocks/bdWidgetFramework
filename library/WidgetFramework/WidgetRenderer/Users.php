<?php
class WidgetFramework_WidgetRenderer_Users extends WidgetFramework_WidgetRenderer {
	protected function _getConfiguration() {
		return array(
			'name' => 'Users',
			'options' => array(
				'limit' => XenForo_Input::UINT,
				'order' => XenForo_Input::STRING,
				'direction' => XenForo_Input::STRING,
			),
			'useCache' => true,
		);
	}
	
	protected function _getOptionsTemplate() {
		return 'wf_widget_options_users';
	}
	
	protected function _validateOptionValue($optionKey, &$optionValue) {
		if ('limit' == $optionKey) {
			if (empty($optionValue)) $optionValue = 5;
		}
		
		return true;
	}
	
	protected function _getRenderTemplate(array $widget, $positionCode, array $params) {
		return 'wf_widget_users';
	}
	
	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject) {
		$userModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_User');
		$conditions = array();
		$fetchOptions = array(
			'limit' => $widget['options']['limit'],
			'order' => $widget['options']['order'],
			'direction' => $widget['options']['direction'],
		);
		$users = $userModel->getUsers($conditions, $fetchOptions);

		$renderTemplateObject->setParam('users', $users);
		
		return $renderTemplateObject->render();
	}
}