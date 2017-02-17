<?php

class WidgetFramework_Helper_AjaxLoadParams
{
    const LINK_PARAM_NAME_LEGACY = 'alp';
    const LINK_PARAM_NAME = '_alp';

    public static function buildLink($widgetId, array $ajaxLoadParams)
    {
        $encoded = json_encode($ajaxLoadParams);

        if (WidgetFramework_Core::debugMode()) {
            $encrypted = $encoded;
        } else {
            $key = self::_getKey($widgetId);
            $encrypted = base64_encode(WidgetFramework_ShippableHelper_Crypt::encrypt($encoded, $key));
        }

        return XenForo_Link::buildPublicLink('full:misc/wf-widget', null, array(
            'widget_id' => $widgetId,
            self::LINK_PARAM_NAME => $encrypted,
        ));
    }

    public static function filterInput($widgetId, XenForo_Input $input)
    {
        $inputValue = $input->filterSingle(self::LINK_PARAM_NAME, XenForo_Input::STRING);
        if (empty($inputValue)) {
            $inputValue = $input->filterSingle(self::LINK_PARAM_NAME_LEGACY, XenForo_Input::STRING);
            if (empty($inputValue)) {
                return array();
            }
        }

        $key = self::_getKey($widgetId);
        $decrypted = WidgetFramework_ShippableHelper_Crypt::decrypt(base64_decode($inputValue), $key);
        if (empty($decrypted)) {
            $decrypted = $inputValue;
        }

        $alp = @json_decode($decrypted, true);
        if (empty($alp)) {
            return array();
        }

        return $alp;
    }

    protected static function _getKey($widgetId)
    {
        return sprintf('%d-%s', $widgetId, XenForo_Application::getConfig()->get('globalSalt'));
    }
}