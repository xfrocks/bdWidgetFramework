<?php

class WidgetFramework_WidgetRenderer_Threads extends WidgetFramework_WidgetRenderer
{
    public function useCache(array $widget)
    {
        if (!empty($widget['options']['is_new'])) {
            return false;
        }

        return parent::useCache($widget);
    }

    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])) {
            if (!empty($widget['options']['tags'])) {
                $widget['options']['type'] = '_tags';
            }

            if (empty($widget['options']['type'])) {
                $widget['options']['type'] = 'new';
            }

            switch ($widget['options']['type']) {
                case 'recent':
                case 'recent_first_poster':
                    if (XenForo_Application::$versionId > 1050000) {
                        return new XenForo_Phrase('new_posts');
                    }

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
                case '_tags':
                    return new XenForo_Phrase('wf_widget_threads_type__tags');
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
                'tags' => XenForo_Input::ARRAY_SIMPLE,
                'open_only' => XenForo_Input::UINT,
                'as_guest' => XenForo_Input::UINT,
                'is_new' => XenForo_Input::UINT,
                'order_reverted' => XenForo_Input::UINT,
                'limit' => XenForo_Input::UINT,
                'layout' => XenForo_Input::STRING,
            ),
            'useCache' => true,
            'useUserCache' => true,
            'cacheSeconds' => 300, // cache for 5 minutes
            'canAjaxLoad' => true,
        );
    }

    protected function _getOptionsTemplate()
    {
        return 'wf_widget_options_threads';
    }

    protected function _renderOptions(XenForo_Template_Abstract $template)
    {
        $params = $template->getParams();

        $forums = empty($params['options']['forums']) ? array() : $params['options']['forums'];
        $forums = $this->_helperPrepareForumsOptionSource($forums, true);
        $template->setParam('forums', $forums);

        /** @var XenForo_Model_ThreadPrefix $threadPrefixModel */
        $threadPrefixModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_ThreadPrefix');
        $prefixes = $threadPrefixModel->getPrefixOptions();
        foreach ($prefixes as $prefixGroupId => &$groupPrefixes) {
            foreach ($groupPrefixes as &$prefix) {
                if (!empty($params['options']['prefixes'])
                    && in_array($prefix['value'], $params['options']['prefixes'])
                ) {
                    $prefix['selected'] = true;
                }
            }
        }
        $template->setParam('prefixes', $prefixes);

        $contentTaggingFound = WidgetFramework_Core::contentTaggingFound();
        if ($contentTaggingFound) {
            $template->setParam('contentTaggingFound', $contentTaggingFound);

            $tags = array();
            if (!empty($params['options']['tags'])) {
                foreach ($params['options']['tags'] as $tag) {
                    if (!empty($tag['tag_id'])) {
                        $tags[] = $tag['tag'];
                    }
                }
            }
            $template->setParam('tags', implode(', ', $tags));
        }

        return parent::_renderOptions($template);
    }

    protected function _validateOptionValue($optionKey, &$optionValue)
    {
        switch ($optionKey) {
            case 'limit':
            case 'cutoff':
                if (empty($optionValue)) {
                    $optionValue = 5;
                }
                break;
            case 'type':
                if (empty($optionValue)) {
                    $optionValue = 'new';
                }
                break;
            case 'tags':
                if (WidgetFramework_Core::contentTaggingFound()
                    && !empty($optionValue[0])
                ) {
                    /** @var XenForo_Model_Tag $tagModel */
                    $tagModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Tag');
                    $tags = $tagModel->splitTags($optionValue[0]);
                    $optionValue = $tagModel->getTags($tags);
                } else {
                    $optionValue = null;
                }
                break;
        }

        return parent::_validateOptionValue($optionKey, $optionValue);
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'wf_widget_threads';
    }

    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        if (empty($widget['options']['limit'])) {
            $widget['options']['limit'] = 5;
        }
        if (empty($widget['options']['cutoff'])) {
            $widget['options']['cutoff'] = 5;
        }
        if (empty($widget['options']['type'])) {
            $widget['options']['type'] = 'new';
        }

        $layoutNeedPost = false;
        if (empty($widget['options']['layout'])) {
            if (!empty($params[WidgetFramework_Core::PARAM_IS_HOOK])) {
                $layout = 'list';
            } else {
                $layout = 'sidebar';
            }
        } else {
            switch ($widget['options']['layout']) {
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

    protected function _getExtraDataLink(array $widget)
    {
        if (XenForo_Application::$versionId > 1050000
            && $widget['options']['type'] === 'recent'
        ) {
            return XenForo_Link::buildPublicLink('find-new/posts');
        }

        return parent::_getExtraDataLink($widget);
    }


    public function useUserCache(array $widget)
    {
        if (!empty($widget['options']['as_guest'])) {
            // using guest permission
            // there is no reason to use the user cache
            return false;
        }

        return parent::useUserCache($widget);
    }

    public function useWrapper(array $widget)
    {
        if (!empty($widget['options']['layout'])
            && $widget['options']['layout'] === 'full'
        ) {
            // using full layout, do not use wrapper
            return false;
        }

        return parent::useWrapper($widget);
    }

    protected function _getCacheId(array $widget, $positionCode, array $params, array $suffix = array())
    {
        if (isset($widget['_ajaxLoadParams'])) {
            if (!empty($widget['_ajaxLoadParams']['forumIds'])) {
                $suffix[] = 'ajax_f' . implode('', $widget['_ajaxLoadParams']['forumIds']);
            }
        }

        if (!empty($widget['options']['forums'])
            && $this->_helperDetectSpecialForums($widget['options']['forums'])
        ) {
            $forumId = $this->_helperGetForumIdForCache($widget['options']['forums'], $params,
                !empty($widget['options']['as_guest']));
            if (!empty($forumId)) {
                $suffix[] = 'f' . $forumId;
            }
        }

        return parent::_getCacheId($widget, $positionCode, $params, $suffix);
    }

    /**
     * @param array $widget
     * @param string $positionCode
     * @param array $params
     * @param XenForo_Template_Abstract $renderTemplateObject
     * @return array $threads
     */
    protected function _getThreads($widget, $positionCode, $params, $renderTemplateObject)
    {
        $core = WidgetFramework_Core::getInstance();
        $layoutNeedPost = $renderTemplateObject->getParam('layoutNeedPost');

        if ($positionCode === 'forum_list'
            && XenForo_Application::$versionId > 1050000
            && isset($params['threads'])
            && !$layoutNeedPost
            && $widget['options']['type'] === 'recent'
            && $widget['options']['limit'] == XenForo_Application::getOptions()->get('forumListNewPosts')
        ) {
            return $params['threads'];
        }

        /* @var $threadModel XenForo_Model_Thread */
        $threadModel = $core->getModelFromCache('XenForo_Model_Thread');

        $forumIds = $this->_helperGetForumIdsFromOption(empty($widget['options']['forums'])
            ? array() : $widget['options']['forums'], $params,
            empty($widget['options']['as_guest']) ? false : true);
        if (!empty($widget['_ajaxLoadParams']['forumIds'])) {
            $forumIds = $widget['_ajaxLoadParams']['forumIds'];
        }
        if (empty($forumIds)) {
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
            'join' => XenForo_Model_Thread::FETCH_USER | XenForo_Model_Thread::FETCH_FORUM,
        );

        // process sticky
        // since 2.4.7
        if (isset($widget['options']['sticky'])
            && is_numeric($widget['options']['sticky'])
        ) {
            $conditions['sticky'] = intval($widget['options']['sticky']);
        }

        // process prefix
        // since 1.3.4
        if (!empty($widget['options']['prefixes'])) {
            $conditions['prefix_id'] = $widget['options']['prefixes'];
        }

        // process discussion_open
        // since 2.5
        if (!empty($widget['options']['open_only'])) {
            $conditions['discussion_open'] = true;
        }

        // get first post if layout needs it
        // since 2.4
        if ($layoutNeedPost) {
            $fetchOptions['join'] |= XenForo_Model_Thread::FETCH_FIRSTPOST;
        }

        // include is_new if option is turned on
        // since 2.5.1
        if (!empty($widget['options']['is_new'])) {
            $fetchOptions['readUserId'] = XenForo_Visitor::getUserId();
        }

        // since 2.6.3
        if (WidgetFramework_Core::contentTaggingFound()
            && !empty($widget['options']['tags'])
        ) {
            $threadIds = array();

            /** @var XenForo_Model_Tag $tagModel */
            $tagModel = $threadModel->getModelFromCache('XenForo_Model_Tag');

            foreach ($widget['options']['tags'] as $tag) {
                $contentIds = $tagModel->getContentIdsByTagId($tag['tag_id'], $widget['options']['limit'] * 3);
                foreach ($contentIds as $contentId) {
                    if ($contentId[0] === 'thread') {
                        $threadIds[] = $contentId[1];
                    }
                }
            }

            $conditions[WidgetFramework_XenForo_Model_Thread::CONDITIONS_THREAD_ID] = $threadIds;
        }

        switch ($widget['options']['type']) {
            case 'recent':
                $threads = $threadModel->getThreads($conditions, array_merge($fetchOptions, array(
                    'order' => 'last_post_date',
                    'orderDirection' => empty($widget['options']['order_reverted']) ? 'desc' : 'asc',
                    'join' => 0,
                    WidgetFramework_XenForo_Model_Thread::FETCH_OPTIONS_LAST_POST_JOIN => $fetchOptions['join'],
                )));
                break;
            case 'recent_first_poster':
                $threads = $threadModel->getThreads($conditions, array_merge($fetchOptions, array(
                    'order' => 'last_post_date',
                    'orderDirection' => empty($widget['options']['order_reverted']) ? 'desc' : 'asc',
                )));
                break;
            case 'latest_replies':
                $threads = $threadModel->getThreads(array_merge($conditions, array(
                    'reply_count' => array(
                        '>',
                        0
                    ),
                )), array_merge($fetchOptions, array(
                    'order' => 'last_post_date',
                    'orderDirection' => empty($widget['options']['order_reverted']) ? 'desc' : 'asc',
                    'join' => 0,
                    WidgetFramework_XenForo_Model_Thread::FETCH_OPTIONS_LAST_POST_JOIN => $fetchOptions['join'],
                )));
                break;
            case 'popular':
                $threads = $threadModel->getThreads(array_merge($conditions, array(
                    WidgetFramework_XenForo_Model_Thread::CONDITIONS_POST_DATE => array(
                        '>',
                        XenForo_Application::$time - $widget['options']['cutoff'] * 86400
                    )
                )), array_merge($fetchOptions, array(
                    'order' => 'view_count',
                    'orderDirection' => empty($widget['options']['order_reverted']) ? 'desc' : 'asc',
                )));
                break;
            case 'most_replied':
                $threads = $threadModel->getThreads(array_merge($conditions, array(
                    WidgetFramework_XenForo_Model_Thread::CONDITIONS_POST_DATE => array(
                        '>',
                        XenForo_Application::$time - $widget['options']['cutoff'] * 86400
                    )
                )), array_merge($fetchOptions, array(
                    'order' => 'reply_count',
                    'orderDirection' => empty($widget['options']['order_reverted']) ? 'desc' : 'asc',
                )));

                foreach (array_keys($threads) as $threadId) {
                    if ($threads[$threadId]['reply_count'] == 0) {
                        // remove threads with zero reply_count
                        unset($threads[$threadId]);
                    }
                }
                break;
            case 'most_liked':
                $threads = $threadModel->getThreads(array_merge($conditions, array(
                    WidgetFramework_XenForo_Model_Thread::CONDITIONS_POST_DATE => array(
                        '>',
                        XenForo_Application::$time - $widget['options']['cutoff'] * 86400
                    )
                )), array_merge($fetchOptions, array(
                    'order' => 'first_post_likes',
                    'orderDirection' => empty($widget['options']['order_reverted']) ? 'desc' : 'asc',
                )));

                foreach (array_keys($threads) as $threadId) {
                    if ($threads[$threadId]['first_post_likes'] == 0) {
                        // remove threads with zero first_post_likes
                        unset($threads[$threadId]);
                    }
                }
                break;
            case 'polls':
                $threads = $threadModel->getThreads(array_merge($conditions, array(
                    WidgetFramework_XenForo_Model_Thread::CONDITIONS_DISCUSSION_TYPE => 'poll'
                )), array_merge($fetchOptions, array(
                    'order' => 'post_date',
                    'orderDirection' => empty($widget['options']['order_reverted']) ? 'desc' : 'asc',
                )));
                break;
            case 'new':
            default:
                $threads = $threadModel->getThreads($conditions, array_merge($fetchOptions, array(
                    'order' => 'post_date',
                    'orderDirection' => empty($widget['options']['order_reverted']) ? 'desc' : 'asc',
                )));
                break;
        }

        if (!empty($threads)) {
            $this->_prepareThreads($widget, $positionCode, $params, $renderTemplateObject, $threads);
        }

        if (count($threads) > $widget['options']['limit']) {
            // too many threads (because we fetched 3 times as needed)
            $threads = array_slice($threads, 0, $widget['options']['limit'], true);
        }

        return $threads;
    }

    /**
     * @param array $widget
     * @param string $positionCode
     * @param array $params
     * @param XenForo_Template_Abstract $renderTemplateObject
     * @param array $threads
     */
    protected function _prepareThreads(
        /** @noinspection PhpUnusedParameterInspection */
        array $widget,
        $positionCode,
        array $params,
        $renderTemplateObject,
        array &$threads
    ) {
        $core = WidgetFramework_Core::getInstance();
        $layoutNeedPost = $renderTemplateObject->getParam('layoutNeedPost');

        /** @var WidgetFramework_XenForo_Model_Thread $threadModel */
        $threadModel = $core->getModelFromCache('XenForo_Model_Thread');
        /** @var XenForo_Model_Node $nodeModel */
        $nodeModel = $core->getModelFromCache('XenForo_Model_Node');
        /** @var XenForo_Model_Forum $forumModel */
        $forumModel = $core->getModelFromCache('XenForo_Model_Forum');
        /** @var XenForo_Model_User $userModel */
        $userModel = $core->getModelFromCache('XenForo_Model_User');
        /** @var XenForo_Model_Post $postModel */
        $postModel = $core->getModelFromCache('XenForo_Model_Post');

        $permissionCombinationId = empty($widget['options']['as_guest']) ? null : 1;
        $nodePermissions = $nodeModel->getNodePermissionsForPermissionCombination($permissionCombinationId);

        $viewObj = self::getViewObject($params, $renderTemplateObject);
        if ($layoutNeedPost
            && !empty($viewObj)
        ) {
            $bbCodeFormatter = XenForo_BbCode_Formatter_Base::create('Base', array('view' => $viewObj));
            if (XenForo_Application::$versionId < 1020000) {
                // XenForo 1.1.x
                $bbCodeParser = new XenForo_BbCode_Parser($bbCodeFormatter);
            } else {
                // XenForo 1.2.x
                $bbCodeParser = XenForo_BbCode_Parser::create($bbCodeFormatter);
            }
            $bbCodeOptions = array(
                'states' => array(),
                'contentType' => 'post',
                'contentIdKey' => 'post_id'
            );

            $postsWithAttachment = array();
            foreach (array_keys($threads) as $threadId) {
                $threadRef = &$threads[$threadId];

                if (empty($threadRef['attach_count'])) {
                    continue;
                }

                if (!empty($threadRef['fetched_last_post'])) {
                    $postsWithAttachment[$threadRef['last_post_id']] = array(
                        'post_id' => $threadRef['last_post_id'],
                        'thread_id' => $threadId,
                        'attach_count' => $threadRef['attach_count'],
                    );
                } else {
                    $postsWithAttachment[$threadRef['first_post_id']] = array(
                        'post_id' => $threadRef['first_post_id'],
                        'thread_id' => $threadId,
                        'attach_count' => $threadRef['attach_count'],
                    );
                }
            }
            if (!empty($postsWithAttachment)) {
                $postsWithAttachment = $postModel->getAndMergeAttachmentsIntoPosts($postsWithAttachment);
                foreach ($postsWithAttachment as $postWithAttachment) {
                    if (empty($postWithAttachment['attachments'])) {
                        continue;
                    }

                    if (empty($threads[$postWithAttachment['thread_id']])) {
                        continue;
                    }
                    $threadRef = &$threads[$postWithAttachment['thread_id']];

                    $threadRef['attachments'] = $postWithAttachment['attachments'];
                }
            }
        }

        $threadForumIds = array();
        foreach ($threads as $thread) {
            $threadForumIds[] = $thread['node_id'];
        }
        $threadForums = $forumModel->getForumsByIds($threadForumIds);

        $viewingUser = (empty($widget['options']['as_guest']) ? null : $userModel->getVisitingGuestUser());

        foreach (array_keys($threads) as $threadId) {
            $threadRef = &$threads[$threadId];

            if (empty($nodePermissions[$threadRef['node_id']])) {
                unset($threads[$threadId]);
                continue;
            }
            $threadPermissionsRef = &$nodePermissions[$threadRef['node_id']];

            if (empty($threadForums[$threadRef['node_id']])) {
                unset($threads[$threadId]);
                continue;
            }
            $threadForumRef = &$threadForums[$threadRef['node_id']];

            if ($threadModel->isRedirect($threadRef)) {
                unset($threads[$threadId]);
                continue;
            }

            if (!$threadModel->canViewThreadAndContainer($threadRef, $threadForumRef, $null,
                $threadPermissionsRef, $viewingUser)
            ) {
                unset($threads[$threadId]);
                continue;
            }

            if (!empty($bbCodeParser)
                && !empty($bbCodeOptions)
            ) {
                $threadBbCodeOptions = $bbCodeOptions;
                $threadBbCodeOptions['states']['viewAttachments'] =
                    $threadModel->canViewAttachmentsInThread($threadRef, $threadForumRef, $null,
                        $threadPermissionsRef, $viewingUser);
                $threadRef['messageHtml'] = WidgetFramework_ShippableHelper_Html::preSnippet(
                    $threadRef, $bbCodeParser, $threadBbCodeOptions);
            }

            $threadRef = $threadModel->WidgetFramework_prepareThreadForRendererThreads($threadRef, $threadForumRef,
                $threadPermissionsRef, $viewingUser);
        }
    }

    protected function _getAjaxLoadParams(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $template
    ) {
        $ajaxLoadParams = parent::_getAjaxLoadParams($widget, $positionCode, $params, $template);

        if ($this->_helperDetectSpecialForums($widget['options']['forums'])) {
            $forumIds = $this->_helperGetForumIdsFromOption($widget['options']['forums'], $params,
                empty($widget['options']['as_guest']) ? false : true);
            $ajaxLoadParams['forumIds'] = $forumIds;
        }

        return $ajaxLoadParams;
    }


}
