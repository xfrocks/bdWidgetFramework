<?php

class WidgetFramework_Helper_String
{
	public static function createArrayOfStrings(array $strings, $glue = '')
	{
		return new _WidgetFramework_ArrayOfString($glue, $strings);
	}

	public static function createWidgetTitleDelayed(WidgetFramework_WidgetRenderer $renderer, array $widget)
	{
		return new _WidgetFramework_WidgetTitleDelayed($renderer, $widget);
	}

}

class _WidgetFramework_ArrayOfString
{
	protected $_glue;
	protected $_arrays;

	public function __construct($glue, array $array)
	{
		$this->_glue = $glue;
		$this->_arrays = $array;
	}

	public function __toString()
	{
		$strings = array();
		$exceptions = array();

		foreach ($this->_arrays as $item)
		{
			if (is_string($item))
			{
				$strings[] = $item;
			}
			elseif ($item instanceof XenForo_Template_Abstract)
			{
				try
				{
					$strings[] = $item->render();
				}
				catch (Exception $e)
				{
					$exceptions[] = $e;
				}
			}
			else
			{
				try
				{
					$strings[] = strval($item);
				}
				catch (Exception $e)
				{
					$exceptions[] = $e;
				}
			}
		}

		if (!empty($exceptions))
		{
			if (WidgetFramework_Core::debugMode())
			{
				// do this to display the exception (only done in our debug mode)
				var_dump($exceptions);
				exit ;
			}
			else
			{
				// throw the first exception to let people know that something is wrong
				$e = reset($exceptions);
				throw $e;
			}
		}

		return implode($this->_glue, $strings);
	}

}

class _WidgetFramework_WidgetTitleDelayed
{
	static protected $_newInstances = array();

	protected $_renderer;
	protected $_widget;
	protected $_prepared = false;

	public function __construct(WidgetFramework_WidgetRenderer $renderer, array $widget)
	{
		$this->_renderer = $renderer;
		$this->_widget = $widget;

		self::$_newInstances[] = $this;
	}

	public function prepare()
	{
		if ($this->_prepared === false)
		{
			$this->_prepared = $this->_renderer->extraPrepareTitle($this->_widget);
		}
	}

	public function __toString()
	{
		foreach (self::$_newInstances as $instance)
		{
			$instance->prepare();
		}
		self::$_newInstances = array();

		return strval($this->_prepared);
	}

}
