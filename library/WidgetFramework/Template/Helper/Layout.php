<?php

class WidgetFramework_Template_Helper_Layout
{
    public static function generateCssMedia($minWidth, $maxWidth, $hasSidebar, array &$cssMedia, array $rules)
    {
        $mediaStatements = array();

        if ($minWidth === 0) {
            $maxMediaWidths = self::getMediaWidths($maxWidth, $hasSidebar);

            foreach ($maxMediaWidths as $maxMediaWidth) {
                if (is_array($maxMediaWidth)) {
                    if ($maxMediaWidth[2]) {
                        // has sidebar
                        $mediaStatements[] = sprintf('screen and (min-width: %dpx) and (max-width: %dpx)',
                            $maxMediaWidth[0], $maxMediaWidth[1]);
                    } else {
                        $mediaStatements[] = sprintf('screen and (max-width: %dpx)', $maxMediaWidth[1]);
                    }
                } else {
                    $mediaStatements[] = sprintf('screen and (max-width: %dpx)', $maxMediaWidth);
                }
            }
        } elseif ($maxWidth === 0) {
            $minMediaWidths = self::getMediaWidths($minWidth, $hasSidebar);

            foreach ($minMediaWidths as $minMediaWidth) {
                if (is_array($minMediaWidth)) {
                    if ($minMediaWidth[2]) {
                        // has sidebar
                        $mediaStatements[] = sprintf('screen and (min-width: %dpx)', $minMediaWidth[1]);
                    } else {
                        $mediaStatements[] = sprintf('screen and (min-width: %dpx) and (max-width: %dpx)',
                            $minMediaWidth[0], $minMediaWidth[1]);
                    }
                } else {
                    $mediaStatements[] = sprintf('screen and (min-width: %dpx)', $minMediaWidth);
                }
            }
        } else {
            $minMediaWidths = self::getMediaWidths($minWidth, $hasSidebar);
            $maxMediaWidths = self::getMediaWidths($maxWidth, $hasSidebar);
            $ranges = array();

            foreach ($minMediaWidths as $minMediaWidth) {
                foreach ($maxMediaWidths as $maxMediaWidth) {
                    if (is_array($minMediaWidth)
                        && is_array($maxMediaWidth)
                        && $minMediaWidth[2] != $maxMediaWidth[2]
                    ) {
                        // split into two ranges because sidebar appearance does not match
                        $ranges[] = array(
                            $minMediaWidth[0],
                            $minMediaWidth[1]
                        );
                        $ranges[] = array(
                            $maxMediaWidth[0],
                            $maxMediaWidth[1]
                        );
                        continue;
                    }

                    if (is_array($minMediaWidth)) {
                        if ($minMediaWidth[2]) {
                            // has sidebar
                            $min = $minMediaWidth[1];
                        } else {
                            // no sidebar
                            $min = $minMediaWidth[0];
                        }
                    } else {
                        $min = $minMediaWidth;
                    }

                    if (is_array($maxMediaWidth)) {
                        if ($maxMediaWidth[2]) {
                            // has sidebar
                            $max = $maxMediaWidth[1];
                        } else {
                            // no sidebar
                            $max = $maxMediaWidth[0];
                        }
                    } else {
                        $max = $maxMediaWidth;
                    }

                    $ranges[] = array(
                        $min,
                        $max
                    );
                }
            }

            foreach ($ranges as $range) {
                if ($range[0] > 0) {
                    $mediaStatements[] = sprintf('screen and (min-width: %dpx) and (max-width: %dpx)',
                        $range[0], $range[1]);
                } else {
                    $mediaStatements[] = sprintf('screen and (max-width: %dpx)', $range[1]);
                }
            }
        }

        foreach ($mediaStatements as $mediaStatement) {
            foreach ($rules as $rule) {
                $cssMedia[$mediaStatement][] = $rule;
            }
        }
    }

