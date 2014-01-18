<?php

class WidgetFramework_XenForo_BbCode_Formatter_HtmlEmail extends XFCP_WidgetFramework_XenForo_BbCode_Formatter_HtmlEmail
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
		return '';
	}

}
