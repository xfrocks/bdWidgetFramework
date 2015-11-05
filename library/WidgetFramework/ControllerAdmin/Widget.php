<?php

class WidgetFramework_ControllerAdmin_Widget extends XenForo_ControllerAdmin_Abstract
{
    protected function _preDispatch($action)
    {
        $this->assertAdminPermission('style');
    }

    protected function _setupSession($action)
    {
        if (!XenForo_Application::isRegistered('session')) {
            if ($this->_noRedirect()
                && $this->_routeMatch->getResponseType() === 'json'
                && $this->_input->filterSingle('_layoutEditor', XenForo_Input::BOOLEAN)
            ) {
                // use public session if the page is being requested within layout editor
                // this poses a slight security risk but UX benefit is tremendous
                // TODO: keep an eye on it
                XenForo_Session::startPublicSession($this->_request);
            }
        }

        parent::_setupSession($action);
    }

    public function actionIndex()
    {
        $widgets = $this->_getWidgetModel()->getGlobalWidgets(false);

        $viewParams = array('widgets' => $widgets);

        return $this->responseView('WidgetFramework_ViewAdmin_Widget_List', 'wf_widget_list', $viewParams);
    }

    public function actionAdd()
    {
        $options = array();

        $position = $this->_input->filterSingle('position', XenForo_Input::STRING);

        $positionWidgetId = $this->_input->filterSingle('position_widget', XenForo_Input::UINT);
        $positionWidget = null;
        if (!empty($positionWidgetId)) {
            $positionWidget = $this->_getWidgetOrError($positionWidgetId);
        }

        $widgetPageId = $this->_input->filterSingle('widget_page_id', XenForo_Input::UINT);
        if (!empty($widgetPageId)) {
            // prepare options for widget of widget page
            $widgetPage = $this->_getWidgetPageOrError($widgetPageId);
        }

        $displayOrder = $this->_input->filterSingle('display_order', XenForo_Input::INT, array('default' => 'na'));
        if ($displayOrder == 'na'
            && !empty($position)
        ) {
            $core = WidgetFramework_Core::getInstance();

            if (empty($widgetPage)) {
                $globalWidgets = $this->_getWidgetModel()->getGlobalWidgets(false, false);
                $core->addWidgets($globalWidgets);
            } else {
                $widgetPageWidgets = $this->_getWidgetModel()->getPageWidgets($widgetPage['node_id'], false);
                $core->addWidgets($widgetPageWidgets);
            }

            $positionWidgetGroups = $core->getWidgetsAtPosition($position);
            $displayOrder = $this->_getWidgetModel()->getLastDisplayOrder($positionWidgetGroups, $positionWidget);
        }

        $widget = array(
            'active' => 1,
            'position' => $position,
            'display_order' => $displayOrder,
            'widget_page_id' => $widgetPageId,
            'options' => $options,

            'class' => $this->_input->filterSingle('class', XenForo_Input::STRING),
        );

        $viewParams = array();
        if (!empty($positionWidget)) {
            $viewParams['positionWidget'] = $positionWidget;
        }
        if (!empty($widgetPage)) {
            $viewParams['widgetPage'] = $widgetPage;

            $this->_routeMatch->setSections('nodeTree');
        }

        return $this->_getResponseAddOrEdit($widget, $viewParams);
    }

    public function actionEdit()
    {
        $widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
        $widget = $this->_getWidgetOrError($widgetId);
        $this->_getWidgetModel()->prepareWidget($widget);

        return $this->_getResponseAddOrEdit($widget);
    }

    public function actionDuplicate()
    {
        $widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
        $widget = $this->_getWidgetOrError($widgetId);

        $widget['widget_id'] = 0;

        return $this->_getResponseAddOrEdit($widget);
    }

