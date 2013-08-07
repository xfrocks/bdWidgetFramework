<?php

class WidgetFramework_WidgetRenderer_Threads extends WidgetFramework_WidgetRenderer
{
	protected function _getConfiguration()
	{
		return array(
			'name' => 'Threads',
			'options' => array(
				'type' => XenForo_Input::STRING,
				'cutoff' => XenForo_Input::UINT,
				'forums' => XenForo_Input::ARRAY_SIMPLE,
				'prefixes' => XenForo_Input::ARRAY_SIMPLE,
				'as_guest' => XenForo_Input::UINT,
				'limit' => XenForo_Input::UINT,
				'display' => XenForo_Input::ARRAY_SIMPLE,
			),
			'useCache' => true,
			'useUserCache' => true,
			'cacheSeconds' => 300, // cache for 5 minutes
		);
	}

	protected function _getOptionsTemplate()
	{
		return 'wf_widget_options_threads';
	}

	protected function _renderOptions(XenForo_Template_Abstract $template)
	{
		$params = $template->getParams();

		$forums = $this->_helperPrepareForumsOptionSource(empty($params['options']['forums']) ? array() : $params['options']['forums'], true);

		$prefixes = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_ThreadPrefix')->getPrefixOptions();
		foreach ($prefixes as $prefixGroupId => &$groupPrefixes)
		{
			foreach ($groupPrefixes as &$prefix)
			{
				if (!empty($params['options']['prefixes']) AND in_array($prefix['value'], $params['options']['prefixes']))
				{
					$prefix['selected'] = true;
				}
			}
		}

		$template->setParam('forums', $forums);
		$template->setParam('prefixes', $prefixes);

		return parent::_renderOptions($template);
	}

