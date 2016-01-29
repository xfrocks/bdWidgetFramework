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
        $widgets = $this->_getWidgetModel()->getWidgets(array('widget_page_id' => 0));

        $viewParams = array('widgets' => $widgets);

        return $this->responseView('WidgetFramework_ViewAdmin_Widget_List', 'wf_widget_list', $viewParams);
    }

    public function actionAdd()
    {
        $options = array();

        $widgetPageId = $this->_input->filterSingle('widget_page_id', XenForo_Input::UINT);
        $position = $this->_input->filterSingle('position', XenForo_Input::STRING);
        $displayOrder = $this->_input->filterSingle('display_order', XenForo_Input::STRING, array('default' => 'na'));
        $groupMerge = $this->_input->filterSingle('group_merge', XenForo_Input::UINT, array('array' => true));
        $groupJoin = $this->_input->filterSingle('group_join', XenForo_Input::UINT);

        $widgetPage = null;
        if (!empty($widgetPageId)) {
            $widgetPage = $this->_getWidgetPageOrError($widgetPageId);
        }

        if ($displayOrder == 'na'
            && !empty($position)
        ) {
            $widgetsConditions = array();
            if (!empty($widgetPage['node_id'])) {
                $widgetsConditions['widget_page_id'] = $widgetPage['node_id'];
            }
            $widgets = $this->_getWidgetModel()->getWidgets($widgetsConditions);

            $core = WidgetFramework_Core::getInstance();
            $core->addWidgets($widgets);
            $displayOrder = $this->_getWidgetModel()->getLastDisplayOrder(
                $core->getWidgetsAtPosition($position), $groupJoin);
        }

        $widget = array(
            'widget_id' => 0,
            'class' => $this->_input->filterSingle('class', XenForo_Input::STRING),
            'active' => 1,

            'widget_page_id' => $widgetPageId,
            'position' => $position,
            'display_order' => $displayOrder,

            'options' => $options,

            'positionCodes' => WidgetFramework_Helper_String::splitPositionCodes($position),
        );

        $viewParams = array(
            'groupMerge' => $groupMerge,
            'groupJoin' => $groupJoin,
        );

        return $this->_getResponseAddOrEdit($widget, $viewParams);
    }

    public function actionEdit()
    {
        $widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
        $widget = $this->_getWidgetOrError($widgetId);

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

        if (isset($widget['widget_page_id'])
            && $widget['widget_page_id'] > 0
        ) {
            $widgetPage = $this->_getWidgetPageModel()->getWidgetPageById($widget['widget_page_id']);
            $viewParams['widgetPage'] = $widgetPage;
        }

        $widgets = array();
        if ($widget['widget_id'] > 0) {
            $widgets = $this->_getWidgetModel()->getWidgets(array(
                'widget_page_id' => $widget['widget_page_id'],
            ));
        }

        if (!empty($widgets)
            && count($widget['positionCodes']) == 1
        ) {
            $positionCode = reset($widget['positionCodes']);

            $core = WidgetFramework_Core::getInstance();
            $core->removeAllWidgets();
            $core->addWidgets($widgets);

            $widgetsAtPosition = $core->getWidgetsAtPosition($positionCode);

            $siblingWidgets = $this->_getWidgetModel()->getSiblingWidgets($widgetsAtPosition, $widget['widget_id']);
            if (isset($siblingWidgets[$widget['widget_id']])) {
                unset($siblingWidgets[$widget['widget_id']]);
            }
            $viewParams['siblingWidgets'] = $siblingWidgets;

            $groups = array();
            foreach ($widgets as $_widget) {
                if ($_widget['class'] === 'WidgetFramework_WidgetGroup'
                    && $positionCode === $_widget['position']
                ) {
                    $groups[$_widget['widget_id']] = $_widget;
                }
            }
            if (isset($groups[$widget['widget_id']])) {
                unset($groups[$widget['widget_id']]);
            }
            $viewParams['groups'] = $groups;
        }

        return $this->responseView('WidgetFramework_ViewAdmin_Widget_Edit', 'wf_widget_edit', $viewParams);
    }

    public function actionOptions()
    {
        $this->_assertPostOnly();

        $widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
        if ($widgetId) {
            $widget = $this->_getWidgetModel()->getWidgetById($widgetId);
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

        $widgetPageId = $this->_input->filterSingle('widget_page_id', XenForo_Input::UINT);
        if (!empty($widgetPageId)) {
            $widgetPage = $this->_getWidgetPageOrError($widgetPageId);
        }

        $dwInput = $this->_input->filter(array(
            'class' => XenForo_Input::STRING,
            'title' => XenForo_Input::STRING,
            'position' => XenForo_Input::STRING,
            'display_order' => XenForo_Input::INT,
            'active' => XenForo_Input::UINT,
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

        if (empty($widget['group_id'])) {
            $groupMerge = $this->_input->filterSingle('group_merge', XenForo_Input::UINT, array('array' => true));
            $groupJoin = $this->_input->filterSingle('group_join', XenForo_Input::UINT);
            if (!empty($groupMerge)) {
                $_widgetsToMerge = $this->_getWidgetModel()->getWidgets(array('widget_id' => $groupMerge));
                foreach (array_keys($_widgetsToMerge) as $_widgetIdToMerge) {
                    if ($_widgetsToMerge[$_widgetIdToMerge]['position'] !== $dw->get('position')
                        || $_widgetIdToMerge[$_widgetIdToMerge]['group_id'] > 0
                    ) {
                        unset($_widgetsToMerge[$_widgetIdToMerge]);
                    }
                }

                if (!empty($_widgetsToMerge)) {
                    $_group = $this->_getWidgetModel()->createGroupContaining(reset($_widgetsToMerge),
                        array('layout' => 'columns'));

                    foreach ($_widgetsToMerge as $_widgetToMerge) {
                        /** @var WidgetFramework_DataWriter_Widget $_widgetToMergeDw */
                        $_widgetToMergeDw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
                        $_widgetToMergeDw->setExistingData($_widgetToMerge, true);
                        $_widgetToMergeDw->set('group_id', $_group['widget_id']);
                        $_widgetToMergeDw->setExtraData(
                            WidgetFramework_DataWriter_Widget::EXTRA_DATA_SKIP_REBUILD, true);
                        $_widgetToMergeDw->save();
                    }

                    $dw->set('group_id', $_group['widget_id']);
                }
            } elseif (!empty($groupJoin)) {
                $_group = $this->_getWidgetModel()->getWidgetById($groupJoin);
                if (!empty($_group)
                    && $_group['position'] === $dw->get('position')
                ) {
                    $dw->set('group_id', $_group['widget_id']);
                }
            }
        } else {
            if ($this->_input->filterSingle('group_ungroup', XenForo_Input::BOOLEAN)) {
                $_group = $this->_getWidgetModel()->getWidgetById($widget['group_id']);
                if (!empty($_group)) {
                    $dw->set('group_id', $_group['group_id']);
                } else {
                    $dw->set('group_id', 0);
                }
            }
        }

        $dw->save();

        XenForo_Db::commit();

        if ($this->_input->filterSingle('_layoutEditor', XenForo_Input::UINT)) {
            $viewParams = array('changedRenderedId' => WidgetFramework_Helper_LayoutEditor::getChangedWidgetIds());

            return $this->responseView('WidgetFramework_ViewAdmin_Widget_Save', '', $viewParams);
        } elseif (!empty($flagGoBackToEdit)) {
            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
                XenForo_Link::buildAdminLink('widgets/edit', $dw->getMergedData())
            );
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

        $widgetPageId = $this->_input->filterSingle('widget_page_id', XenForo_Input::UINT);
        $position = $this->_input->filterSingle('position', XenForo_Input::STRING);
        $widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
        $groupId = $this->_input->filterSingle('group_id', XenForo_Input::UINT);
        $groupMerge = $this->_input->filterSingle('group_merge', XenForo_Input::UINT);
        $displayOrder = $this->_input->filterSingle('display_order', XenForo_Input::STRING, array(
            'default' => 'na',
        ));
        $relativeDisplayOrder = $this->_input->filterSingle('relative_display_order', XenForo_Input::STRING, array(
            'default' => 'na',
        ));

        $widget = $this->_getWidgetOrError($widgetId);
        $groupWidget = null;
        $groupMergeWidget = null;
        $widgetPage = null;
        $widgetsNeedUpdate = array();

        if ($groupId > 0) {
            $groupWidget = $this->_getWidgetOrError($groupId);
            $widgetPageId = $groupWidget['widget_page_id'];
            $position = $groupWidget['position'];
        } elseif ($groupMerge > 0) {
            $groupMergeWidget = $this->_getWidgetOrError($groupMerge);
            if ($groupMergeWidget['group_id'] > 0) {
                return $this->responseNoPermission();
            }
            $widgetPageId = $groupMergeWidget['widget_page_id'];
            $position = $groupMergeWidget['position'];

            $_group = $this->_getWidgetModel()->createGroupContaining($groupMergeWidget, array('layout' => 'columns'));
            $groupId = $_group['widget_id'];
            $widgetsNeedUpdate[$groupMergeWidget['widget_id']]['group_id'] = $groupId;
        }

        if ($widgetPageId > 0) {
            $this->_getWidgetPageOrError($widgetPageId);
        }
        $widgets = $this->_getWidgetModel()->getWidgets(array('widget_page_id' => $widgetPageId));

        $core = WidgetFramework_Core::getInstance();
        $core->addWidgets($widgets);

        /** @var WidgetFramework_DataWriter_Widget $dw */
        $dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
        $dw->setExistingData($widget, true);
        $dw->set('widget_page_id', $widgetPageId);
        $dw->set('position', $position);
        $dw->set('group_id', $groupId);

        if ($displayOrder !== 'na') {
            $dw->set('display_order', $displayOrder);
        } elseif ($relativeDisplayOrder !== 'na') {
            if ($groupMergeWidget !== null) {
                $dw->set('display_order', $groupMergeWidget['display_order']);
            } else {
                $dw->set('display_order', $this->_getWidgetModel()->getDisplayOrderFromRelative(
                    $dw->get('widget_id'), $dw->get('group_id'), $relativeDisplayOrder,
                    $core->getWidgetsAtPosition($dw->get('position')), $widgetsNeedUpdate));
            }
        }

        XenForo_Db::beginTransaction();

        $dw->save();

        foreach ($widgetsNeedUpdate as $needUpdateId => $needUpdateData) {
            /** @var WidgetFramework_DataWriter_Widget $needUpdateDw */
            $needUpdateDw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
            $needUpdateDw->setExistingData($needUpdateId);
            $needUpdateDw->bulkSet($needUpdateData);
            $needUpdateDw->save();
        }

        XenForo_Db::commit();

        $viewParams = array('changedRenderedId' => WidgetFramework_Helper_LayoutEditor::getChangedWidgetIds());

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
                $viewParams = array(
                    'changedRenderedId' => WidgetFramework_Helper_LayoutEditor::getChangedWidgetIds(),
                );

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

        $link = XenForo_Link::buildAdminLink('widgets');
        if ($dw->get('widget_page_id') > 0) {
            $link = XenForo_Link::buildAdminLink('widget-pages/edit',
                array('node_id' => $dw->get('widget_page_id')));
        }

        return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $link);
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
        return $this->_getToggleResponse(
            $this->_getWidgetModel()->getWidgets(array('widget_page_id' => 0)),
            'WidgetFramework_DataWriter_Widget',
            'widgets'
        );
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

            $widgetPageId = $this->_input->filterSingle('widget_page_id', XenForo_Input::UINT);
            $widgetPage = null;
            if ($widgetPageId > 0) {
                $widgetPage = $this->_getWidgetPageOrError($widgetPageId);
            }

            $deleteAll = $this->_input->filterSingle('delete_all', XenForo_Input::UINT);

            try {
                $this->_getWidgetModel()->importFromFile($fileName, $widgetPage, $deleteAll);
            } catch (XenForo_Exception $e) {
                return $this->responseError($e->getMessage());
            }

            if ($widgetPage === null) {
                $redirectTarget = XenForo_Link::buildAdminLink('widgets');
            } else {
                $redirectTarget = XenForo_Link::buildAdminLink('widget-pages/edit', $widgetPage);
            }

            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                $redirectTarget
            );
        } else {
            $widgetPages = $this->_getWidgetPageModel()->getWidgetPages();

            $viewParams = array(
                'widgetPages' => $widgetPages,
            );

            return $this->responseView('WidgetFramework_ViewAdmin_Widget_Import', 'wf_widget_import', $viewParams);
        }
    }

    public function actionExport()
    {
        $widgetModel = $this->_getWidgetModel();
        /** @var XenForo_Model_AddOn $addOnModel */
        $addOnModel = $this->getModelFromCache('XenForo_Model_AddOn');

        $widgetPageId = $this->_input->filterSingle('widget_page_id', XenForo_Input::UINT);
        $widgetPage = null;
        if ($widgetPageId > 0) {
            $widgetPage = $this->_getWidgetPageOrError($widgetPageId);
        }
        $widgets = $widgetModel->getWidgets(array('widget_page_id' => $widgetPageId));

        $addOn = $addOnModel->getAddOnById('widget_framework');

        $this->_routeMatch->setResponseType('xml');

        $viewParams = array(
            'system' => $addOn,
            'widgetPage' => $widgetPage,
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
            throw $this->responseException(
                $this->responseError(new XenForo_Phrase('wf_requested_widget_not_found'), 404));
        }

        return $info;
    }

    protected function _getWidgetPageOrError($nodeId)
    {
        $info = $this->_getWidgetPageModel()->getWidgetPageById($nodeId);
        if (!$info) {
            throw $this->responseException(
                $this->responseError(new XenForo_Phrase('wf_requested_widget_page_not_found'), 404));
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
