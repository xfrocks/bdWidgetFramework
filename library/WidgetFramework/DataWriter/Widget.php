<?php

class WidgetFramework_DataWriter_Widget extends XenForo_DataWriter
{
    const EXTRA_DATA_SKIP_REBUILD = 'skipRebuild';
    const EXTRA_DATA_TEMPLATE_FOR_HOOKS = 'templateForHooks';

    const WIDGET_OPTION_ADDON_VERSION_ID = '_addOnVersionId';

    protected $_isDelete = false;

    public function isDelete()
    {
        return $this->_isDelete;
    }

    public function getWidgetOptions($existing = false)
    {
        if ($existing) {
            $options = $this->getExisting('options');
        } else {
            $options = $this->get('options');
        }

        if (is_string($options)) {
            $options = unserialize($options);
        }
        if (!is_array($options)) {
            $options = array();
        }

        return $options;
    }

    public function getWidgetOption($optionKey, $default = null)
    {
        $options = $this->getWidgetOptions();

        if (isset($options[$optionKey])) {
            return $options[$optionKey];
        }

        return $default;
    }

    public function setWidgetOption($optionKey, $optionValue = null)
    {
        $options = $this->getWidgetOptions();

        if (is_array($optionKey)) {
            $options = XenForo_Application::mapMerge($options, $optionKey);
        } elseif ($optionValue !== null) {
            $options[$optionKey] = $optionValue;
        } elseif (isset($options[$optionKey])) {
            unset($options[$optionKey]);
        }

        $this->set('options', $options);
    }

    protected function _rebuildGlobalCache()
    {
        if ($this->getExtraData(self::EXTRA_DATA_SKIP_REBUILD)) {
            return;
        }

        if ($this->get('widget_page_id') == 0) {
            $this->_getWidgetModel()->buildCache();
        }
    }

    protected function _getFields()
    {
        return array(
            'xf_widget' => array(
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
                'group_id' => array(
                    'type' => self::TYPE_UINT,
                    'default' => 0,
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
            )
        );
    }

    protected function _getExistingData($data)
    {
        if (!$id = $this->_getExistingPrimaryKey($data, 'widget_id')) {
            return false;
        }

        return array('xf_widget' => $this->_getWidgetModel()->getWidgetById($id));
    }

    protected function _preSave()
    {
        $this->_bumpAddOnVersionId();

        $templateForHooks = $this->getExtraData(self::EXTRA_DATA_TEMPLATE_FOR_HOOKS);
        if ($templateForHooks !== null) {
            // this extra data has been set somehow
            $this->set('template_for_hooks', $templateForHooks);
        }

        if ($this->isChanged('class')
            && $this->getExisting('class') === 'WidgetFramework_WidgetGroup'
        ) {
            $this->error(new XenForo_Phrase('wf_widget_class_cannot_changed_from_group'), 'class');
        }

        if ($this->get('class') === 'WidgetFramework_WidgetGroup') {
            if (!$this->get('active')) {
                $this->error(new XenForo_Phrase('wf_widget_group_must_be_active'), 'active');
            }
        }
    }

    protected function _postSave()
    {
        $this->_insertOrUpdateMasterPhrase(
            $this->_getWidgetModel()->getWidgetTitlePhrase($this->get('widget_id')),
            $this->get('title'),
            '',
            array('global_cache' => 1)
        );

        if ($this->_isTemplateWidget($this->get('class'))) {
            $this->_getWidgetRendererTemplateModel()->dwPostSave(
                $this->getMergedData(),
                $this->getWidgetOptions()
            );
        } elseif ($this->isChanged('class')
            && $this->_isTemplateWidget($this->getExisting('class'))
        ) {
            $this->_getWidgetRendererTemplateModel()->dwPostDelete($this->getMergedExistingData());
        }

        if (!empty($this->_newData['xf_widget'])) {
            WidgetFramework_Helper_LayoutEditor::keepWidgetChanges(
                $this->get('widget_id'), $this, $this->_newData['xf_widget']);
        }
    }

    protected function _postSaveAfterTransaction()
    {
        $this->_rebuildGlobalCache();

        WidgetFramework_Core::clearCachedWidgetById($this->get('widget_id'));
    }

    protected function _postDelete()
    {
        $this->_deleteMasterPhrase($this->_getWidgetModel()->getWidgetTitlePhrase($this->get('widget_id')));

        if ($this->_isTemplateWidget($this->get('class'))) {
            $this->_getWidgetRendererTemplateModel()->dwPostDelete($this->getMergedData());
        }

        if ($this->get('class') === 'WidgetFramework_WidgetGroup') {
            // reassign widgets of this group to its parent
            $widgets = $this->_getWidgetModel()->getWidgets(array('group_id' => $this->get('widget_id')));
            foreach ($widgets as $widget) {
                /** @var WidgetFramework_DataWriter_Widget $dw */
                $dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
                $dw->setExistingData($widget, true);
                $dw->set('group_id', $this->get('group_id'));
                $dw->setExtraData(self::EXTRA_DATA_SKIP_REBUILD, true);
                $dw->save();
            }
        }

        $this->_rebuildGlobalCache();

        WidgetFramework_Core::clearCachedWidgetById($this->get('widget_id'));

        $this->_isDelete = true;

        WidgetFramework_Helper_LayoutEditor::keepWidgetChanges($this->get('widget_id'), $this);
    }

    protected function _getUpdateCondition($tableName)
    {
        return 'widget_id = ' . $this->_db->quote($this->getExisting('widget_id'));
    }

    protected function _bumpAddOnVersionId()
    {
        if (XenForo_Application::$versionId < 1020000) {
            return;
        }

        $addOns = XenForo_Application::get('addOns');
        if (empty($addOns['widget_framework'])) {
            return;
        }

        $optionValue = $this->getWidgetOption(self::WIDGET_OPTION_ADDON_VERSION_ID);
        if (empty($optionValue)
            || $optionValue != $addOns['widget_framework']
        ) {
            $this->setWidgetOption(self::WIDGET_OPTION_ADDON_VERSION_ID, $addOns['widget_framework']);
        }
    }

    protected function _isTemplateWidget($class)
    {
        return in_array($class, array(
            'WidgetFramework_WidgetRenderer_Html',
            'WidgetFramework_WidgetRenderer_HtmlWithoutWrapper',
            'WidgetFramework_WidgetRenderer_Template',
            'WidgetFramework_WidgetRenderer_TemplateWithoutWrapper',
        ), true);
    }

    /**
     * @return WidgetFramework_Model_Widget
     */
    protected function _getWidgetModel()
    {
        return $this->getModelFromCache('WidgetFramework_Model_Widget');
    }

    /**
     * @return WidgetFramework_Model_WidgetRenderer_Template
     */
    protected function _getWidgetRendererTemplateModel()
    {
        return $this->getModelFromCache('WidgetFramework_Model_WidgetRenderer_Template');
    }

}