	protected function _validateOptionValue($optionKey, &$optionValue)
	{
		if ('type' == $optionKey)
		{
			if (!in_array($optionValue, array(
				'new',
				'recent',
				'popular',
				'most_replied',
				'most_liked',
				'polls'
			)))
			{
				throw new XenForo_Exception(new XenForo_Phrase('wf_widget_threads_invalid_type'), true);
			}
		}
		elseif ('limit' == $optionKey)
		{
			if (empty($optionValue))
			{
				$optionValue = 5;
			}
		}
		elseif ('cutoff' == $optionKey)
		{
			if (empty($optionValue))
			{
				$optionValue = 5;
			}
		}

		return parent::_validateOptionValue($optionKey, $optionValue);
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return 'wf_widget_threads';
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		$core = WidgetFramework_Core::getInstance();
		$visitor = XenForo_Visitor::getInstance();

		/* @var $threadModel XenForo_Model_Thread */
		$threadModel = $core->getModelFromCache('XenForo_Model_Thread');

		$forumIds = $this->_helperGetForumIdsFromOption($widget['options']['forums'], $params, empty($widget['options']['as_guest']) ? false : true);

		$conditions = array(
			'node_id' => $forumIds,
			'deleted' => $visitor->isSuperAdmin() AND empty($widget['options']['as_guest']),
			'moderated' => $visitor->isSuperAdmin() AND empty($widget['options']['as_guest']),
		);
		$fetchOptions = array(
			// 'readUserId' => XenForo_Visitor::getUserId(), -- disable this to save some
			// headeach of db join
			// 'includeForumReadDate' => true, -- this's not necessary too
			'limit' => $widget['options']['limit'],
			'join' => XenForo_Model_Thread::FETCH_AVATAR,
		);

		// process prefix
		// since 1.3.4
		if (!empty($widget['options']['prefixes']))
		{
			$conditions['prefix_id'] = $widget['options']['prefixes'];
		}

		if ($widget['options']['type'] == 'new')
		{
			$threads = $threadModel->getThreads($conditions, $fetchOptions + array(
				'order' => 'post_date',
				'orderDirection' => 'desc',
			));
		}
		elseif ($widget['options']['type'] == 'recent')
		{
			$threads = $threadModel->getThreads($conditions, array_merge($fetchOptions, array(
				'order' => 'last_post_date',
				'orderDirection' => 'desc',
				'join' => 0,
				WidgetFramework_XenForo_Model_Thread::FETCH_OPTIONS_LAST_POST_JOIN => XenForo_Model_Thread::FETCH_USER,
			)));
		}
		elseif ($widget['options']['type'] == 'popular')
		{
			$threads = $threadModel->getThreads($conditions + array(WidgetFramework_XenForo_Model_Thread::CONDITIONS_POST_DATE => array(
					'>',
					XenForo_Application::$time - $widget['options']['cutoff'] * 86400
				), ), $fetchOptions + array(
				'order' => 'view_count',
				'orderDirection' => 'desc',
			));
		}
		elseif ($widget['options']['type'] == 'most_replied')
		{
			$threads = $threadModel->getThreads($conditions + array(WidgetFramework_XenForo_Model_Thread::CONDITIONS_POST_DATE => array(
					'>',
					XenForo_Application::$time - $widget['options']['cutoff'] * 86400
				), ), $fetchOptions + array(
				'order' => 'reply_count',
				'orderDirection' => 'desc',
			));

			foreach (array_keys($threads) as $threadId)
			{
				if ($threads[$threadId]['reply_count'] == 0)
				{
					// remove threads with zero reply_count
					unset($threads[$threadId]);
				}
			}
		}
		elseif ($widget['options']['type'] == 'most_liked')
		{
			$threads = $threadModel->getThreads($conditions + array(WidgetFramework_XenForo_Model_Thread::CONDITIONS_POST_DATE => array(
					'>',
					XenForo_Application::$time - $widget['options']['cutoff'] * 86400
				), ), $fetchOptions + array(
				'order' => 'first_post_likes',
				'orderDirection' => 'desc',
			));

			foreach (array_keys($threads) as $threadId)
			{
				if ($threads[$threadId]['first_post_likes'] == 0)
				{
					// remove threads with zero first_post_likes
					unset($threads[$threadId]);
				}
			}
		}
		elseif ($widget['options']['type'] == 'polls')
		{
			$threads = $threadModel->getThreads($conditions + array(WidgetFramework_XenForo_Model_Thread::CONDITIONS_DISCUSSION_TYPE => 'poll', ), $fetchOptions + array(
				'order' => 'post_date',
				'orderDirection' => 'desc',
			));
		}
		else
		{
			$threads = array();
		}

		if (!empty($params['_WidgetFramework_isHook']))
		{
			$layout = 'list';
		}
		else
		{
			$layout = 'sidebar';
		}

		if (!empty($threads))
		{
			/* @var $nodeModel XenForo_Model_Node */
			$nodeModel = $core->getModelFromCache('XenForo_Model_Node');

			/* @var $forumModel XenForo_Model_Forum */
			$forumModel = $core->getModelFromCache('XenForo_Model_Forum');

			/* @var $userModel XenForo_Model_User */
			$userModel = $core->getModelFromCache('XenForo_Model_User');

			$nodePermissions = $nodeModel->getNodePermissionsForPermissionCombination(empty($widget['options']['as_guest']) ? null : 1);

			$threadForumIds = array();
			foreach ($threads as $thread)
			{
				$threadForumIds[] = $thread['node_id'];
			}
			$threadForums = $forumModel->getForumsByIds($threadForumIds);

			foreach ($threads as &$thread)
			{
				$threadPermissions = (isset($nodePermissions[$thread['node_id']]) ? $nodePermissions[$thread['node_id']] : array());
				$threadForum = (isset($threadForums[$thread['node_id']]) ? $threadForums[$thread['node_id']] : array());
				$viewingUser = (empty($widget['options']['as_guest']) ? null : $userModel->getVisitingGuestUser());

				$thread = $threadModel->WidgetFramework_prepareThreadForRendererThreads($thread, $threadForum, $threadPermissions, $viewingUser);
			}
		}

		$renderTemplateObject->setParam('threads', $threads);
		$renderTemplateObject->setParam('layout', $layout);

		return $renderTemplateObject->render();
	}

	public function useUserCache(array $widget)
	{
		if (!empty($widget['options']['as_guest']))
		{
			// using guest permission
			// there is no reason to use the user cache
			return false;
		}

		return parent::useUserCache($widget);
	}

	protected function _getCacheId(array $widget, $positionCode, array $params, array $suffix = array())
	{
		if ($this->_helperDetectSpecialForums($widget['options']['forums']))
		{
			// we have to use special cache id when special forum ids are used
			if (isset($params['forum']))
			{
				$suffix[] = 'f' . $params['forum']['node_id'];
			}
		}

		return parent::_getCacheId($widget, $positionCode, $params, $suffix);
	}

}
