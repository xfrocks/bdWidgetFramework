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
			'options' => array(
				'thread_id' => XenForo_Input::STRING,
				'open_only' => XenForo_Input::UINT,
			),
			'useWrapper' => false,
		);
	}

	protected function _getOptionsTemplate()
	{
		return 'wf_widget_options_poll';
	}

	protected function _validateOptionValue($optionKey, &$optionValue)
	{
		switch ($optionKey)
		{
			case 'thread_id':
				if (!empty($optionValue))
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
				break;
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
		$nodeModel = $core->getModelFromCache('XenForo_Model_Node');

		$thread = array();
		$poll = array();

		if (empty($widget['options']['thread_id']) OR $widget['options']['thread_id'] === 'random')
		{
			$forumIds = array_keys($this->_helperGetViewableNodeList(false));

			$conditions = array(
				'node_id' => $forumIds,
				WidgetFramework_XenForo_Model_Thread::CONDITIONS_DISCUSSION_TYPE => 'poll',
				'deleted' => false,
				'moderated' => false,
			);

			if (!empty($widget['options']['open_only']))
			{
				$conditions['discussion_open'] = true;
			}

			$fetchOptions = array(
				'order' => ($widget['options']['thread_id'] === 'random' ? WidgetFramework_XenForo_Model_Thread::FETCH_OPTIONS_ORDER_RANDOM : 'post_date'),
				'orderDirection' => 'desc',
				WidgetFramework_XenForo_Model_Thread::FETCH_OPTIONS_POLL_JOIN => true,
				WidgetFramework_XenForo_Model_Thread::FETCH_OPTIONS_FORUM_FULL_JOIN => true,
				'limit' => 3,
			);

			$threads = $threadModel->getThreads($conditions, $fetchOptions);

			if (!empty($threads))
			{
				$thread = array();
				$nodePermissions = $nodeModel->getNodePermissionsForPermissionCombination();

				foreach ($threads as $_thread)
				{
					if ($threadModel->canViewThread($_thread, $_thread, $null, $nodePermissions[$_thread['node_id']]))
					{
						$thread = $_thread;
						break;
					}
				}
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

			if (!empty($widget['options']['open_only']))
			{
				if (empty($thread['discussion_open']))
				{
					$thread = array();
				}
			}
		}

		if (!empty($thread))
		{
			if (XenForo_Application::$versionId > 1040000)
			{
				// XenForo 1.4.0+ has some major changes regarding polls
				$poll = $pollModel->getPollByContent('thread', $thread['thread_id']);
				if (!empty($poll))
				{
					$poll = $pollModel->preparePoll($poll, $threadModel->canVoteOnPoll($poll, $thread, $thread));
				}
			}
			else
			{
				$poll = $pollModel->preparePoll($thread, $threadModel->canVoteOnPoll($thread, $thread));
			}
		}

		$renderTemplateObject->setParam('thread', $thread);
		$renderTemplateObject->setParam('poll', $poll);

		return $renderTemplateObject->render();
	}

}
