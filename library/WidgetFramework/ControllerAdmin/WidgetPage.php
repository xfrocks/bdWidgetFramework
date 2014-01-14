<?php

class WidgetFramework_ControllerAdmin_WidgetPage extends XenForo_ControllerAdmin_NodeAbstract
{
	protected $_nodeDataWriterName = 'WidgetFramework_DataWriter_WidgetPage';

	public function actionIndex()
	{
		return $this->responseReroute('XenForo_ControllerAdmin_Node', 'index');
	}

	public function actionAdd()
	{
		return $this->responseReroute(__CLASS__, 'edit');
	}

	public function actionEdit()
	{
		if ($nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT))
		{
			// if a node ID was specified, we should be editing, so make sure a page exists
			$widgetPage = $this->_getWidgetPageModel()->getWidgetPageById($nodeId);
			if (empty($widgetPage))
			{
				return $this->responseError(new XenForo_Phrase('wf_requested_widget_page_not_found'), 404);
			}

			$widgets = $this->_getWidgetModel()->getWidgetPageWidgets($widgetPage['node_id']);
		}
		else
		{
			// add a new page
			$widgetPage = array(
				'parent_node_id' => $this->_input->filterSingle('parent_node_id', XenForo_Input::UINT),
				'display_order' => 1,
				'display_in_list' => 1
			);
			$widgets = array();
		}

		$viewParams = array(
			'widgetPage' => $widgetPage,
			'widgets' => $widgets,
			'nodeParentOptions' => $this->_getNodeModel()->getNodeOptionsArray($this->_getNodeModel()->getPossibleParentNodes($widgetPage), $widgetPage['parent_node_id'], true),
			'styles' => $this->_getStyleModel()->getAllStylesAsFlattenedTree(),
			'natOptions' => (XenForo_Application::isRegistered('nodesAsTabsAPI') ? NodesAsTabs_API::nodeOptionsRecord($nodeId) : false)
		);

