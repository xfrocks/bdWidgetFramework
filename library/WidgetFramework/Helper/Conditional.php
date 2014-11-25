<?php

class WidgetFramework_Helper_Conditional
{
    public static function parse($raw)
    {
        $compiler = new XenForo_Template_Compiler(sprintf('<xen:if is="%s">%s</xen:if>', $raw, md5($raw)));
        $compiler->addFunctionHandler('helper', new WidgetFramework_Helper_Conditional_Function_Helper());
        $parsed = $compiler->lexAndParse();

        $compiler->setFollowExternal(false);
        $parsed = $compiler->compileParsed($parsed, __CLASS__, 0, 0);

        return $parsed;
    }

    public static function test($raw, $parsed, array $params)
    {
        extract($params);
        eval($parsed);

        /** @noinspection PhpUndefinedVariableInspection */
        return $__output === md5($raw);
    }

}

class WidgetFramework_Helper_Conditional_Function_Helper extends XenForo_Template_Compiler_Function_Helper
{
    public function compile(XenForo_Template_Compiler $compiler, $function, array $arguments, array $options)
    {
        $result = parent::compile($compiler, $function, $arguments, $options);

        $result = str_replace('XenForo_Template_Helper_Core', 'WidgetFramework_Template_Helper_Conditional',
            $result, $count);

        if ($count !== 1) {
            throw new XenForo_Exception('Unable to inject conditional helper methods.');
        }

        return $result;
    }
}
