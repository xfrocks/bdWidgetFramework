<?php

class WidgetFramework_WidgetRenderer_Stats extends WidgetFramework_WidgetRenderer
{
	public function extraPrepareTitle(array $widget)
	{
		if (empty($widget['title']))
		{
			return new XenForo_Phrase('forum_statistics');
		}

		return parent::extraPrepareTitle($widget);
	}

	protected function _getConfiguration()
	{
		return array('name' => 'Forum Statistics');
	}

	protected function _getOptionsTemplate()
	{
		return false;
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return 'wf_widget_stats';
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		if ('forum_list' == $positionCode)
		{
			$renderTemplateObject->setParam('boardTotals', $params['boardTotals']);
		}
		else
		{
			$core = WidgetFramework_Core::getInstance();
			$boardTotals = $core->getModelFromCache('XenForo_Model_DataRegistry')->get('boardTotals');
			if (!$boardTotals)
			{
				$boardTotals = $core->getModelFromCache('XenForo_Model_Counters')->rebuildBoardTotalsCounter();
			}

			$renderTemplateObject->setParam('boardTotals', $boardTotals);
		}

		return $renderTemplateObject->render();
	}

}