    public static function getMediaWidths($width, $hasSidebar)
    {
        static $results = array();

        $hash = md5(sprintf('%d-%d', $width, $hasSidebar ? 1 : 0));
        if (isset($results[$hash])) {
            // try to return cached results
            return $results[$hash];
        }
        $results[$hash] = array();
        $mediaWidths = &$results[$hash];

        static $styleProperties = null;
        if ($styleProperties === null) {
            // do this only once because
            // XenForo_Template_Helper_Core::styleProperty is super complicated
            $styleProperties = array();

            $styleProperties['sidebarWidth'] = intval(XenForo_Template_Helper_Core::styleProperty('sidebar.width'));
            $styleProperties['pageWidth'] = intval(XenForo_Template_Helper_Core::styleProperty('pageWidth.width'));
            $styleProperties['enableResponsive'] = !!XenForo_Template_Helper_Core::styleProperty('enableResponsive');
            $styleProperties['maxResponsiveWideWidth'] = intval(XenForo_Template_Helper_Core::styleProperty('maxResponsiveWideWidth'));

            $widthDelta = 0;
            $widthDelta += intval(XenForo_Template_Helper_Core::styleProperty('pageWidth.padding-left'));
            $widthDelta += intval(XenForo_Template_Helper_Core::styleProperty('pageWidth.padding-right'));
            $widthDelta += intval(XenForo_Template_Helper_Core::styleProperty('content.padding-left'));
            $widthDelta += intval(XenForo_Template_Helper_Core::styleProperty('content.padding-right'));

            $styleProperties['widthDelta'] = $widthDelta;
        }

        if (empty($styleProperties['enableResponsive'])) {
            // not responsive, do nothing
            return $mediaWidths;
        }

        if (!empty($styleProperties['pageWidth'])) {
            // fixed width, do nothing
            return $mediaWidths;
        }

        $width += $styleProperties['widthDelta'];

        if (!$hasSidebar) {
            $mediaWidths[] = $width;
        } else {
            if ($styleProperties['maxResponsiveWideWidth'] > $width) {
                // $width is small enough, sidebar is going to be hidden
                $mediaWidths[] = array(
                    $width,
                    $styleProperties['maxResponsiveWideWidth'],

                    // false means sidebar is hidden
                    false,
                );
            }

            $tmpWidth = $width + $styleProperties['sidebarWidth'];
            if ($tmpWidth > $styleProperties['maxResponsiveWideWidth']) {
                // $tmpWidth is large enough, sidebar is shown and left enough space for content
                $mediaWidths[] = array(
                    $styleProperties['maxResponsiveWideWidth'],
                    $tmpWidth,

                    // true means sidebar is shown
                    true,
                );
            }
        }

        return $mediaWidths;
    }

    public static function prepareConditionalParams(array $params, array $exclude = null, $level = 0)
    {
        if ($exclude === null
            && $level == 0
        ) {
            $exclude = array(
                // list of keys from XenForo_Dependencies_Abstract
                'session',
                'sessionId',
                'requestPaths',
                'cookieConfig',
                'currentVersion',
                'jsVersion',
                'visitor',
                'visitorLanguage',
                'visitorStyle',
                'userFieldsInfo',
                'pageIsRtl',
                'xenOptions',
                'xenCache',
                'xenAddOns',
                'serverTime',
                'debugMode',
                'javaScriptSource',
                'viewName',
                'controllerName',
                'controllerAction',

                // list of keys for PAGE_CONTAINER
                'majorSection',
                'tosUrl',
                'jQuerySource',
                'jQuerySourceLocal',
                'homeTabId',
                'homeLink',
                'logoLink',
                'title',
                'h1',
                'quickNavSelected',
                'topctrl',
                'debug_url',
            );
        }

        $prepared = array();
        if ($level > 1) {
            return $prepared;
        }

        foreach ($params as $key => &$value) {
            if (in_array($key, $exclude, true) OR substr($key, 0, 1) === '_') {
                // excluded
            } elseif (is_object($value) OR empty($value)) {
                // ignore
            } elseif (is_array($value)) {
                $valueKeys = array_keys($value);
                if (!is_numeric($valueKeys[0])) {
                    // only process object-like array
                    $valueExclude = array();
                    $valuePrepared = self::prepareConditionalParams($value, $valueExclude, $level + 1);

                    if (!empty($valuePrepared)) {
                        $prepared[$key] = $valuePrepared;
                    }
                }
            } elseif (is_numeric($value)) {
                if (strpos($key, '_id') !== false) {
                    $prepared[$key] = $value;
                }
            } elseif (is_string($value)) {
                if (strlen($value) < 255
                    && $level == 0
                ) {
                    $prepared[$key] = $value;
                }
            }
        }

        return $prepared;
    }

}
