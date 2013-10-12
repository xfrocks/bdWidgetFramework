<?php

class WidgetFramework_WidgetRenderer_Poll extends WidgetFramework_WidgetRenderer
{
	public function extraPrepareTitle(array $widget)
	{
		if (empty($widget['title']))
		{
			return new XenForo_Phrase('wf_thread_with_poll');
		}

		return parent::extraPrepareTitle($widget);
	}

	protected function _getConfiguration()
	{
		return array(
			'name' => 'Thread with Poll',
			'options' => array('thread_id' => XenForo_Input::STRING),
			'useWrapper' => false,
		);
	}

	protected function _getOptionsTemplate()
	{
		return 'wf_widget_options_poll';
	}

	protected function _validateOptionValue($optionKey, &$optionValue)
	{
		if ('thread_id' == $optionKey AND !empty($optionValue))
		{
			$optionValue = strtolower($optionValue);

			if ($optionValue === 'random')
			{
				// random mode
			}
			else
			{
				$threadModel = XenForo_Model::create('XenForo_Model_Thread');
				$thread = $threadModel->getThreadById($optionValue);
				if (empty($thread))
				{
					throw new XenForo_Exception(new XenForo_Phrase('requested_thread_not_found'), true);
				}
				elseif (empty($thread['discussion_type']) OR 'poll' != $thread['discussion_type'])
				{
					throw new XenForo_Exception(new XenForo_Phrase('wf_requested_thread_does_not_have_poll'), true);
				}
			}
		}

		return parent::_validateOptionValue($optionKey, $optionValue);
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return 'wf_widget_poll';
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		$core = WidgetFramework_Core::getInstance();
		$threadModel = $core->getModelFromCache('XenForo_Model_Thread');
		$pollModel = $core->getModelFromCache('XenForo_Model_Poll');

		$thread = array();
		$poll = array();

		if (empty($widget['options']['thread_id']) OR $widget['options']['thread_id'] === 'random')
		{
			if (empty($GLOBALS['WidgetFramework_viewableNodeList']))
			{
				$nodeModel = $core->getModelFromCache('XenForo_Model_Node');
				$GLOBALS['WidgetFramework_viewableNodeList'] = $nodeModel->getViewableNodeList();
			}
			$forumIds = array_keys($GLOBALS['WidgetFramework_viewableNodeList']);

			$thread = $threadModel->getThreads(array(
				'node_id' => $forumIds,
				WidgetFramework_XenForo_Model_Thread::CONDITIONS_DISCUSSION_TYPE => 'poll',
			), array(
				'order' => ($widget['options']['thread_id'] === 'random' ? WidgetFramework_XenForo_Model_Thread::FETCH_OPTIONS_ORDER_RANDOM : 'post_date'),
				'orderDirection' => 'desc',
				WidgetFramework_XenForo_Model_Thread::FETCH_OPTIONS_POLL_JOIN => true,
				WidgetFramework_XenForo_Model_Thread::FETCH_OPTIONS_FORUM_FULL_JOIN => true,
				'limit' => 1,
			));

			if (!empty($thread))
			{
				$thread = array_shift($thread);
				$poll = $pollModel->preparePoll($thread, $threadModel->canVoteOnPoll($thread, $thread));
			}
		}
		else
		{
			$thread = $threadModel->getThreadById($widget['options']['thread_id'], array(
				WidgetFramework_XenForo_Model_Thread::FETCH_OPTIONS_POLL_JOIN => true,
				WidgetFramework_XenForo_Model_Thread::FETCH_OPTIONS_FORUM_FULL_JOIN => true,
			));

			if ($thread['discussion_type'] != 'poll')
			{
				$thread = array();
			}
		}
		if (!empty($thread))
			$poll = $pollModel->preparePoll($thread, $threadModel->canVoteOnPoll($thread, $thread));

		$renderTemplateObject->setParam('thread', $thread);
		$renderTemplateObject->setParam('poll', $poll);

		return $renderTemplateObject->render();
	}

}
