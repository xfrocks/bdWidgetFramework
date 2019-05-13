<?php

class WidgetFramework_WidgetRenderer_Callback extends WidgetFramework_WidgetRenderer
{
    protected function _getConfiguration()
    {
        return array(
            'name' => '[Advanced] PHP Callback',
            'options' => array(
                'callback_class' => XenForo_Input::STRING,
                'callback_method' => XenForo_Input::STRING,
            ),
        );
    }

    protected function _getOptionsTemplate()
    {
        return 'wf_widget_options_callback';
    }

    public function parseOptionsInput(XenForo_Input $input, array $widget)
    {
        $options = parent::parseOptionsInput($input, $widget);

        if (($class = $options['callback_class']) &&
            ($method = $options['callback_method']) &&
            (
                !XenForo_Application::autoload($class) ||
                !method_exists($class, $method)
            )) {
            throw new XenForo_Exception(new XenForo_Phrase('please_enter_valid_callback_method'), true);
        }

        return $options;
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return false;
    }

    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        if (empty($widget['options']['callback_class']) ||
            empty($widget['options']['callback_method'])) {
            return '';
        }

        $callback = array($widget['options']['callback_class'], $widget['options']['callback_method']);
        $args = array($widget, $positionCode, $params, $renderTemplateObject);
        return $this->callUserFuncArray($callback, $args);
    }

    protected function callUserFuncArray($callback, array $args)
    {
        if (!is_callable($callback) && !XenForo_Application::debugMode()) {
            return '';
        }

        return call_user_func_array($callback, $args);
    }

    public static function dump($widget)
    {
        return XenForo_Template_Helper_Core::helperDump($widget);
    }
}
