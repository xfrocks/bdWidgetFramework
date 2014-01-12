<?php

class WidgetFramework_XenForo_BbCode_Formatter_Base extends XFCP_WidgetFramework_XenForo_BbCode_Formatter_Base
{
	public function getTags()
	{
		$tags = parent::getTags();

		$tags['prbreak'] = array(
			'hasOption' => false,
			'callback' => array(
				$this,
				'renderPrbreak'
			),
		);

		return $tags;
	}

	public function renderPrbreak(array $tag, array $rendererStates)
	{
		$text = trim($this->renderSubTree($tag['children'], $rendererStates));

		return $this->_wrapInHtml('<span class="prbreak" style="display: none">', '</span>', htmlentities($text));
	}

}
