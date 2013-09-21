<?php

class WidgetFramework_DataWriter_WidgetPage extends XenForo_DataWriter_Node
{

	protected $_existingDataErrorPhrase = 'requested_page_not_found';

	protected function _getFields()
	{
		$fields = parent::_getFields() + array('xf_widgetframework_widget_page' => array(
				'node_id' => array(
					'type' => self::TYPE_UINT,
					'default' => array(
						'xf_node',
						'node_id'
					),
					'required' => true
				),
				'widgets' => array('type' => self::TYPE_SERIALIZED),
				'options' => array('type' => self::TYPE_SERIALIZED),
			));

		$fields['xf_node']['node_name']['required'] = true;
		$fields['xf_node']['node_name']['requiredError'] = 'please_enter_valid_url_portion';

		return $fields;
	}

	protected function _getExistingData($data)
	{
		if (!$nodeId = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		$widgetPage = $this->_getWidgetPageModel()->getWidgetPageById($nodeId);
		if (!$widgetPage)
		{
			return false;
		}

		return $this->getTablesDataFromArray($widgetPage);
	}

	protected function _postDelete()
	{
		if ($this->get('node_id') == WidgetFramework_Option::get('indexNodeId'))
		{
			WidgetFramework_Option::setIndexNodeId(0);
		}

		$this->_deleteWidgets();
	}

	protected function _deleteWidgets()
	{
		$widgets = $this->_getWidgetModel();
		foreach ($widgets as $widget)
		{
			/* @var $dw WidgetFramework_DataWriter_Widget */
			$dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
			$dw->setExistingData($widget, true);
			$dw->delete();
		}
	}

	/**
	 * @return WidgetFramework_Model_Widget
	 */
	protected function _getWidgetModel()
	{
		return $this->getModelFromCache('WidgetFramework_Model_Widget');
	}

	/**
	 * @return WidgetFramework_Model_WidgetPage
	 */
	protected function _getWidgetPageModel()
	{
		return $this->getModelFromCache('WidgetFramework_Model_WidgetPage');
	}

}