    protected function _getResponseAddOrEdit($widget, array $viewParams = array())
    {
        $viewParams = array_merge($viewParams, array(
            'widget' => $widget,
            'renderers' => $this->_getRenderersList(),

            '_layoutEditor' => $this->_input->filterSingle('_layoutEditor', XenForo_Input::UINT),
            'conditionalParams' => $this->_input->filterSingle('conditionalParams', XenForo_Input::STRING),
        ));

        return $this->responseView('WidgetFramework_ViewAdmin_Widget_Edit', 'wf_widget_edit', $viewParams);
    }

    public function actionOptions()
    {
        $this->_assertPostOnly();

        $widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
        if ($widgetId) {
            $widget = $this->_getWidgetModel()->getWidgetById($widgetId);
            $this->_getWidgetModel()->prepareWidget($widget);
        } else {
            $widget = array();
        }

        $widgetPageId = $this->_input->filterSingle('widget_page_id', XenForo_Input::UINT);
        if (!empty($widgetPageId)) {
            $widget['widget_page_id'] = $widgetPageId;
        }

        $class = $this->_input->filterSingle('class', XenForo_Input::STRING);
        $renderer = WidgetFramework_Core::getRenderer($class, false);
        if (!empty($renderer)) {
            $widget['options'] = $renderer->parseOptionsInput($this->_input, $widget);
        }

        $viewParams = array(
            'class' => $class,
            'widget' => $widget,

            '_layoutEditor' => $this->_input->filterSingle('_layoutEditor', XenForo_Input::UINT),
        );
        return $this->responseView('WidgetFramework_ViewAdmin_Widget_Options', 'wf_widget_options', $viewParams);
    }

