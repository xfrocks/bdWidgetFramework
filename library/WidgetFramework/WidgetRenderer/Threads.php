<?php

class WidgetFramework_WidgetRenderer_Threads extends WidgetFramework_WidgetRenderer
{
	public function extraPrepareTitle(array $widget)
	{
		if (empty($widget['title']))
		{
			switch ($widget['options']['type'])
			{
				case 'new':
					return new XenForo_Phrase('wf_widget_threads_type_new');
				case 'recent':
					return new XenForo_Phrase('wf_widget_threads_type_recent');
				case 'popular':
					return new XenForo_Phrase('wf_widget_threads_type_popular');
				case 'most_replied':
					return new XenForo_Phrase('wf_widget_threads_type_most_replied');
				case 'most_liked':
					return new XenForo_Phrase('wf_widget_threads_type_most_liked');
				case 'polls':
					return new XenForo_Phrase('wf_widget_threads_type_polls');
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
				'prefixes' => XenForo_Input::ARRAY_SIMPLE,
				'as_guest' => XenForo_Input::UINT,
				'limit' => XenForo_Input::UINT,
				'layout' => XenForo_Input::STRING,
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
		elseif ('layout' == $optionKey)
		{
			if (!in_array($optionValue, array(
				'',
				'sidebar',
				'sidebar_snippet',
				'list',
				'full',
			)))
			{
				throw new XenForo_Exception(new XenForo_Phrase('wf_widget_threads_invalid_layout'), true);
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
				case 'sidebar':
					$layout = 'sidebar';
					break;
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

		// get first post if layout needs it
		// since 2.4
		if ($layoutNeedPost)
		{
			$fetchOptions['join'] |= XenForo_Model_Thread::FETCH_FIRSTPOST;
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
				WidgetFramework_XenForo_Model_Thread::FETCH_OPTIONS_LAST_POST_JOIN => $fetchOptions['join'],
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

		if (!empty($threads))
		{
			/* @var $nodeModel XenForo_Model_Node */
			$nodeModel = $core->getModelFromCache('XenForo_Model_Node');

			/* @var $forumModel XenForo_Model_Forum */
			$forumModel = $core->getModelFromCache('XenForo_Model_Forum');

			/* @var $userModel XenForo_Model_User */
			$userModel = $core->getModelFromCache('XenForo_Model_User');

			$nodePermissions = $nodeModel->getNodePermissionsForPermissionCombination(empty($widget['options']['as_guest']) ? null : 1);

			if ($layoutNeedPost AND !empty($params[WidgetFramework_WidgetRenderer::PARAM_VIEW_OBJECT]))
			{
				$bbCodeFormatter = XenForo_BbCode_Formatter_Base::create('Base', array('view' => $params[WidgetFramework_WidgetRenderer::PARAM_VIEW_OBJECT]));
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
							'thread_id' => $threadRef['thread_id'],
							'attach_count' => $threadRef['attach_count'],
						);
					}
					else
					{
						$postsWithAttachment[$threadRef['first_post_id']] = array(
							'post_id' => $threadRef['first_post_id'],
							'thread_id' => $threadRef['thread_id'],
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
				$threadForumRef = &$threadForums[$thread['node_id']];

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

		return $threads;
	}

}
