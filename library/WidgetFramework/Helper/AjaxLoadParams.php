<?php

class WidgetFramework_Helper_AjaxLoadParams
{
    const LINK_PARAM_NAME_LEGACY = 'alp';
    const LINK_PARAM_NAME = '_alp';

    public static function buildLink($widgetId, array $ajaxLoadParams)
    {
        $linkParams = array('widget_id' => $widgetId);
        $encoded = json_encode($ajaxLoadParams);

        if (WidgetFramework_Core::debugMode()) {
            $linkParams[self::LINK_PARAM_NAME] = $encoded;
        } else {
            $key = self::_getKey($widgetId);
            $encrypted = base64_encode(WidgetFramework_ShippableHelper_Crypt::encrypt($encoded, $key));
            $linkParams[self::LINK_PARAM_NAME] = $encrypted;
        }

        foreach (array(
                     'page',
                     '_page',
                     '_pageNumber',
                 ) as $possiblePageParamKey) {
            if (!empty($ajaxLoadParams[$possiblePageParamKey])) {
                // prepare page number for show only, this parameter is used by the rendering routine
                $linkParams['page'] = $ajaxLoadParams[$possiblePageParamKey];
            }
        }

        return XenForo_Link::buildPublicLink('full:misc/wf-widget', null, $linkParams);
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