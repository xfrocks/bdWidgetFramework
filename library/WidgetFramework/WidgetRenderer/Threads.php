<?php

class WidgetFramework_WidgetRenderer_Threads extends WidgetFramework_WidgetRenderer
{
	public function useCache(array $widget)
	{
		if (!empty($widget['options']['is_new']))
		{
			return false;
		}

		return parent::useCache($widget);
	}

	public function extraPrepareTitle(array $widget)
	{
		if (empty($widget['title']))
		{
			if (empty($widget['options']['type']))
			{
				$widget['options']['type'] = 'new';
			}

			switch ($widget['options']['type'])
			{
				case 'recent':
				case 'recent_first_poster':
					return new XenForo_Phrase('wf_widget_threads_type_recent');
				case 'latest_replies':
					return new XenForo_Phrase('wf_widget_threads_type_latest_replies');
				case 'popular':
					return new XenForo_Phrase('wf_widget_threads_type_popular');
				case 'most_replied':
					return new XenForo_Phrase('wf_widget_threads_type_most_replied');
				case 'most_liked':
					return new XenForo_Phrase('wf_widget_threads_type_most_liked');
				case 'polls':
					return new XenForo_Phrase('wf_widget_threads_type_polls');
				case 'new':
				default:
					return new XenForo_Phrase('wf_widget_threads_type_new');
			}
		}

		return parent::extraPrepareTitle($widget);
	}

