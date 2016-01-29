<?php

class WidgetFramework_DevHelper_Config extends DevHelper_Config_Base
{
    protected $_dataClasses = array(
        'widget_page' => array(
            'name' => 'widget_page',
            'camelCase' => 'WidgetPage',
            'camelCasePlural' => 'WidgetPages',
            'camelCaseWSpace' => 'Widget Page',
            'fields' => array(
                'node_id' => array('name' => 'node_id', 'type' => 'uint', 'required' => true),
                'options' => array('name' => 'options', 'type' => 'serialized'),
            ),
            'phrases' => array(),
            'id_field' => 'node_id',
            'title_field' => false,
            'primaryKey' => array('node_id'),
            'indeces' => array(),
            'files' => array(
                'data_writer' => array(
                    'className' => 'WidgetFramework_DataWriter_WidgetPage',
                    'hash' => '8519e858a701bac5ff2aa3ac65c77246'
                ),
                'model' => array(
                    'className' => 'WidgetFramework_Model_WidgetPage',
                    'hash' => '7a86d6ed21bf412fef03eaa479c2dcc6'
                ),
                'route_prefix_admin' => false,
                'controller_admin' => false,
            ),
        ),
        'widget' => array(
            'name' => 'xf_widget',
            'camelCase' => 'Widget',
            'camelCasePlural' => false,
            'camelCaseWSpace' => 'Widget',
            'fields' => array(
                'widget_id' => array('name' => 'widget_id', 'type' => 'uint', 'autoIncrement' => true),
                'title' => array('name' => 'title', 'type' => 'string'),
                'class' => array('name' => 'class', 'type' => 'string', 'required' => true),
                'position' => array('name' => 'position', 'type' => 'string'),
                'group_id' => array('name' => 'group_id', 'type' => 'uint', 'required' => true, 'default' => 0),
                'display_order' => array(
                    'name' => 'display_order',
                    'type' => 'int',
                    'required' => true,
                    'default' => 0
                ),
                'active' => array('name' => 'active', 'type' => 'uint', 'required' => true, 'default' => 1),
                'options' => array('name' => 'options', 'type' => 'serialized'),
                'template_for_hooks' => array('name' => 'template_for_hooks', 'type' => 'serialized'),
            ),
            'phrases' => array(),
            'id_field' => 'widget_id',
            'title_field' => 'title',
            'primaryKey' => array('widget_id'),
            'indeces' => array(),
            'files' => array(
                'data_writer' => false,
                'model' => false,
                'route_prefix_admin' => false,
                'controller_admin' => false
            ),
        ),
        'cache' => array(
            'name' => 'cache',
            'camelCase' => 'Cache',
            'camelCasePlural' => 'Caches',
            'camelCaseWSpace' => 'Cache',
            'camelCasePluralWSpace' => 'Caches',
            'fields' => array(
                'cache_id' => array('name' => 'cache_id', 'type' => 'string', 'length' => 255, 'required' => true),
                'cache_date' => array('name' => 'cache_date', 'type' => 'uint', 'required' => true),
                'data' => array('name' => 'data', 'type' => 'serialized'),
            ),
            'phrases' => array(),
            'title_field' => 'hash',
            'primaryKey' => array('cache_id'),
            'indeces' => array(),
            'files' => array(
                'data_writer' => false,
                'model' => false,
                'route_prefix_admin' => false,
                'controller_admin' => false
            ),
        ),
    );
    protected $_dataPatches = array(
        'xf_widget' => array(
            'template_for_hooks' => array('name' => 'template_for_hooks', 'type' => 'serialized'),
            'widget_page_id' => array('name' => 'widget_page_id', 'type' => 'uint', 'required' => true, 'default' => 0),
            'group_id' => array('name' => 'group_id', 'type' => 'uint', 'required' => true, 'default' => 0),
        ),
    );
    protected $_exportPath = '/Users/sondh/XenForo/WidgetFramework';
    protected $_exportIncludes = array();
    protected $_exportExcludes = array();
    protected $_exportAddOns = array();
    protected $_exportStyles = array();
    protected $_options = array();

    /**
     * Return false to trigger the upgrade!
     **/
    protected function _upgrade()
    {
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
            array('primary_key_1', 'primary_key_2'), // or 'primary_key', both are okie
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