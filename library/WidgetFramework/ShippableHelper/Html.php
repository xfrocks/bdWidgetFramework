<?php

// updated by DevHelper_Helper_ShippableHelper at 2016-02-04T07:10:19+00:00

/**
 * Class WidgetFramework_ShippableHelper_Html
 * @version 6
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
            'previewBreakBbCode' => 'prbreak',
            'ellipsis' => '…',
        ), $options);

        if (!empty($options['previewBreakBbCode'])
            && preg_match(sprintf('#\[%1$s\](?<preview>.*)\[/%1$s\]#', preg_quote($options['previewBreakBbCode'], '#')),
                $string, $matches, PREG_OFFSET_CAPTURE)
        ) {
            // preview break bbcode found
            if (!empty($matches['preview'][0])) {
                // preview text specified, use it directly
                $string = $matches['preview'][0];
                $maxLength = 0;
            } else {
                // use content before the found bbcode to continue
                $string = substr($string, 0, $matches[0][1]);
                $maxLength = 0;
            }
        }

        $snippet = XenForo_Template_Helper_Core::callHelper('snippet', array($string, $maxLength, $options));

        // TODO: find better way to avoid having to call this
        $snippet = htmlspecialchars_decode($snippet);

        if ($maxLength == 0 || $maxLength > utf8_strlen($string)) {
            return $snippet;
        }

        $offset = 0;
        $stack = array();
        while (true) {
            $startPos = utf8_strpos($snippet, '<', $offset);
            if ($startPos !== false) {
                $endPos = utf8_strpos($snippet, '>', $startPos);
                if ($endPos === false) {
                    // we found a partial open tag, best to delete the whole thing
                    $snippet = utf8_substr($snippet, 0, $startPos) . $options['ellipsis'];
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
                        if (count($stack) > 0) {
                            $lastInStack = array_pop($stack);
                        }

                        if ($lastInStack !== $tag) {
                            // found tag does not match the one in stack
                            $replacement = '';

                            // first we have to close the one in stack
                            if ($lastInStack !== null) {
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
                        $stack[] = $tag;
                    }
                }
            } else {
                break;
            }
        }

        while (!empty($stack)) {
            $snippet .= sprintf('</%s>', array_pop($stack));
        }

        $snippet = utf8_trim($snippet);
        if ($snippet === '') {
            // this is bad...
            // happens if the $maxLength is too low and for some reason the very first tag cannot finish
            $snippet = utf8_trim(strip_tags($string));
            if ($snippet !== '') {
                $snippet = XenForo_Template_Helper_Core::callHelper('snippet', array($snippet, $maxLength, $options));
            } else {
                // this is super bad...
                // the string is one big html tag and it is too damn long
                $snippet = $options['ellipsis'];
            }
        }

        return $snippet;
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

                if (preg_match('#name="(?<name>[^"]+)"#i', $tag, $matches)) {
                    $name = $matches['name'];
                } elseif (preg_match('#property="(?<name>[^"]+)"#i', $tag, $matches)) {
                    $name = $matches['name'];
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