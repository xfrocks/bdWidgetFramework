<?php
class WidgetFramework_DataWriter_Widget extends XenForo_DataWriter {
	protected function _getFields() {
		return array(
			'xf_widget' => array(
				'widget_id' => array('type' => self::TYPE_UINT, 'autoIncrement' => true,
					'verification' => array('WidgetFramework_DataWriter_Helper_Widget', 'verifyWidgetId')),
				'title' => array('type' => self::TYPE_STRING, 'default' => ''),
				'class' => array('type' => self::TYPE_STRING, 'required' => true,
					'verification' => array('WidgetFramework_DataWriter_Helper_Widget', 'verifyClass')),
				'options' => array('type' => self::TYPE_SERIALIZED, 'default' => ''),
				'position' => array('type' => self::TYPE_STRING, 'required' => true,
					'verification' => array('WidgetFramework_DataWriter_Helper_Widget', 'verifyPosition')),
				'display_order' => array('type' => self::TYPE_UINT, 'default' => 1),
				'active' => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
			)
		);
	}

	protected function _getExistingData($data) {
		if (!$id = $this->_getExistingPrimaryKey($data, 'widget_id')) {
			return false;
		}

		return array('xf_widget' => $this->_getWidgetModel()->getWidgetById($id));
	}
	
	protected function _postSaveAfterTransaction() {
		$this->_getWidgetModel()->buildCache();
		WidgetFramework_Core::clearCachedWidgetById($this->get('widget_id'));
	}
	
	protected function _postDelete() {
		$this->_getWidgetModel()->buildCache();
		WidgetFramework_Core::clearCachedWidgetById($this->get('widget_id'));
	}

	protected function _getUpdateCondition($tableName) {
		return 'widget_id = ' . $this->_db->quote($this->getExisting('widget_id'));
	}

	protected function _getWidgetModel() {
		return $this->getModelFromCache('WidgetFramework_Model_Widget');
	}
}