<?php

class WidgetFramework_WidgetRenderer_FeedReader extends WidgetFramework_WidgetRenderer
{
	public function extraPrepareTitle(array $widget)
	{
		if (empty($widget['title']))
		{
			return new XenForo_Phrase('wf_feed_reader');
		}

		return parent::extraPrepareTitle($widget);
	}

	protected function _getConfiguration()
	{
		return array(
			'name' => 'Feed Reader',
			'options' => array(
				'url' => XenForo_Input::STRING,
				'limit' => XenForo_Input::UINT,
				'displayMode' => XenForo_Input::STRING,
			),
			'useCache' => true,
			'cacheSeconds' => 3600, // cache for an hour
		);
	}

	protected function _getOptionsTemplate()
	{
		return 'wf_widget_options_feed_reader';
	}

	protected function _validateOptionValue($optionKey, &$optionValue)
	{
		switch ($optionKey)
		{
			case 'limit':
				if (empty($optionValue))
				{
					$optionValue = 5;
				}
				break;
		}

		return parent::_validateOptionValue($optionKey, $optionValue);
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return 'wf_widget_feed_reader';
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		if (empty($widget['options']['url']))
		{
			return '';
		}
		if (empty($widget['options']['limit']))
		{
			$widget['options']['limit'] = 5;
		}

		$core = WidgetFramework_Core::getInstance();
		$feedModel = $core->getModelFromCache('XenForo_Model_Feed');

		$feedUrl = $widget['options']['url'];
		$feedData = $feedModel->getFeedData($feedUrl);
		$feedConfig = array();
		$feedConfig['baseUrl'] = $feedModel->getFeedBaseUrl($feedUrl);

		$entries = array();
		if (!empty($feedData['entries']))
		{
			foreach ($feedData['entries'] as $entryRaw)
			{
				$entry = array();
				$entryRaw = $feedModel->prepareFeedEntry($entryRaw, $feedData, $feedConfig);

				$entry['link'] = $entryRaw['link'];
				$entry['author'] = $entryRaw['author'];
				$entry['title'] = $entryRaw['title'];
				$entry['content'] = $entryRaw['content'];

				if (class_exists('bdImage_Integration'))
				{
					// out source the image processing + handling to [bd] Image
					$entry['bdImage_image'] = bdImage_Integration::getBbCodeImage($entryRaw['content']);
				}
				else
				{
					// TODO: support other method?
				}

				$entries[] = $entry;

				if (count($entries) >= $widget['options']['limit'])
				{
					// we have got enough entries, stop here
					break;
				}
			}
		}

		$renderTemplateObject->setParam('entries', $entries);

		return $renderTemplateObject->render();
	}

}
