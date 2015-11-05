<?php

class WidgetFramework_WidgetGroup extends WidgetFramework_WidgetRenderer
{
    public function prepare(array &$widgetRef, $positionCode, array $params, XenForo_Template_Abstract $template)
    {
        if (empty($widgetRef['widgets'])) {
            return;
        }

        foreach ($widgetRef['widgets'] as &$subWidgetRef) {
            $renderer = WidgetFramework_Core::getRenderer($subWidgetRef['class'], false);
            if ($renderer) {
                $renderer->prepare($subWidgetRef, $positionCode, $params, $template);
            }
        }
    }

    public function render(
        array &$widgetRef,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $template,
        &$output)
    {
        if (empty($widgetRef['widgets'])) {
            return '';
        }

        $subWidgetIds = array_keys($widgetRef['widgets']);

        $layout = 'rows';
        if (!empty($widgetRef['options']['layout'])) {
            switch ($widgetRef['options']['layout']) {
                case 'columns':
                case 'random':
                case 'tabs':
                    $layout = $widgetRef['options']['layout'];
                    break;
            }
        }

        if (!WidgetFramework_Option::get('layoutEditorEnabled')
            && $layout === 'random'
        ) {
            $randKey = array_rand($subWidgetIds);
            $subWidgetIds = array($subWidgetIds[$randKey]);
        }

        $subWidgets = array();
        $subWidgetParams = $params;
        $subWidgetParams[self::PARAM_PARENT_GROUP_NAME] = $widgetRef['widget_id'];

        foreach ($subWidgetIds as $subWidgetId) {
            $subWidgetRef =& $widget['widgets'][$subWidgetId];
            $subWidgetRef['_runtime']['html'] = '';
            $subWidgetRef['_runtime']['ajaxLoadUrl'] = '';

            $renderer = WidgetFramework_Core::getRenderer($subWidgetRef['class'], false);
            if (!WidgetFramework_Option::get('layoutEditorEnabled')
                && count($subWidgets) > 0
                && $layout === 'tabs'
                && !empty($renderer)
                && $renderer->canAjaxLoad($widget)
            ) {
                $subWidgetRef['_runtime']['ajaxLoadUrl'] = $renderer->getAjaxLoadUrl($subWidgetRef, $positionCode, $params, $template);
                $subWidgetRef['_runtime']['html'] = $subWidgetRef['_runtime']['ajaxLoadUrl'];
            } else {
                $subWidgetRef['_runtime']['html'] = WidgetFramework_Core::getInstance()->renderWidget($subWidgetRef,
                    $positionCode, $subWidgetParams, $template, $subWidgetRef['_runtime']['html']);
            }

            if (!empty($subWidgetRef['_runtime']['html'])
                || WidgetFramework_Option::get('layoutEditorEnabled')
            ) {
                $subWidgets[$subWidgetId] =& $subWidgetRef;
            }
        }

        return $this->_wrapWidgets($widgetRef, $subWidgets, $params, $template);
    }

    protected function _getConfiguration()
    {
        return array(
            'name' => 'Group',
            'isHidden' => true,
            'options' => array(),
        );
    }

    protected function _getOptionsTemplate()
    {
        return false;
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        // TODO: Implement _getRenderTemplate() method.
    }

    protected function _wrapWidgets(
        array &$groupRef,
        array &$widgetsRef,
        array $params,
        XenForo_Template_Abstract $template)
    {
        $wrapperTemplateName = 'wf_widget_group_wrapper';

        if (WidgetFramework_Option::get('layoutEditorEnabled')) {
            $wrapperTemplateName = 'wf_layout_editor_widget_wrapper';

            $params['groupSaveParams'] = array(
                'group_id' => $groupRef['widget_id'],
            );

            $params['conditionalParams'] = WidgetFramework_Template_Helper_Layout::prepareConditionalParams($params);
            if (!empty($groupRef['widget_page_id'])
                && !empty($params['conditionalParams']['widgetPage'])
            ) {
                $params['groupSaveParams']['widget_page_id'] = $groupRef['widget_page_id'];
                unset($params['conditionalParams']['widgetPage']);
            }
        }

        $wrapperTemplateObj = $template->create($wrapperTemplateName, $params);
        $wrapperTemplateObj->setParam('group', $groupRef);
        $wrapperTemplateObj->setParam('groupId', $groupRef['widget_id']);
        $wrapperTemplateObj->setParam('widgets', $widgetsRef);

        return $wrapperTemplateObj;
    }

    protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
    {
        throw new XenForo_Exception('not implemented');
    }
}