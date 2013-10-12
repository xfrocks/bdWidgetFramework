<?php

class WidgetFramework_Template_Helper_Core
{
	public static function snippet($string, $maxLength = 0)
	{
		if ($maxLength > 0)
		{
			$string = strval($string);
			$string = XenForo_Helper_String::wholeWordTrim($string, $maxLength);

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
