<?php

class WidgetFramework_WidgetRenderer_Empty extends WidgetFramework_WidgetRenderer
{
	const NO_VISITOR_PANEL_MARKUP = '<!-- no visitor panel please -->';
	const NO_VISITOR_PANEL_FLAG = 'WidgetFramework_WidgetRenderer_Empty.noVisitorPanel';
	const RENDERED = 'RENDERED';

	protected function _getConfiguration()
	{
		return array(
			'name' => ' Clear Contents',
			'options' => array('noVisitorPanel' => XenForo_Input::UINT),
			'useWrapper' => false,
		);
	}

	protected function _getOptionsTemplate()
	{
		return 'wf_widget_options_empty';
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return false;
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		if (WidgetFramework_Option::get('layoutEditorEnabled'))
		{
			return new XenForo_Phrase('wf_widget_empty_layout_editor_explain');
		}

		return self::RENDERED;
	}

	public function render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $template, &$output)
	{
		$rendered = parent::render($widget, $positionCode, $params, $template, $output);

		if ($rendered === self::RENDERED)
		{
			// only work if the normal rendering routine runs throughly
			// this is done to make sure expression is tested properly
			// since 1.2.1
			$rendered = $output = '';

			if (!empty($widget['options']['noVisitorPanel']))
			{
				define(self::NO_VISITOR_PANEL_FLAG, true);
				$output .= self::NO_VISITOR_PANEL_MARKUP;
			}
		}

		return $rendered;
	}

}
