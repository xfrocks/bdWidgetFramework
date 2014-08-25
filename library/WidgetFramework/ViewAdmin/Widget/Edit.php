<?php

class WidgetFramework_ViewAdmin_Widget_Edit extends XenForo_ViewAdmin_Base
{
	public function prepareParams()
	{
		$widget = &$this->_params['widget'];

		if (!empty($this->_params['conditionalParams']))
		{
			$conditionalParams = @json_decode($this->_params['conditionalParams'], true);
			$keyValuePairs = array();
			if (!empty($conditionalParams))
			{
				$this->_prepareKeyValuePairs($conditionalParams, $keyValuePairs);
			}

			$explains = array();
			foreach ($keyValuePairs as $pairKey => $pairValue)
			{
				$explains[] = sprintf('<a href="#" title="{%1$s} == \'%2$s\'" class="Tooltip">%1$s</a>', $pairKey, htmlspecialchars($pairValue));
			}

			$this->_params['conditionalParamsExplain'] = implode(' ', $explains);

			if (!empty($keyValuePairs['$contentTemplate']))
			{
				if (empty($widget['widget_id']) AND empty($widget['options']['expression']) AND empty($widget['options']['conditional']))
				{
					$widget['options']['conditional']['raw'] = sprintf('{$contentTemplate} == \'%s\'', $keyValuePairs['$contentTemplate']);
				}
			}
		}

		return parent::prepareParams();
	}

	public function renderHtml()
	{
		$widget = &$this->_params['widget'];

		if (!empty($widget['class']))
		{
			$renderer = WidgetFramework_Core::getRenderer($widget['class'], false);
		}
		else
		{
			$renderer = WidgetFramework_Core::getRenderer('WidgetFramework_WidgetRenderer_None', false);
		}

		if ($renderer)
		{
			$renderer->renderOptions($this->_renderer, $this->_params);
		}
	}

	protected function _prepareKeyValuePairs(array $array, array &$keyValuePairs, $prefix = '')
	{
		foreach ($array as $key => $value)
		{
			if (is_array($value))
			{
				$this->_prepareKeyValuePairs($value, $keyValuePairs, sprintf('%s.%s', $prefix, $key));
			}
			else
			{
				$variableName = sprintf('%s.%s', $prefix, $key);
				$variableName = substr($variableName, 1);
				$variableName = '$' . $variableName;

				$keyValuePairs[$variableName] = $value;
			}
		}
	}

}
