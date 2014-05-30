<?php

class WidgetFramework_ControllerAdmin_Widget extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('style');
	}

	public function actionIndex()
	{
		$widgets = $this->_getWidgetModel()->getGlobalWidgets(false);

		$viewParams = array('widgets' => $widgets);

		return $this->responseView('WidgetFramework_ViewAdmin_Widget_List', 'wf_widget_list', $viewParams);
	}

	public function actionAdd()
	{
		$displayOrder = $this->_input->filterSingle('display_order', XenForo_Input::INT, array('default' => 'na'));
		if ($displayOrder == 'na')
		{
			$displayOrder = 10;
		}

		$widgetPageId = $this->_input->filterSingle('widget_page_id', XenForo_Input::UINT);

		$options = array();

		if (!empty($widgetPageId))
		{
			// prepare options for widget of widget page
			$widgetPage = $this->_getWidgetPageModel()->getWidgetPageById($widgetPageId);
			if (empty($widgetPage))
			{
				return $this->responseError(new XenForo_Phrase('wf_requested_widget_page_not_found'), 404);
			}

			$widgetPageWidgets = $this->_getWidgetModel()->getWidgetPageWidgets($widgetPage['node_id']);
			$maxRow = -1;
			foreach ($widgetPageWidgets as $widgetPageWidget)
			{
				if (isset($widgetPageWidget['options']['layout_row']))
				{
					$maxRow = max($maxRow, $widgetPageWidget['options']['layout_row']);
				}
			}
			$options['layout_row'] = $maxRow + 1;
		}

		$widget = array(
			'active' => 1,
			'position' => $this->_input->filterSingle('position', XenForo_Input::STRING),
			'display_order' => $displayOrder,
			'widget_page_id' => $widgetPageId,
			'options' => $options,
		);

		$viewParams = array();
		if (!empty($widgetPage))
		{
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
		$this->_getWidgetModel()->prepareWidget($widget);

		$widget['widget_id'] = 0;

		return $this->_getResponseAddOrEdit($widget);
	}

	protected function _getResponseAddOrEdit($widget, array $viewParams = array())
	{
		$viewParams = array_merge($viewParams, array(
			'widget' => $widget,
			'renderers' => $this->_getRenderersList(),
		));

		return $this->responseView('WidgetFramework_ViewAdmin_Widget_Edit', 'wf_widget_edit', $viewParams);
	}

	public function actionOptions()
	{
		$this->_assertPostOnly();

		$widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
		if ($widgetId)
		{
			$widget = $this->_getWidgetModel()->getWidgetById($widgetId);
			$this->_getWidgetModel()->prepareWidget($widget);
		}
		else
		{
			$widget = array();
		}

		$widgetPageId = $this->_input->filterSingle('widget_page_id', XenForo_Input::UINT);
		if (!empty($widgetPageId))
		{
			$widget['widget_page_id'] = $widgetPageId;
		}

		$class = $this->_input->filterSingle('class', XenForo_Input::STRING);

		$viewParams = array(
			'class' => $class,
			'widget' => $widget,
		);
		return $this->responseView('WidgetFramework_ViewAdmin_Widget_Options', 'wf_widget_options', $viewParams);
	}

	public function actionSave()
	{
		$this->_assertPostOnly();

		$widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
		if (!empty($widgetId))
		{
			$widget = $this->_getWidgetOrError($widgetId);
			$this->_getWidgetModel()->prepareWidget($widget);
		}
		else
		{
			$widget = array();
		}

		$dwInput = $this->_input->filter(array(
			'widget_page_id' => XenForo_Input::UINT,
			'class' => XenForo_Input::STRING,
			'title' => XenForo_Input::STRING,
			'position' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::INT,
			'active' => XenForo_INput::UINT,
		));

		$dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
		if ($widgetId)
		{
			$dw->setExistingData($widget, true);
		}
		$dw->bulkSet($dwInput);

		$renderer = WidgetFramework_Core::getRenderer($dwInput['class']);
		if ($this->_input->filterSingle('options_loaded', XenForo_Input::STRING) == get_class($renderer))
		{
			// process options now
			$widgetOptions = $renderer->parseOptionsInput($this->_input, $widget);
			$dw->set('options', $widgetOptions);
		}
		else
		{
			// skip options, mark to redirect later
			$flagGoBackToEdit = true;
		}

		$dw->save();

		if (!empty($flagGoBackToEdit))
		{
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED, XenForo_Link::buildAdminLink('widgets/edit', $dw->getMergedData()));
		}
		else
		{
			$link = XenForo_Link::buildAdminLink('widgets') . $this->getLastHash($dw->get('widget_id'));

			$widgetPageId = $dw->get('widget_page_id');
			if (!empty($widgetPageId))
			{
				$link = XenForo_Link::buildAdminLink('widget-pages/edit', array('node_id' => $widgetPageId));
			}

			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $link);
		}
	}

	public function actionDelete()
	{
		$widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
		$widget = $this->_getWidgetOrError($widgetId);

		if ($this->isConfirmedPost())
		{
			$dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
			$dw->setExistingData($widgetId);
			$dw->delete();

			$link = XenForo_Link::buildAdminLink('widgets');

			$widgetPageId = $dw->getExisting('widget_page_id');
			if (!empty($widgetPageId))
			{
				$link = XenForo_Link::buildAdminLink('widget-pages/edit', array('node_id' => $widgetPageId));
			}

			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $link);
		}
		else
		{
			$viewParams = array('widget' => $widget);

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
		if ($this->isConfirmedPost())
		{
			$fileTransfer = new Zend_File_Transfer_Adapter_Http();
			if ($fileTransfer->isUploaded('upload_file'))
			{
				$fileInfo = $fileTransfer->getFileInfo('upload_file');
				$fileName = $fileInfo['upload_file']['tmp_name'];
			}
			else
			{
				$fileName = $this->_input->filterSingle('server_file', XenForo_Input::STRING);
			}

			$deleteAll = $this->_input->filterSingle('delete_all', XenForo_Input::UINT);

			$this->_getWidgetModel()->importFromFile($fileName, $deleteAll);

			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('widgets'));
		}
		else
		{
			return $this->responseView('WidgetFramework_ViewAdmin_Widget_Import', 'wf_widget_import');
		}
	}

	public function actionExport()
	{
		$widgetModel = $this->_getWidgetModel();
		$widgets = $widgetModel->getGlobalWidgets(false, false);

		$addOn = $this->getModelFromCache('XenForo_Model_AddOn')->getAddOnById('widget_framework');

		$this->_routeMatch->setResponseType('xml');

		$viewParams = array(
			'system' => $addOn,
			'widgets' => $widgets,
		);

		return $this->responseView('WidgetFramework_ViewAdmin_Widget_Export', '', $viewParams);
	}

	public function actionReveal()
	{
		$publicSession = new XenForo_Session();
		$publicSession->start();
		if ($publicSession->get('user_id') != XenForo_Visitor::getUserId())
		{
			return $this->responseError(new XenForo_Phrase('please_login_via_public_login_page_before_testing_permissions'));
		}

		$publicSession->set('_WidgetFramework_reveal', true);
		$publicSession->save();

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('index'));
	}

	protected function _getWidgetOrError($widgetId)
	{
		$info = $this->_getWidgetModel()->getWidgetById($widgetId);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('wf_requested_widget_not_found'), 404));
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
		foreach ($renderers as $renderer)
		{
			$options[] = array(
				'value' => $renderer,
				'label' => WidgetFramework_Core::getRenderer($renderer)->getName(),
			);
		}

		usort($options, create_function('$a, $b', 'return $a["label"] > $b["label"];'));

		return $options;
	}

}