	protected function _getConfiguration()
	{
		return array(
			'name' => 'Threads',
			'options' => array(
				'type' => XenForo_Input::STRING,
				'cutoff' => XenForo_Input::UINT,
				'forums' => XenForo_Input::ARRAY_SIMPLE,
				'sticky' => XenForo_Input::STRING,
				'prefixes' => XenForo_Input::ARRAY_SIMPLE,
				'open_only' => XenForo_Input::UINT,
				'as_guest' => XenForo_Input::UINT,
				'is_new' => XenForo_Input::UINT,
				'limit' => XenForo_Input::UINT,
				'layout' => XenForo_Input::STRING,
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
		switch ($optionKey)
		{
			case 'limit':
			case 'cutoff':
				if (empty($optionValue))
				{
					$optionValue = 5;
				}
				break;
			case 'type':
				if (empty($optionValue))
				{
					$optionValue = 'new';
				}
				break;
		}

		return parent::_validateOptionValue($optionKey, $optionValue);
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return 'wf_widget_threads';
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		if (empty($widget['options']['limit']))
		{
			$widget['options']['limit'] = 5;
		}
		if (empty($widget['options']['cutoff']))
		{
			$widget['options']['cutoff'] = 5;
		}
		if (empty($widget['options']['type']))
		{
			$widget['options']['type'] = 'new';
		}

		$layout = 'sidebar';
		$layoutNeedPost = false;
		if (empty($widget['options']['layout']))
		{
			if (!empty($params[WidgetFramework_WidgetRenderer::PARAM_IS_HOOK]))
			{
				$layout = 'list';
			}
			else
			{
				$layout = 'sidebar';
			}
		}
		else
		{
			switch ($widget['options']['layout'])
			{
				case 'sidebar_snippet':
					$layout = 'sidebar';
					$layoutNeedPost = true;
					break;
				case 'list':
					$layout = 'list';
					break;
				case 'full':
					$layout = 'full';
					$layoutNeedPost = true;
					break;
				case 'sidebar':
				default:
					$layout = 'sidebar';
					break;
			}
		}
		$renderTemplateObject->setParam('layout', $layout);
		$renderTemplateObject->setParam('layoutNeedPost', $layoutNeedPost);

		$threads = $this->_getThreads($widget, $positionCode, $params, $renderTemplateObject);
		$renderTemplateObject->setParam('threads', $threads);

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

	public function useWrapper(array $widget)
	{
		if (!empty($widget['options']['layout']) AND $widget['options']['layout'] === 'full')
		{
			// using full layout, do not use wrapper
			return false;
		}

		return parent::useWrapper($widget);
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

	protected function _getThreads($widget, $positionCode, $params, $renderTemplateObject)
	{
		$core = WidgetFramework_Core::getInstance();
		$visitor = XenForo_Visitor::getInstance();
		$layoutNeedPost = $renderTemplateObject->getParam('layoutNeedPost');

		/* @var $threadModel XenForo_Model_Thread */
		$threadModel = $core->getModelFromCache('XenForo_Model_Thread');

		$forumIds = $this->_helperGetForumIdsFromOption($widget['options']['forums'], $params, empty($widget['options']['as_guest']) ? false : true);
		if (empty($forumIds))
		{
			// no forum ids?! Save the effort and return asap
			// btw, because XenForo_Model_Thread::prepareThreadConditions ignores empty
			// node_id in $conditions, continuing may result in incorrect output (could be a
			// serious bug)
			return array();
		}

		$conditions = array(
			'node_id' => $forumIds,
			'deleted' => false,
			'moderated' => false,
		);

		// note: `limit` is set to 3 times of configured limit to account for the threads
		// that get hidden because of deep permissions like viewOthers or viewContent
		$fetchOptions = array(
			'limit' => $widget['options']['limit'] * 3,
			'join' => XenForo_Model_Thread::FETCH_AVATAR,
		);

		// process sticky
		// since 2.4.7
		if (isset($widget['options']['sticky']) AND is_numeric($widget['options']['sticky']))
		{
			$conditions['sticky'] = intval($widget['options']['sticky']);
		}

		// process prefix
		// since 1.3.4
		if (!empty($widget['options']['prefixes']))
		{
			$conditions['prefix_id'] = $widget['options']['prefixes'];
		}

		// process discussion_open
		// since 2.5
		if (!empty($widget['options']['open_only']))
		{
			$conditions['discussion_open'] = true;
		}

		// get first post if layout needs it
		// since 2.4
		if ($layoutNeedPost)
		{
			$fetchOptions['join'] |= XenForo_Model_Thread::FETCH_FIRSTPOST;
		}

		// include is_new if option is turned on
		// since 2.5.1
		if (!empty($widget['options']['is_new']))
		{
			$fetchOptions['readUserId'] = XenForo_Visitor::getUserId();
		}

		switch ($widget['options']['type'])
		{
			case 'recent':
				$threads = $threadModel->getThreads($conditions, array_merge($fetchOptions, array(
					'order' => 'last_post_date',
					'orderDirection' => 'desc',
					'join' => 0,
					WidgetFramework_XenForo_Model_Thread::FETCH_OPTIONS_LAST_POST_JOIN => $fetchOptions['join'],
				)));
				break;
			case 'recent_first_poster':
				$threads = $threadModel->getThreads($conditions, array_merge($fetchOptions, array(
					'order' => 'last_post_date',
					'orderDirection' => 'desc',
				)));
				break;
			case 'latest_replies':
				$threads = $threadModel->getThreads(array_merge($conditions, array('reply_count' => array(
						'>',
						0
					), )), array_merge($fetchOptions, array(
					'order' => 'last_post_date',
					'orderDirection' => 'desc',
					'join' => 0,
					WidgetFramework_XenForo_Model_Thread::FETCH_OPTIONS_LAST_POST_JOIN => $fetchOptions['join'],
				)));
				break;
			case 'popular':
				$threads = $threadModel->getThreads(array_merge($conditions, array(WidgetFramework_XenForo_Model_Thread::CONDITIONS_POST_DATE => array(
						'>',
						XenForo_Application::$time - $widget['options']['cutoff'] * 86400
					))), array_merge($fetchOptions, array(
					'order' => 'view_count',
					'orderDirection' => 'desc',
				)));
				break;
			case 'most_replied':
				$threads = $threadModel->getThreads(array_merge($conditions, array(WidgetFramework_XenForo_Model_Thread::CONDITIONS_POST_DATE => array(
						'>',
						XenForo_Application::$time - $widget['options']['cutoff'] * 86400
					))), array_merge($fetchOptions, array(
					'order' => 'reply_count',
					'orderDirection' => 'desc',
				)));

				foreach (array_keys($threads) as $threadId)
				{
					if ($threads[$threadId]['reply_count'] == 0)
					{
						// remove threads with zero reply_count
						unset($threads[$threadId]);
					}
				}
				break;
			case 'most_liked':
				$threads = $threadModel->getThreads(array_merge($conditions, array(WidgetFramework_XenForo_Model_Thread::CONDITIONS_POST_DATE => array(
						'>',
						XenForo_Application::$time - $widget['options']['cutoff'] * 86400
					))), array_merge($fetchOptions, array(
					'order' => 'first_post_likes',
					'orderDirection' => 'desc',
				)));

				foreach (array_keys($threads) as $threadId)
				{
					if ($threads[$threadId]['first_post_likes'] == 0)
					{
						// remove threads with zero first_post_likes
						unset($threads[$threadId]);
					}
				}
				break;
			case 'polls':
				$threads = $threadModel->getThreads(array_merge($conditions, array(WidgetFramework_XenForo_Model_Thread::CONDITIONS_DISCUSSION_TYPE => 'poll')), array_merge($fetchOptions, array(
					'order' => 'post_date',
					'orderDirection' => 'desc',
				)));
				break;
			case 'new':
			default:
				$threads = $threadModel->getThreads($conditions, array_merge($fetchOptions, array(
					'order' => 'post_date',
					'orderDirection' => 'desc',
				)));
				break;
		}

		if (!empty($threads))
		{
			$this->_prepareThreads($widget, $positionCode, $params, $renderTemplateObject, $threads);
		}

		if (count($threads) > $widget['options']['limit'])
		{
			// too many threads (because we fetched 3 times as needed)
			$threads = array_slice($threads, 0, $widget['options']['limit'], true);
		}

		return $threads;
	}

	protected function _prepareThreads($widget, $positionCode, $params, $renderTemplateObject, array &$threads)
	{
		$core = WidgetFramework_Core::getInstance();
		$visitor = XenForo_Visitor::getInstance();
		$layoutNeedPost = $renderTemplateObject->getParam('layoutNeedPost');

		/* @var $threadModel XenForo_Model_Thread */
		$threadModel = $core->getModelFromCache('XenForo_Model_Thread');

		/* @var $nodeModel XenForo_Model_Node */
		$nodeModel = $core->getModelFromCache('XenForo_Model_Node');

		/* @var $forumModel XenForo_Model_Forum */
		$forumModel = $core->getModelFromCache('XenForo_Model_Forum');

		/* @var $userModel XenForo_Model_User */
		$userModel = $core->getModelFromCache('XenForo_Model_User');

		$nodePermissions = $nodeModel->getNodePermissionsForPermissionCombination(empty($widget['options']['as_guest']) ? null : 1);

		$viewObj = self::getViewObject($params, $renderTemplateObject);
		if ($layoutNeedPost AND !empty($viewObj))
		{
			$bbCodeFormatter = XenForo_BbCode_Formatter_Base::create('Base', array('view' => $viewObj));
			if (XenForo_Application::$versionId < 1020000)
			{
				// XenForo 1.1.x
				$bbCodeParser = new XenForo_BbCode_Parser($bbCodeFormatter);
			}
			else
			{
				// XenForo 1.2.x
				$bbCodeParser = XenForo_BbCode_Parser::create($bbCodeFormatter);
			}
			$bbCodeOptions = array(
				'states' => array(),
				'contentType' => 'post',
				'contentIdKey' => 'post_id'
			);

			$postsWithAttachment = array();
			foreach (array_keys($threads) as $threadId)
			{
				$threadRef = &$threads[$threadId];

				if (empty($threadRef['attach_count']))
				{
					continue;
				}

				if (!empty($threadRef['fetched_last_post']))
				{
					$postsWithAttachment[$threadRef['last_post_id']] = array(
						'post_id' => $threadRef['last_post_id'],
						'thread_id' => $threadId,
						'attach_count' => $threadRef['attach_count'],
					);
				}
				else
				{
					$postsWithAttachment[$threadRef['first_post_id']] = array(
						'post_id' => $threadRef['first_post_id'],
						'thread_id' => $threadId,
						'attach_count' => $threadRef['attach_count'],
					);
				}
			}
			if (!empty($postsWithAttachment))
			{
				$postsWithAttachment = $core->getModelFromCache('XenForo_Model_Post')->getAndMergeAttachmentsIntoPosts($postsWithAttachment);
				foreach ($postsWithAttachment as $postWithAttachment)
				{
					if (empty($postWithAttachment['attachments']))
					{
						continue;
					}

					if (empty($threads[$postWithAttachment['thread_id']]))
					{
						continue;
					}
					$threadRef = &$threads[$postWithAttachment['thread_id']];

					$threadRef['attachments'] = $postWithAttachment['attachments'];
				}
			}
		}

		$threadForumIds = array();
		foreach ($threads as $thread)
		{
			$threadForumIds[] = $thread['node_id'];
		}
		$threadForums = $forumModel->getForumsByIds($threadForumIds);

		$viewingUser = (empty($widget['options']['as_guest']) ? null : $userModel->getVisitingGuestUser());

		foreach (array_keys($threads) as $threadId)
		{
			$threadRef = &$threads[$threadId];

			if (empty($nodePermissions[$threadRef['node_id']]))
			{
				unset($threads[$threadId]);
				continue;
			}
			$threadPermissionsRef = &$nodePermissions[$threadRef['node_id']];

			if (empty($threadForums[$threadRef['node_id']]))
			{
				unset($threads[$threadId]);
				continue;
			}
			$threadForumRef = &$threadForums[$threadRef['node_id']];

			if ($threadModel->isRedirect($threadRef))
			{
				unset($threads[$threadId]);
				continue;
			}

			if (!$threadModel->canViewThread($threadRef, $threadForumRef, $null, $threadPermissionsRef, $viewingUser))
			{
				unset($threads[$threadId]);
				continue;
			}

			if (!empty($bbCodeParser) AND !empty($bbCodeOptions))
			{
				$threadBbCodeOptions = $bbCodeOptions;
				$threadBbCodeOptions['states']['viewAttachments'] = $threadModel->canViewAttachmentsInThread($threadRef, $threadForumRef, $null, $threadPermissionsRef, $viewingUser);
				$threadRef['messageHtml'] = XenForo_ViewPublic_Helper_Message::getBbCodeWrapper($threadRef, $bbCodeParser, $threadBbCodeOptions);
			}

			$threadRef = $threadModel->WidgetFramework_prepareThreadForRendererThreads($threadRef, $threadForumRef, $threadPermissionsRef, $viewingUser);
		}
	}

}