		return $this->responseView('WidgetFramework_ViewAdmin_WidgetPage_Edit', 'wf_widget_page_edit', $viewParams);
	}

	public function actionSave()
	{
		$this->_assertPostOnly();

		if ($this->_input->filterSingle('delete', XenForo_Input::STRING))
		{
			return $this->responseReroute(__CLASS__, 'deleteConfirm');
		}

		$data = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'description' => XenForo_Input::STRING,
			'node_name' => XenForo_Input::STRING,
			'node_type_id' => XenForo_Input::BINARY,
			'parent_node_id' => XenForo_Input::UINT,
			'display_order' => XenForo_Input::UINT,
			'display_in_list' => XenForo_Input::UINT,
			'style_id' => XenForo_Input::UINT,
			'options' => XenForo_Input::ARRAY_SIMPLE,
		));
		$widgetsInput = $this->_input->filterSingle('widgets', XenForo_Input::ARRAY_SIMPLE);
		$toggler = $this->_input->filterSingle('toggler', XenForo_Input::ARRAY_SIMPLE);

		if (!$this->_input->filterSingle('style_override', XenForo_Input::UINT))
		{
			$data['style_id'] = 0;
		}

		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);

		// save page
		$dw = $this->_getNodeDataWriter();
		if ($nodeId)
		{
			$dw->setExistingData($nodeId);
		}
		$dw->bulkSet($data);
		$dw->save();

		if ($this->_input->filterSingle('is_index', XenForo_Input::UINT))
		{
			WidgetFramework_Option::setIndexNodeId($dw->get('node_id'));
		}
		elseif (WidgetFramework_Option::get('indexNodeId') == $dw->get('node_id'))
		{
			WidgetFramework_Option::setIndexNodeId(0);
		}

		// save widgets
		$widgets = $this->_getWidgetModel()->getWidgetPageWidgets($dw->get('node_id'));
		$widgetOptionsChanged = 0;
		$preferStaying = 0;

		foreach ($widgets as $widget)
		{
			$changed = 0;
			$newOptions = $widget['options'];
			$newValues = array();

			if (!empty($widgetsInput[$widget['widget_id']]))
			{
				$inputRef = &$widgetsInput[$widget['widget_id']];

				if (isset($inputRef['layout_row']) AND (!isset($widget['options']['layout_row']) OR ($inputRef['layout_row'] != $widget['options']['layout_row'])))
				{
					$changed++;
					$widgetOptionsChanged++;
					$newOptions['layout_row'] = $inputRef['layout_row'];
				}
				if (isset($inputRef['layout_col']) AND (!isset($widget['options']['layout_col']) OR ($inputRef['layout_col'] != $widget['options']['layout_col'])))
				{
					$changed++;
					$widgetOptionsChanged++;
					$newOptions['layout_col'] = $inputRef['layout_col'];
				}
				if (isset($inputRef['layout_sizeRow']) AND (!isset($widget['options']['layout_sizeRow']) OR ($inputRef['layout_sizeRow'] != $widget['options']['layout_sizeRow'])))
				{
					$changed++;
					$widgetOptionsChanged++;
					$newOptions['layout_sizeRow'] = $inputRef['layout_sizeRow'];
				}
				if (isset($inputRef['layout_sizeCol']) AND (!isset($widget['options']['layout_sizeCol']) OR ($inputRef['layout_sizeCol'] != $widget['options']['layout_sizeCol'])))
				{
					$changed++;
					$widgetOptionsChanged++;
					$newOptions['layout_sizeCol'] = $inputRef['layout_sizeCol'];
				}
			}

			if (!empty($toggler['exists'][$widget['widget_id']]))
			{
				$widgetActive = empty($toggler['id'][$widget['widget_id']]) ? 0 : intval($toggler['id'][$widget['widget_id']]);

				if ($widgetActive != $widget['active'])
				{
					$changed++;
					$preferStaying++;
					$newValues['active'] = $widgetActive;
				}
			}

			if ($changed > 0)
			{
				$widgetDw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
				$widgetDw->setExistingData($widget, true);
				$widgetDw->set('options', $newOptions);
				$widgetDw->bulkSet($newValues);
				$widgetDw->save();
			}
		}

		$link = XenForo_Link::buildAdminLink('widget-pages/edit', $dw->getMergedData());

		if ($widgetOptionsChanged == 0 AND $preferStaying > 0 AND $this->_noRedirect())
		{
			// skip redirect
			$link = false;
		}

		if ($link !== false)
		{
			$response = $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $link);
		}
		else
		{
			$response = $this->responseMessage(new XenForo_Phrase('redirect_changes_saved_successfully'));
		}

		if (XenForo_Application::isRegistered('nodesAsTabsAPI'))
		{
			NodesAsTabs_API::actionSave($response, $this);
		}

		return $response;
	}

	public function actionDelete()
	{
		$response = parent::actionDelete();

		if (XenForo_Application::isRegistered('nodesAsTabsAPI'))
		{
			NodesAsTabs_API::actionDelete($response, $this);
		}

		return $response;
	}

	public function actionDeleteConfirm()
	{
		$nodeModel = $this->_getNodeModel();

		$widgetPage = $nodeModel->getNodeById($this->_input->filterSingle('node_id', XenForo_Input::UINT));
		if (!$widgetPage)
		{
			return $this->responseError(new XenForo_Phrase('requested_page_not_found'), 404);
		}

		$childNodes = $nodeModel->getChildNodes($widgetPage);

		$viewParams = array(
			'widgetPage' => $widgetPage,
			'childNodes' => $childNodes,
			'nodeParentOptions' => $nodeModel->getNodeOptionsArray($nodeModel->getPossibleParentNodes($widgetPage), $widgetPage['parent_node_id'], true)
		);

		return $this->responseView('WidgetFramework_ViewAdmin_WidgetPage_Delete', 'wf_widget_page_delete', $viewParams);
	}

	public function actionValidateField()
	{
		$response = parent::actionValidateField();

		if (XenForo_Application::isRegistered('nodesAsTabsAPI'))
		{
			NodesAsTabs_API::actionValidateField($response, $this);
		}

		return $response;
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

	/**
	 * @return XenForo_DataWriter_Page
	 */
	protected function _getNodeDataWriter()
	{
		return XenForo_DataWriter::create($this->_nodeDataWriterName);
	}

}
