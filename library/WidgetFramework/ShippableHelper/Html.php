<?php

// updated by DevHelper_Helper_ShippableHelper at 2017-01-23T07:29:22+00:00

/**
 * Class WidgetFramework_ShippableHelper_Html
 * @version 14
 * @see DevHelper_Helper_ShippableHelper_Html
 */
class WidgetFramework_ShippableHelper_Html
{
    public static function preSnippet(array &$message, XenForo_BbCode_Parser $parser, array $options = array())
    {
        $options = array_merge(array(
            'previewBreakBbCode' => 'prbreak',
            'messageKey' => 'message',
        ), $options);

        $previewFound = false;
        $tagOpen = '';
        $tagClose = '';

        if (!empty($options['previewBreakBbCode'])) {
            $textRef =& $message[$options['messageKey']];
            $tagOpen = '[' . $options['previewBreakBbCode'] . ']';
            $posOpen = stripos($textRef, $tagOpen);
            if ($posOpen !== false) {
                $tagClose = '[/' . $options['previewBreakBbCode'] . ']';
                $posClose = stripos($textRef, $tagClose, $posOpen);
                if ($posClose !== false) {
                    $previewFound = true;
                    $previewTextOffset = $posOpen + strlen($tagOpen);
                    $previewTextLength = $posClose - $posOpen - strlen($tagOpen);

                    if ($previewTextLength > 0) {
                        $textRef = substr($textRef, $previewTextOffset, $previewTextLength);

                    } else {
                        $textRef = substr($textRef, 0, $posOpen);
                    }
                }
            }
        }

        $messageHtml = XenForo_ViewPublic_Helper_Message::getBbCodeWrapper(
            $message, $parser, $options);

        if ($previewFound) {
            $messageHtml = sprintf('%s%s%s', $tagOpen, $messageHtml, $tagClose);
        }

        return $messageHtml;
    }

    /**
     * @param string $string
     * @param int $maxLength
     * @param array $options
     *
     * @return string
     */
    public static function snippet($string, $maxLength = 0, array $options = array())
    {
        $options = array_merge(array(
            'ellipsis' => '…',
            'fromStart' => true,
            'previewBreakBbCode' => 'prbreak',
            'processImage' => true,
            'processLink' => true,
            'processFrame' => true,
            'processScript' => true,
            'replacements' => array(),
            'stripImage' => false,
            'stripLink' => false,
            'stripFrame' => false,
            'stripScript' => false,
            'stripSpacing' => true,

            // WARNING: options below are for internal usage only
            '_isPreview' => false,
        ), $options);
        $options['maxLength'] = $maxLength;

        self::snippetPreProcess($string, $options);
        if (!empty($options['_isPreview'])) {
            return $string;
        }

        $snippet = self::snippetCallHelper($string, $options);

        self::snippetFixBrokenHtml($snippet, $options);

        self::snippetPostProcess($snippet, $options);

        if ($snippet === '') {
            $plainTextString = utf8_trim(strip_tags($string));
            if ($plainTextString !== '') {
                $snippet = self::snippetCallHelper($plainTextString, $options);
            } else {
                $snippet = $options['ellipsis'];
            }
        }

        return $snippet;
    }

