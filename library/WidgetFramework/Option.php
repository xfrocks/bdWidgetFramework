<?php

class WidgetFramework_Option
{

	protected static $_revealEnabled = null;

	public static function get($key)
	{
		$options = XenForo_Application::get('options');

		switch ($key)
		{
			case 'cacheCutoffDays':
				return 7;
			case 'revealEnabled':
				if (self::$_revealEnabled === null)
				{
					$session = XenForo_Application::get('session');
					if (empty($session))
					{
						// no session yet...
						return false;
					}
					self::$_revealEnabled = ($session->get('_WidgetFramework_reveal') == true);
				}

				// use the cached value
				return self::$_revealEnabled;
		}

		return $options->get('WidgetFramework_' . $key);
	}

}
