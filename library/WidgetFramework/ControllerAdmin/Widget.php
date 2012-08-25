<?php
class WidgetFramework_ControllerAdmin_Widget extends XenForo_ControllerAdmin_Abstract {
	protected function _preDispatch($action) {
		$this->assertAdminPermission('style');
	}

	public function actionIndex() {
		if ($this->_request->isPost()) {
			// probably a toggle request
			$widgetExists = $this->_input->filterSingle('widgetExists', array(XenForo_Input::UINT, 'array' => true));
			$widgets = $this->_input->filterSingle('widget', array(XenForo_Input::UINT, 'array' => true));
			
			if (!empty($widgetExists)) {
				$widgetModel = $this->_getWidgetModel();
		
				foreach ($widgetModel->getAllWidgets(false) AS $widgetId => $widget) {
					if (isset($widgetExists[$widgetId])) {
						$widgetActive = (isset($widgets[$widgetId]) && $widgets[$widgetId] ? 1 : 0);
		
						if ($widget['active'] != $widgetActive) {
							$dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
							$dw->setExistingData($widgetId);
							$dw->set('active', $widgetActive);
							$dw->save();
						}
					}
				}
		
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildAdminLink('widgets')
				);
			}
		} 
		
		// a simple listing request
		$widgetModel = $this->_getWidgetModel();
		$widgets = $widgetModel->getAllWidgets(false);

		$viewParams = array(
			'widgets' => $widgets,
		);

		return $this->responseView('WidgetFramework_ViewAdmin_Widget_List', 'wf_widget_list', $viewParams);
	}

	public function actionAdd() {
		$viewParams = array(
			'widget' => array(
				'active' => 1,
			),
			'renderers' => $this->_getRenderersList(),
		);

		return $this->responseView('WidgetFramework_ViewAdmin_Widget_Edit', 'wf_widget_edit', $viewParams);
	}

	public function actionEdit() {
		$widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
		$widget = $this->_getWidgetOrError($widgetId);

		$viewParams = array(
			'widget' => $widget,
			'renderers' => $this->_getRenderersList(),
		);

		return $this->responseView('WidgetFramework_ViewAdmin_Widget_Edit', 'wf_widget_edit', $viewParams);
	}
	
	public function actionOptions() {
		$this->_assertPostOnly();
		
		$widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
		if ($widgetId) {
			$widget = $this->_getWidgetModel()->getWidgetById($widgetId);
		} else {
			$widget = array();
		}
		
		$class = $this->_input->filterSingle('class', XenForo_Input::STRING);
		
		$viewParams = array(
			'class' => $class,
			'widget' => $widget,
		);
		return $this->responseView('WidgetFramework_ViewAdmin_Widget_Options', 'wf_widget_options', $viewParams);
	}

	public function actionSave() {
		$this->_assertPostOnly();

		$widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);

		$dwInput = $this->_input->filter(array(
			'class' => XenForo_Input::STRING,
			'title' => XenForo_Input::STRING,
			'position' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::UINT,
			'active' => XenForo_INput::UINT,
		));
		
		$dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
		if ($widgetId) {
			$dw->setExistingData($widgetId);
		}
		$dw->bulkSet($dwInput);
		
		if ($this->_input->filterSingle('options_loaded', XenForo_Input::STRING) == $dwInput['class']) {
			// process options now
			$renderer = WidgetFramework_Core::getRenderer($dwInput['class']);
			$widgetOptions = $renderer->parseOptionsInput($this->_input, $dw->getMergedData());
			$dw->set('options', $widgetOptions);
		} else {
			// skip options, mark to redirect later
			$flagGoBackToEdit = true;
		}

		$dw->save();

		if ($this->_noRedirect()) {
			return $this->responseView('WidgetFramework_ViewAdmin_Widget_Save', '', array('widget' => $dw->getMergedData()));
		} else if (!empty($flagGoBackToEdit)) {
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				XenForo_Link::buildAdminLink('widgets/edit', $dw->getMergedData())
			);
		} else {
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('widgets')
			);
		}
	}

	public function actionDelete() {
		$widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
		$widget = $this->_getWidgetOrError($widgetId);

		if ($this->isConfirmedPost()) {
			$dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
			$dw->setExistingData($widgetId);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('widgets')
			);
		} else {
			$viewParams = array(
				'widget' => $widget
			);

			return $this->responseView('WidgetFramework_ViewAdmin_Widget_Delete', 'wf_widget_delete', $viewParams);
		}
	}
	
	protected function _switchWidgetActiveStateAndGetResponse($widgetId, $activeState) {
		$dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
		$dw->setExistingData($widgetId);
		$dw->set('active', $activeState);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('widgets')
		);
	}

	public function actionEnable() {
		// can be requested over GET, so check for the token manually
		$this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

		$widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
		return $this->_switchWidgetActiveStateAndGetResponse($widgetId, 1);
	}

	public function actionDisable() {
		// can be requested over GET, so check for the token manually
		$this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

		$widgetId = $this->_input->filterSingle('widget_id', XenForo_Input::UINT);
		return $this->_switchWidgetActiveStateAndGetResponse($widgetId, 0);
	}
	
	public function actionImport() {
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

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('widgets')
			);
		} else {
			return $this->responseView('WidgetFramework_ViewAdmin_Widget_Import', 'wf_widget_import');
		}
	}
	
	public function actionExport() {
		$widgetModel = $this->_getWidgetModel();
		$widgets = $widgetModel->getAllWidgets(false, false);
		
		$addOn = $this->getModelFromCache('XenForo_Model_AddOn')->getAddOnById('widget_framework');
		
		$this->_routeMatch->setResponseType('xml');

		$viewParams = array(
			'system' => $addOn,
			'widgets' => $widgets,
		);

		return $this->responseView('WidgetFramework_ViewAdmin_Widget_Export', '', $viewParams);
	}

	protected function _getWidgetOrError($widgetId) {
		$info = $this->_getWidgetModel()->getWidgetById($widgetId);
		if (!$info) {
			throw $this->responseException($this->responseError(new XenForo_Phrase('wf_requested_widget_not_found'), 404));
		}

		return $info;
	}

	/**
	 * @return WidgetFramework_Model_Widget
	 */
	protected function _getWidgetModel() {
		return $this->getModelFromCache('WidgetFramework_Model_Widget');
	}
	
	protected function _getRenderersList() {
		$renderers = WidgetFramework_Core::getRenderers();
		$options = array();
		foreach ($renderers as $renderer) {
			$options[] = array(
				'value' => $renderer,
				'label' => WidgetFramework_Core::getRenderer($renderer)->getName(),
			);
		}
		
		usort($options, create_function('$a, $b', 'return $a["label"] > $b["label"];'));
		
		return $options;
	}
}