    public static function snippetPreProcess(&$string, array &$options)
    {
        if (!empty($options['previewBreakBbCode'])
            && preg_match(sprintf('#\[%1$s\](?<' . 'preview>.*)\[/%1$s\]#',
                preg_quote($options['previewBreakBbCode'], '#')),
                $string, $matches, PREG_OFFSET_CAPTURE)
        ) {
            // preview break bbcode found
            if (!empty($matches['preview'][0])) {
                // preview text specified, use it directly
                $string = $matches['preview'][0];
                $options['maxLength'] = 0;
                $options['_isPreview'] = true;
                return $string;
            } else {
                // use content before the found bbcode to continue
                $string = substr($string, 0, $matches[0][1]);
                $options['maxLength'] = 0;
            }
        }

        $string = preg_replace('#<br\s?\/?>#', "\n", $string);
        $string = str_replace('&#8203;', '', $string);

        $replacementsRef =& $options['replacements'];
        $replacementTags = array();
        if (!!$options['processImage'] && !$options['stripImage']) {
            $replacementTags[] = 'img';
        }
        if (!!$options['processLink'] && !$options['stripLink']) {
            $replacementTags[] = 'a';
        }
        if (!!$options['processFrame'] && !$options['stripFrame']) {
            $replacementTags[] = 'iframe';
        }
        if (!!$options['processScript'] && !$options['stripScript']) {
            $replacementTags[] = 'script';
        }
        if (count($replacementTags) > 0) {
            $replacementOffset = 0;
            $replacementRegEx = sprintf('#<(%s)(\s[^>]*)?>.*?</\\1>#i', implode('|', $replacementTags));
            while (true) {
                if (!preg_match($replacementRegEx, $string,
                    $replacementMatches, PREG_OFFSET_CAPTURE, $replacementOffset)
                ) {
                    break;
                }
                $replacement = array(
                    'original' => $replacementMatches[0][0],
                    'replacement' => sprintf('<br data-replacement-id="%d" />', count($replacementsRef)),
                );
                $replacementsRef[] = $replacement;
                $string = str_replace($replacement['original'], $replacement['replacement'], $string);
                $replacementOffset = $replacementMatches[0][1] + 1;
            }
        }

        if (!!$options['stripImage']) {
            $string = preg_replace('#<img[^>]+/>#', '', $string);
        }
        if (!!$options['stripLink']) {
            $string = preg_replace('#<a[^>]+>(.+?)</a>#', '$1', $string);
        }
        if (!!$options['stripFrame']) {
            $string = preg_replace('#<iframe[^>]+></iframe>#', '', $string);
        }
        if (!!$options['stripScript']) {
            $string = preg_replace('#<script[^>]+>.*?</script>#', '', $string);
        }
        if (!!$options['stripSpacing']) {
            $string = preg_replace('#(\n\s*)+#', "\n", $string);
        }
    }

    public static function snippetCallHelper($string, array &$options)
    {
        $snippet = XenForo_Template_Helper_Core::callHelper('snippet', array($string, $options['maxLength'], $options));

        // TODO: find better way to avoid having to call this to reset snippet
        $snippet = htmlspecialchars_decode($snippet);
        $snippet = preg_replace('#\.\.\.\z#', '', $snippet);

        return $snippet;
    }

    public static function snippetFixBrokenHtml(&$snippet, array &$options)
    {
        $offset = 0;
        $stack = array();
        while (true) {
            $startPos = utf8_strpos($snippet, '<', $offset);
            if ($startPos !== false) {
                $endPos = utf8_strpos($snippet, '>', $startPos);
                if ($endPos === false) {
                    // we found a partial open tag, best to delete the whole thing
                    $snippet = utf8_substr($snippet, 0, $startPos);
                    break;
                }

                $foundLength = $endPos - $startPos - 1;
                $found = utf8_substr($snippet, $startPos + 1, $foundLength);
                $offset = $endPos;

                if (preg_match('#^(?<closing>/?)(?<tag>\w+)#', $found, $matches)) {
                    $tag = $matches['tag'];
                    $isClosing = !empty($matches['closing']);
                    $isSelfClosing = (!$isClosing && (utf8_substr($found, $foundLength - 1, 1) === '/'));

                    if ($isClosing) {
                        $lastInStack = null;
                        $lastInStackTag = null;
                        if (count($stack) > 0) {
                            $lastInStack = array_pop($stack);
                            $lastInStackTag = $lastInStack['tag'];
                        }

                        if ($lastInStackTag !== $tag) {
                            // found tag does not match the one in stack
                            $replacement = '';

                            // first we have to close the one in stack
                            if ($lastInStackTag !== null) {
                                $replacement .= sprintf('</%s>', $tag);
                            }

                            // then we have to self close the found tag
                            $replacement .= utf8_substr($snippet, $startPos, $endPos - $startPos - 1);
                            $replacement .= '/>';

                            // do the replacement
                            $snippet = utf8_substr_replace($snippet, $replacement, $startPos, $endPos - $startPos);
                            $offset = $startPos + utf8_strlen($snippet);
                        }
                    } elseif ($isSelfClosing) {
                        // do nothing
                    } else {
                        // is opening tag
                        $stack[] = array('tag' => $tag, 'offset' => $startPos);
                    }
                }
            } else {
                break;
            }
        }

        // close any remaining tags
        while (!empty($stack)) {
            $stackItem = array_pop($stack);

            self::snippetAppendEllipsis($snippet, $options);
            $snippet .= sprintf('</%s>', $stackItem['tag']);
        }
    }

