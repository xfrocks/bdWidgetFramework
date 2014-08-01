<?php

class WidgetFramework_Helper_Conditional
{
	public static function parse($raw)
	{
		$compiler = new XenForo_Template_Compiler(sprintf('<xen:if is="%s">%s</xen:if>', $raw, md5($raw)));
		$parsed = $compiler->lexAndParse();

		$compiler->setFollowExternal(false);
		$parsed = $compiler->compileParsed($parsed, __CLASS__, 0, 0);

		return $parsed;
	}

	public static function test($raw, $parsed, array $params)
	{
		extract($params);
		eval($parsed);

		return $__output === md5($raw);
	}

}
