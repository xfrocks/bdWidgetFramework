<?php

class WidgetFramework_NodeHandler_WidgetPage extends XenForo_NodeHandler_Abstract
{

	protected $_widgetPageModel = null;

	public function isNodeViewable(array $node, array $nodePermissions)
	{
		return $this->_getWidgetPageModel()->canViewWidgetPage($node, $null, $nodePermissions);
	}

	public function renderNodeForTree(XenForo_View $view, array $node, array $permissions, array $renderedChildren, $level)
	{
		$templateLevel = ($level <= 2 ? $level : 'n');

		return $view->createTemplateObject('wf_node_widget_page_level_' . $templateLevel, array(
			'level' => $level,
			'widgetPage' => $node,
			'renderedChildren' => $renderedChildren
		));
	}

	protected function _getWidgetPageModel()
	{
		if ($this->_widgetPageModel === null)
		{
			$this->_widgetPageModel = XenForo_Model::create('WidgetFramework_Model_WidgetPage');
		}

		return $this->_widgetPageModel;
	}

}
