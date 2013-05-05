<?php
class WidgetFramework_DevHelper_Config extends DevHelper_Config_Base {
	protected $_dataClasses = array(
		'widget_page' => array(
			'name' => 'widget_page',
			'camelCase' => 'WidgetPage',
			'camelCasePlural' => 'WidgetPages',
			'camelCaseWSpace' => 'Widget Page',
			'fields' => array(
				'node_id' => array('name' => 'node_id', 'type' => 'uint', 'required' => true),
				'widgets' => array('name' => 'widgets', 'type' => 'serialized'),
				'options' => array('name' => 'options', 'type' => 'serialized')
			),
			'phrases' => array(),
			'id_field' => 'node_id',
			'title_field' => false,
			'primaryKey' => array('node_id'),
			'indeces' => array(),
			'files' => array(
				'data_writer' => array('className' => 'WidgetFramework_DataWriter_WidgetPage', 'hash' => '8519e858a701bac5ff2aa3ac65c77246'),
				'model' => array('className' => 'WidgetFramework_Model_WidgetPage', 'hash' => '7a86d6ed21bf412fef03eaa479c2dcc6'),
				'route_prefix_admin' => false,
				'controller_admin' => false
			)
		),
		'widget' => array(
			'name' => 'xf_widget',
			'camelCase' => 'Widget',
			'camelCasePlural' => false,
			'camelCaseWSpace' => 'Widget',
			'fields' => array(
				'widget_id' => array('name' => 'widget_id', 'type' => 'uint', 'autoIncrement' => true),
				'title' => array('name' => 'title', 'type' => 'string', 'length' => '75'),
				'class' => array('name' => 'class', 'type' => 'string', 'length' => '75', 'required' => true),
				'position' => array('name' => 'position', 'type' => 'string'),
				'display_order' => array('name' => 'display_order', 'type' => 'int', 'required' => true, 'default' => 0),
				'active' => array('name' => 'active', 'type' => 'uint', 'required' => true, 'default' => 1),
				'options' => array('name' => 'options', 'type' => 'serialized'),
				'template_for_hooks' => array('name' => 'template_for_hooks', 'type' => 'serialized')
			),
			'phrases' => array(),
			'id_field' => 'widget_id',
			'title_field' => 'title',
			'primaryKey' => array('widget_id'),
			'indeces' => array(),
			'files' => array('data_writer' => false, 'model' => false, 'route_prefix_admin' => false, 'controller_admin' => false)
		)
	);
	protected $_dataPatches = array(
		'xf_widget' => array(
			'widget_page_id' => array('name' => 'widget_page_id', 'type' => 'uint', 'required' => true, 'default' => 0)
		)
	);
	protected $_exportPath = '/Users/sondh/Dropbox/XenForo/WidgetFramework';
	protected $_exportIncludes = array();
	
	/**
	 * Return false to trigger the upgrade!
	 * common use methods:
	 * 	public function addDataClass($name, $fields = array(), $primaryKey = false, $indeces = array())
	 *	public function addDataPatch($table, array $field)
	 *	public function setExportPath($path)
	**/
	protected function _upgrade() {
		return true; // remove this line to trigger update
		
		/*
		$this->addDataClass(
			'name_here',
			array( // fields
				'field_here' => array(
					'type' => 'type_here',
					// 'length' => 'length_here',
					// 'required' => true,
					// 'allowedValues' => array('value_1', 'value_2'), 
					// 'default' => 0,
					// 'autoIncrement' => true,
				),
				// other fields go here
			),
			'primary_key_field_here',
			array( // indeces
				array(
					'fields' => array('field_1', 'field_2'),
					'type' => 'NORMAL', // UNIQUE or FULLTEXT
				),
			),
		);
		*/
	}
}