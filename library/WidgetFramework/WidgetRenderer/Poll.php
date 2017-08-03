<?php

class WidgetFramework_WidgetRenderer_Poll extends WidgetFramework_WidgetRenderer
{
    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])) {
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
                'cutoff' => XenForo_Input::UINT,
                'forums' => XenForo_Input::ARRAY_SIMPLE,
                'sticky' => XenForo_Input::STRING,
                'open_only' => XenForo_Input::UINT,
            ),
            'useWrapper' => false,
        );
    }

    protected function _getOptionsTemplate()
    {
        return 'wf_widget_options_poll';
    }

    protected function _renderOptions(XenForo_Template_Abstract $template)
    {
        $params = $template->getParams();

        $forums = empty($params['options']['forums']) ? array() : $params['options']['forums'];
        $forums = $this->_helperPrepareForumsOptionSource($forums, true);
        $template->setParam('forums', $forums);

        return parent::_renderOptions($template);
    }

    protected function _validateOptionValue($optionKey, &$optionValue)
    {
        switch ($optionKey) {
            case 'thread_id':
                if (!empty($optionValue)) {
                    $optionValue = strtolower($optionValue);

                    if ($optionValue === 'random') {
                        // random mode
                    } else {
                        /** @var XenForo_Model_Thread $threadModel */
                        $threadModel = XenForo_Model::create('XenForo_Model_Thread');
                        $thread = $threadModel->getThreadById($optionValue);
                        if (empty($thread)) {
                            throw new XenForo_Exception(new XenForo_Phrase('requested_thread_not_found'), true);
                        } elseif (empty($thread['discussion_type']) OR 'poll' != $thread['discussion_type']) {
                            throw new XenForo_Exception(
                                new XenForo_Phrase('wf_requested_thread_does_not_have_poll'),
                                true
                            );
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

    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        if (empty($widget['options']['cutoff'])) {
            $widget['options']['cutoff'] = 5;
        }

        $thread = $this->_getThread($widget, $positionCode, $params, $renderTemplateObject);
        if (empty($thread['node_id'])) {
            return '';
        }

        $core = WidgetFramework_Core::getInstance();
        /** @var XenForo_Model_Forum $forumModel */
        $forumModel = $core->getModelFromCache('XenForo_Model_Forum');
        /** @var XenForo_Model_Poll $pollModel */
        $pollModel = $core->getModelFromCache('XenForo_Model_Poll');
        /** @var XenForo_Model_Thread $threadModel */
        $threadModel = $core->getModelFromCache('XenForo_Model_Thread');

        $forum = $forumModel->getForumById($thread['node_id']);
        if (empty($forum)) {
            return '';
        }

        $poll = $pollModel->getPollByContent('thread', $thread['thread_id']);
        if (empty($poll)) {
            return '';
        }
        $canVoteOnPoll = $threadModel->canVoteOnPoll($poll, $thread, $forum);
        $poll = $pollModel->preparePoll($poll, $canVoteOnPoll);

        $renderTemplateObject->setParam('thread', $thread);
        $renderTemplateObject->setParam('poll', $poll);

        return $renderTemplateObject->render();
    }

    protected function _getThread(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        $core = WidgetFramework_Core::getInstance();
        /** @var XenForo_Model_Thread $threadModel */
        $threadModel = $core->getModelFromCache('XenForo_Model_Thread');

        if (!empty($widget['options']['thread_id'])
            && intval($widget['options']['thread_id']) > 0
        ) {
            // one specific thread
            return $threadModel->getThreadById($widget['options']['thread_id']);
        }

        $forumIds = $this->_helperGetForumIdsFromOption(empty($widget['options']['forums'])
            ? array() : $widget['options']['forums'], $params);

        $conditions = array(
            'node_id' => $forumIds,
            'discussion_type' => 'poll',
            'deleted' => false,
            'moderated' => false,
        );

        $fetchOptions = array(
            'order' => 'post_date',
            'orderDirection' => 'desc',
        );

        if (isset($widget['options']['sticky'])
            && is_numeric($widget['options']['sticky'])
        ) {
            $conditions['sticky'] = intval($widget['options']['sticky']);
        }

        if (!empty($widget['options']['open_only'])) {
            $conditions['discussion_open'] = true;
        }

        if ($widget['options']['thread_id'] === 'random') {
            $conditions['post_date'] = array(
                '>',
                XenForo_Application::$time - $widget['options']['cutoff'] * 86400
            );
            $fetchOptions['order'] = WidgetFramework_Model_Thread::FETCH_OPTIONS_ORDER_RANDOM;
        }

        /** @var WidgetFramework_Model_Thread $wfThreadModel */
        $wfThreadModel = $core->getModelFromCache('WidgetFramework_Model_Thread');
        $threadIds = $wfThreadModel->getThreadIds($conditions, $fetchOptions);
        $threads = $wfThreadModel->getThreadsByIdsInOrder($threadIds);

        if (!empty($threads)) {
            /** @var XenForo_Model_Node $nodeModel */
            $nodeModel = $core->getModelFromCache('XenForo_Model_Node');
            $nodePermissions = $nodeModel->getNodePermissionsForPermissionCombination();

            foreach ($threads as $_thread) {
                if ($threadModel->canViewThread($_thread, $_thread, $null, $nodePermissions[$_thread['node_id']])) {
                    return $_thread;
                }
            }
        }

        return null;
    }
}
