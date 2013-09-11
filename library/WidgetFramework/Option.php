<?php

class WidgetFramework_Option
{
	const SIMPLE_CACHE_KEY_INDEX_NODE_ID = 'WidgetFramework_indexNodeId';

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
			case 'indexNodeId':
				return XenForo_Application::getSimpleCacheData(self::SIMPLE_CACHE_KEY_INDEX_NODE_ID);
		}

		return $options->get('WidgetFramework_' . $key);
	}
	
	public static function setIndexNodeId($nodeId)
	{
		XenForo_Application::setSimpleCacheData(self::SIMPLE_CACHE_KEY_INDEX_NODE_ID, $nodeId);
	}

}
