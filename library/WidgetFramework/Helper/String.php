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
	protected $_strings;

	public function __construct($glue, array $strings)
	{
		$this->_glue = $glue;
		$this->_strings = $strings;
	}
	
	public function __toString()
	{
		return implode($this->_glue, $this->_strings);
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
