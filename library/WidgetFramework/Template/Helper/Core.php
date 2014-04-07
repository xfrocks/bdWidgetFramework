<?php

class WidgetFramework_Template_Helper_Core
{
	public static function snippet($string, $maxLength = 0, array $options = array())
	{
		$gotCutOff = false;

		$posOpen = strpos($string, '<span class="prbreak"');
		if ($posOpen !== false)
		{
			if (preg_match('#<span class="prbreak"[^>]+>([^<]*)</span>#', $string, $matches, PREG_OFFSET_CAPTURE))
			{
				$string = substr($string, 0, $matches[0][1]);
				$gotCutOff = true;

				if (!empty($options['link']))
				{
					if (!empty($matches[1][0]))
					{
						$linkText = $matches[1][0];
					}
					else
					{
						$linkText = new XenForo_Phrase('wf_read_more');
					}

					$string .= sprintf('<div class="readMoreLink"><a href="%s">%s</a></div>', $options['link'], $linkText);
				}
			}
		}

		if ($maxLength > 0)
		{
			$string = strval($string);
			$string = XenForo_Helper_String::wholeWordTrim($string, $maxLength);
			$gotCutOff = true;
		}

		if ($gotCutOff)
		{
			// try to make valid HTML output
			$offset = 0;
			$opened = array();
			while (true)
			{
				$pos = strpos($string, '<', $offset);

				if ($pos === false)
				{
					// while (true)
					break;
				}

				$pos2 = strpos($string, '>', $pos);
				if ($pos2 !== false)
				{
					$tag = strtolower(trim(substr($string, $pos + 1, $pos2 - $pos - 1)));

					if (substr($tag, 0, 1) == '/')
					{
						// closing
						$pop = array_pop($opened);
						if ($pop === substr($tag, 1))
						{
							// good closing
						}
						else
						{
							// bad closing
							$opened[] = $pop;
						}
					}
					elseif (substr($tag, -1) == '/')
					{
						// self-closing: ignore
					}
					else
					{
						// opening
						$tagSpacePos = strpos($tag, ' ');
						if ($tagSpacePos !== false)
						{
							$tag = substr($tag, 0, $tagSpacePos);
						}

						$opened[] = $tag;
					}
				}
				else
				{
					// the opening/closing is not finished yet
					// remove all of it, then stop
					$string = substr($string, 0, $pos);

					// while (true)
					break;
				}

				$offset = $pos + 1;
			}

			while (count($opened) > 0)
			{
				// automatically add closing tags for opened ones
				$string .= sprintf('</%s>', array_pop($opened));
			}
		}

		return $string;
	}

}