    public function actionSave()
    {
        $this->_assertPostOnly();

        $widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
        if (!empty($widgetId)) {
            $widget = $this->_getWidgetOrError($widgetId);
        } else {
            $widget = array();
        }

        $positionWidgetId = $this->_input->filterSingle('position_widget', XenForo_Input::UINT);
        $positionWidget = null;
        if (!empty($positionWidgetId)) {
            $positionWidget = $this->_getWidgetOrError($positionWidgetId);
        }

        $widgetPageId = $this->_input->filterSingle('widget_page_id', XenForo_Input::UINT);
        if (!empty($widgetPageId)) {
            $widgetPage = $this->_getWidgetPageOrError($widgetPageId);
        }

        $dwInput = $this->_input->filter(array(
            'class' => XenForo_Input::STRING,
            'title' => XenForo_Input::STRING,
            'position' => XenForo_Input::STRING,
            'display_order' => XenForo_Input::INT,
            'active' => XenForo_INput::UINT,
        ));

        /** @var WidgetFramework_DataWriter_Widget $dw */
        $dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
        if ($widgetId) {
            $dw->setExistingData($widget, true);
        }
        if (!empty($widgetPage)) {
            $dw->set('widget_page_id', $widgetPage['node_id']);
        }
        $dw->bulkSet($dwInput);

        $renderer = WidgetFramework_Core::getRenderer($dwInput['class']);
        if ($this->_input->filterSingle('options_loaded', XenForo_Input::STRING) == get_class($renderer)) {
            // process options now
            $widgetOptions = $renderer->parseOptionsInput($this->_input, $widget);
            $dw->set('options', $widgetOptions);
        } else {
            // skip options, mark to redirect later
            $flagGoBackToEdit = true;
        }

        XenForo_Db::beginTransaction();

        if (!empty($positionWidget)) {
            if (empty($positionWidget['group_id'])) {
                $_group = $this->_getWidgetModel()->createGroupContaining($positionWidget);

                $_positionWidgetDw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
                $_positionWidgetDw->setExistingData($widget, true);
                $_positionWidgetDw->set('group_id', $_group['widget_id']);
                $_positionWidgetDw->setExtraData(WidgetFramework_DataWriter_Widget::EXTRA_DATA_SKIP_REBUILD, true);
                $_positionWidgetDw->save();

                $positionWidget['group_id'] = $_group['widget_id'];
            }

            $dw->bulkSet(array(
                'position' => $positionWidget['position'],
                'group_id' => $positionWidget['group_id'],
            ));
        }

        $dw->save();

        XenForo_Db::commit();

        if ($this->_input->filterSingle('_layoutEditor', XenForo_Input::UINT)) {
            $changedRenderedId = WidgetFramework_Helper_LayoutEditor::getChangedRenderedId($dw);

            if (!empty($positionWidgetDw)) {
                $changedRenderedId = WidgetFramework_Helper_LayoutEditor::getChangedRenderedId($dw, $changedRenderedId);
            }

            $viewParams = array('changedRenderedId' => $changedRenderedId);

            return $this->responseView('WidgetFramework_ViewAdmin_Widget_Save', '', $viewParams);
        } elseif (!empty($flagGoBackToEdit)) {
            return call_user_func_array(array(
                $this,
                'responseRedirect'
            ), array(
                XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
                call_user_func_array(array(
                    'XenForo_Link',
                    'buildAdminLink'
                ), array(
                    'widgets/edit',
                    $dw->getMergedData(),
                    array('position_widget' => !empty($positionWidget) ? $positionWidget['widget_id'] : '')
                ))
            ));
        } else {
            $link = XenForo_Link::buildAdminLink('widgets') . $this->getLastHash($dw->get('widget_id'));

            $widgetPageId = $dw->get('widget_page_id');
            if (!empty($widgetPageId)) {
                $link = XenForo_Link::buildAdminLink('widget-pages/edit', array('node_id' => $widgetPageId));
            }

            if (!empty($widget)) {
                $notLink = XenForo_Link::buildAdminLink('full:widgets/edit', $widget);
            } else {
                $notLink = XenForo_Link::buildAdminLink('full:widgets/add');
            }

            $link = $this->getDynamicRedirectIfNot($notLink, $link);

            return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $link);
        }
    }

    public function actionSavePosition()
    {
        $this->_assertPostOnly();

        $changedRenderedId = array();

        $widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
        $widget = $this->_getWidgetOrError($widgetId);

        $globalWidgets = $this->_getWidgetModel()->getGlobalWidgets(false, false);
        $core = WidgetFramework_Core::getInstance();
        $core->addWidgets($globalWidgets);

        $positionWidgetId = $this->_input->filterSingle('position_widget', XenForo_Input::UINT);
        $positionWidget = null;
        if (!empty($positionWidgetId)) {
            $positionWidget = $this->_getWidgetOrError($positionWidgetId);
        }

        $widgetPageId = $this->_input->filterSingle('widget_page_id', XenForo_Input::UINT);
        if (!empty($widgetPageId)) {
            $widgetPage = $this->_getWidgetPageOrError($widgetPageId);

            $widgetPageWidgets = $this->_getWidgetModel()->getPageWidgets($widgetPage['node_id'], false);
            $core->addWidgets($widgetPageWidgets);
        }

        $dwInput = $this->_input->filter(array(
            'position' => XenForo_Input::STRING,
            'display_order' => XenForo_Input::STRING,
            'relative_display_order' => XenForo_Input::STRING,
            'widget_page_id' => XenForo_Input::UINT,
        ));

        /** @var WidgetFramework_DataWriter_Widget $dw */
        $dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
        $dw->setExistingData($widget, true);

        $widgetsNeedUpdate = array();

        $groupId = 0;
        if (!empty($positionWidget)) {
            if ($positionWidget['widget_id'] != $widget['widget_id']) {
                if (!empty($positionWidget['group_id'])) {
                    $groupId = $positionWidget['group_id'];
                } else {
                    $_group = $this->_getWidgetModel()->createGroupContaining($positionWidget);

                    $groupId = $_group['widget_id'];
                    $widgetsNeedUpdate[$positionWidget['widget_id']]['group_id'] = $groupId;
                }
            }
        } else {
            $dw->set('widget_page_id', $dwInput['widget_page_id']);
            $dw->set('position', $dwInput['position']);
        }
        $dw->set('group_id', $groupId);

        if ($dwInput['display_order'] !== '') {
            $dw->set('display_order', $dwInput['display_order']);
        } elseif ($dwInput['relative_display_order'] !== '') {
            $dw->set('display_order',
                $this->_getWidgetModel()->getDisplayOrderFromRelative(
                    $dw->get('widget_id'),
                    $groupId,
                    intval($dwInput['relative_display_order']),
                    $core->getWidgetsAtPosition($dw->get('position')),
                    $positionWidget,
                    $widgetsNeedUpdate
                )
            );
        }

        if ($dw->isChanged('group_id')) {
            $this->_getWidgetModel()->updatePositionGroupAndDisplayOrderForWidgets(
                $dw->get('widget_id'),
                $dw->get('position'),
                $dw->get('group_id'),
                $dw->get('display_order'),
                $core->getWidgetsAtPosition($widget['position']),
                $widgetsNeedUpdate
            );
        }

        XenForo_Db::beginTransaction();

        $dw->save();

        foreach ($widgetsNeedUpdate as $needUpdateId => $needUpdateData) {
            /** @var WidgetFramework_DataWriter_Widget $needUpdateDw */
            $needUpdateDw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
            $needUpdateDw->setExistingData($needUpdateId);

            $needUpdateDw->bulkSet($needUpdateData, array('ignoreInvalidFields' => true));
            if (isset($needUpdateData['_options'])) {
                foreach ($needUpdateData['_options'] as $optionKey => $optionValue) {
                    $needUpdateDw->setWidgetOption($optionKey, $optionValue);
                }
            }

            $needUpdateDw->save();

            $changedRenderedId = WidgetFramework_Helper_LayoutEditor::getChangedRenderedId($needUpdateDw, $changedRenderedId);
        }

        XenForo_Db::commit();

        $changedRenderedId = WidgetFramework_Helper_LayoutEditor::getChangedRenderedId($dw, $changedRenderedId);
        $viewParams = array('changedRenderedId' => $changedRenderedId);

        return $this->responseView('WidgetFramework_ViewAdmin_Widget_Save', '', $viewParams);
    }

    public function actionDelete()
    {
        $widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
        $widget = $this->_getWidgetOrError($widgetId);

        if ($this->isConfirmedPost()) {
            /** @var WidgetFramework_DataWriter_Widget $dw */
            $dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
            $dw->setExistingData($widgetId);
            $dw->delete();

            $link = XenForo_Link::buildAdminLink('widgets');

            $widgetPageId = $dw->getExisting('widget_page_id');
            if (!empty($widgetPageId)) {
                $link = XenForo_Link::buildAdminLink('widget-pages/edit', array('node_id' => $widgetPageId));
            }

            $notLink = XenForo_Link::buildAdminLink('full:widgets/delete', $widget);

            $link = $this->getDynamicRedirectIfNot($notLink, $link);

            if ($this->_input->filterSingle('_layoutEditor', XenForo_Input::UINT)) {
                $changedRenderedId = WidgetFramework_Helper_LayoutEditor::getChangedRenderedId($dw);
                $viewParams = array('changedRenderedId' => $changedRenderedId);

                return $this->responseView('WidgetFramework_ViewAdmin_Widget_Save', '', $viewParams);
            } else {
                return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $link);
            }
        } else {
            $viewParams = array(
                'widget' => $widget,

                '_layoutEditor' => $this->_input->filterSingle('_layoutEditor', XenForo_Input::UINT),
            );

            return $this->responseView('WidgetFramework_ViewAdmin_Widget_Delete', 'wf_widget_delete', $viewParams);
        }
    }

    protected function _switchWidgetActiveStateAndGetResponse($widgetId, $activeState)
    {
        $dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
        $dw->setExistingData($widgetId);
        $dw->set('active', $activeState);
        $dw->save();

        return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('widgets'));
    }

    public function actionEnable()
    {
        // can be requested over GET, so check for the token manually
        $this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

        $widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
        return $this->_switchWidgetActiveStateAndGetResponse($widgetId, 1);
    }

    public function actionDisable()
    {
        // can be requested over GET, so check for the token manually
        $this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

        $widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
        return $this->_switchWidgetActiveStateAndGetResponse($widgetId, 0);
    }

    public function actionToggle()
    {
        return $this->_getToggleResponse($this->_getWidgetModel()->getGlobalWidgets(false), 'WidgetFramework_DataWriter_Widget', 'widgets');
    }

    public function actionImport()
    {
        if ($this->isConfirmedPost()) {
            $fileTransfer = new Zend_File_Transfer_Adapter_Http();
            if ($fileTransfer->isUploaded('upload_file')) {
                $fileInfo = $fileTransfer->getFileInfo('upload_file');
                $fileName = $fileInfo['upload_file']['tmp_name'];
            } else {
                $fileName = $this->_input->filterSingle('server_file', XenForo_Input::STRING);
            }

            $deleteAll = $this->_input->filterSingle('delete_all', XenForo_Input::UINT);

            $this->_getWidgetModel()->importFromFile($fileName, $deleteAll);

            return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('widgets'));
        } else {
            return $this->responseView('WidgetFramework_ViewAdmin_Widget_Import', 'wf_widget_import');
        }
    }

    public function actionExport()
    {
        $widgetModel = $this->_getWidgetModel();
        /** @var XenForo_Model_AddOn $addOnModel */
        $addOnModel = $this->getModelFromCache('XenForo_Model_AddOn');

        $widgets = $widgetModel->getGlobalWidgets(false, false);
        $addOn = $addOnModel->getAddOnById('widget_framework');

        $this->_routeMatch->setResponseType('xml');

        $viewParams = array(
            'system' => $addOn,
            'widgets' => $widgets,
        );

        return $this->responseView('WidgetFramework_ViewAdmin_Widget_Export', '', $viewParams);
    }

    public function actionTranslateTitle()
    {
        $widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
        $widget = $this->_getWidgetOrError($widgetId);

        $languageId = $this->_input->filterSingle('language_id', XenForo_Input::UINT);

        $dw = XenForo_DataWriter::create('XenForo_DataWriter_Phrase', XenForo_DataWriter::ERROR_SILENT);
        $dw->set('language_id', $languageId);
        $dw->set('title', $this->_getWidgetModel()->getWidgetTitlePhrase($widget['widget_id']));
        $dw->set('phrase_text', '');
        $dw->save();

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
            XenForo_Link::buildAdminLink('phrases/edit', null, array('phrase_id' => $dw->get('phrase_id')))
        );
    }

    protected function _getWidgetOrError($widgetId)
    {
        $info = $this->_getWidgetModel()->getWidgetById($widgetId);
        if (!$info) {
            throw $this->responseException($this->responseError(new XenForo_Phrase('wf_requested_widget_not_found'), 404));
        }

        return $info;
    }

    protected function _getWidgetPageOrError($nodeId)
    {
        $info = $this->_getWidgetPageModel()->getWidgetPageById($nodeId);
        if (!$info) {
            throw $this->responseException($this->responseError(new XenForo_Phrase('wf_requested_widget_page_not_found'), 404));
        }

        return $info;
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

    protected function _getRenderersList()
    {
        $renderers = WidgetFramework_Core::getRenderers();
        $options = array();
        foreach ($renderers as $renderer) {
            $rendererObj = WidgetFramework_Core::getRenderer($renderer);

            $options[] = array(
                'value' => $renderer,
                'label' => $rendererObj->getName(),
                'is_hidden' => $rendererObj->isHidden(),
            );
        }

        usort($options, array('WidgetFramework_Helper_Sort', 'widgetsByLabel'));

        return $options;
    }

}