    public static function snippetAppendEllipsis(&$snippet, array &$options)
    {
        if (empty($options['ellipsis'])) {
            return;
        }

        if (!preg_match('#<\/?(div|iframe|img|li|ol|p|script|ul)[^>]*>\z#', $snippet)) {
            $snippet .= $options['ellipsis'];
            $options['ellipsis'] = '';
        }
    }

    public static function snippetPostProcess(&$snippet, array &$options)
    {
        // strip all empty body tags
        $snippetBinaryLength = 0;
        while (strlen($snippet) !== $snippetBinaryLength) {
            $snippetBinaryLength = strlen($snippet);
            $snippet = preg_replace('#<(\w+)(\s[^>]+)?>\s*<\/\\1>#', '', $snippet);
        }

        // restore replacements
        foreach ($options['replacements'] as $replacement) {
            $snippet = str_replace($replacement['replacement'], $replacement['original'], $snippet);
        }

        // remove invisible characters
        $snippet = utf8_trim($snippet);

        // restore line breaks
        $snippet = nl2br($snippet);

        self::snippetAppendEllipsis($snippet, $options);

        $snippet = utf8_trim($snippet);
    }

    public static function stripFont($html)
    {
        $html = preg_replace('#(<[^>]+)( style="[^"]+")([^>]*>)#', '$1$3', $html);
        $html = preg_replace('#<\/?(b|i)>#', '', $html);

        return $html;
    }

    public static function getMetaTags($html)
    {
        $tags = array();

        $headPos = strpos($html, '</head>');
        if ($headPos === false) {
            return $tags;
        }

        $head = substr($html, 0, $headPos);

        $offset = 0;
        while (true) {
            if (preg_match('#<meta[^>]+>#i', $head, $matches, PREG_OFFSET_CAPTURE, $offset)) {
                $tag = $matches[0][0];
                $offset = $matches[0][1] + strlen($tag);
                $name = null;
                $value = null;

                if (preg_match('#(name|property)="(?<name>[^"]+)"#i', $tag, $matches)) {
                    $name = $matches['name'];
                } elseif (preg_match('#itemprop="(?<itemprop>[^"]+)"#i', $tag, $matches)) {
                    $name = $matches['itemprop'];
                } else {
                    continue;
                }

                if (preg_match('#content="(?<value>[^"]+)"#', $tag, $matches)) {
                    $value = self::entityDecode($matches['value']);
                } else {
                    continue;
                }

                $tags[] = array(
                    'name' => $name,
                    'value' => $value,
                );
            } else {
                break;
            }
        }

        return $tags;
    }

    public static function getTitleTag($html)
    {
        if (preg_match('#<title>(?<title>[^<]+)</title>#i', $html, $matches)) {
            return self::entityDecode($matches['title']);
        }

        return '';
    }

    public static function entityDecode($html)
    {
        $decoded = $html;

        // required to deal with &quot; etc.
        $decoded = html_entity_decode($decoded, ENT_COMPAT, 'UTF-8');

        // required to deal with &#1234; etc.
        $convmap = array(0x0, 0x2FFFF, 0, 0xFFFF);
        $decoded = mb_decode_numericentity($decoded, $convmap, 'UTF-8');

        return $decoded;
    }
}