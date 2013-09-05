<?php

class WidgetFramework_DataWriter_Widget extends XenForo_DataWriter
{

	const EXTRA_DATA_SKIP_REBUILD = 'skipRebuild';
	const EXTRA_DATA_TEMPLATE_FOR_HOOKS = 'templateForHooks';

	protected function _getFields()
	{
		return array('xf_widget' => array(
				'widget_id' => array(
					'type' => self::TYPE_UINT,
					'autoIncrement' => true,
					'verification' => array(
						'WidgetFramework_DataWriter_Helper_Widget',
						'verifyWidgetId'
					)
				),
				'title' => array(
					'type' => self::TYPE_STRING,
					'default' => ''
				),
				'class' => array(
					'type' => self::TYPE_STRING,
					'required' => true,
					'verification' => array(
						'WidgetFramework_DataWriter_Helper_Widget',
						'verifyClass'
					)
				),
				'options' => array(
					'type' => self::TYPE_SERIALIZED,
					'default' => 'a:0:{}'
				),
				'position' => array(
					'type' => self::TYPE_STRING,
					'verification' => array(
						'WidgetFramework_DataWriter_Helper_Widget',
						'verifyPosition'
					)
				),
				'display_order' => array(
					'type' => self::TYPE_INT,
					'default' => 0
				),
				'active' => array(
					'type' => self::TYPE_BOOLEAN,
					'default' => 1
				),
				'template_for_hooks' => array(
					'type' => self::TYPE_SERIALIZED,
					'default' => 'a:0:{}'
				),
				'widget_page_id' => array(
					'type' => self::TYPE_UINT,
					'default' => 0
				),
			));
	}

	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'widget_id'))
		{
			return false;
		}

		return array('xf_widget' => $this->_getWidgetModel()->getWidgetById($id));
	}

	protected function _preSave()
	{
		$templateForHooks = $this->getExtraData(self::EXTRA_DATA_TEMPLATE_FOR_HOOKS);
		if ($templateForHooks !== null)
		{
			// this extra data has been set somehow
			$this->set('template_for_hooks', $templateForHooks);
		}

		return parent::_preSave();
	}

	protected function _postSaveAfterTransaction()
	{
		if (!$this->getExtraData(self::EXTRA_DATA_SKIP_REBUILD))
		{
			$this->_getWidgetModel()->buildCache();
		}

		WidgetFramework_Core::clearCachedWidgetById($this->get('widget_id'));
	}

	protected function _postDelete()
	{
		if (!$this->getExtraData(self::EXTRA_DATA_SKIP_REBUILD))
		{
			$this->_getWidgetModel()->buildCache();
		}

		WidgetFramework_Core::clearCachedWidgetById($this->get('widget_id'));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'widget_id = ' . $this->_db->quote($this->getExisting('widget_id'));
	}

	/**
	 * @return WidgetFramework_Model_Widget
	 */
	protected function _getWidgetModel()
	{
		return $this->getModelFromCache('WidgetFramework_Model_Widget');
	}

}
