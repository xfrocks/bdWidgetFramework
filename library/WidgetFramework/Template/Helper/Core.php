<?php

class WidgetFramework_Template_Helper_Core
{
    public static function snippet($string, $maxLength = 0, array $options = array())
    {
        $snippet = WidgetFramework_ShippableHelper_Html::snippet($string, $maxLength, $options);

        if (!empty($options['link'])
            && $string !== $snippet
        ) {
            /** @noinspection HtmlUnknownTarget */
            $snippet .= sprintf('<div class="readMoreLink"><a href="%s">%s</a></div>',
                $options['link'], new XenForo_Phrase('wf_read_more'));
        }

        return $snippet;
    }

    public static function canToggle(array $widget)
    {
        return strpos($widget['position'], ',') === false;
    }

    public static function getThumbnailUrl($url, array $params)
    {
        return WidgetFramework_ShippableHelper_Image::getThumbnailUrl($url, $params['width'], $params['height']);
    }
}
