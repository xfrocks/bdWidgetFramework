<?php

class WidgetFramework_WidgetRenderer_Stats extends WidgetFramework_WidgetRenderer
{
    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])) {
            return new XenForo_Phrase('wf_forum_statistics');
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

    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        if ('forum_list' == $positionCode) {
            $renderTemplateObject->setParam('boardTotals', $params['boardTotals']);
        } else {
            $core = WidgetFramework_Core::getInstance();
            /** @var XenForo_Model_DataRegistry $dataRegistryModel */
            $dataRegistryModel = $core->getModelFromCache('XenForo_Model_DataRegistry');
            /** @var XenForo_Model_Counters $countersModel */
            $countersModel = $core->getModelFromCache('XenForo_Model_Counters');

            $boardTotals = $dataRegistryModel->get('boardTotals');
            if (!$boardTotals) {
                $boardTotals = $countersModel->rebuildBoardTotalsCounter();
            }

            $renderTemplateObject->setParam('boardTotals', $boardTotals);
        }

        return $renderTemplateObject->render();
    }

}
