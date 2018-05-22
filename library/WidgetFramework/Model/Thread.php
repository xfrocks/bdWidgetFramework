<?php

class WidgetFramework_Model_Thread extends XenForo_Model
{
    const CONDITIONS_THREAD_ID = 'WidgetFramework_thread_id';
    const CONDITIONS_THREAD_ID_NOT = 'WidgetFramework_thread_id_not';
    const CONDITIONS_TAG_ID = 'WidgetFramework_tag_id';
    const FETCH_OPTIONS_JOIN_XF_TAG_CONTENT = 'WidgetFramework_join_xfTagContent';
    const FETCH_OPTIONS_ORDER_RANDOM = 'WidgetFramework_random';

    public function getThreadIds(array $conditions, array $fetchOptions = array())
    {
        if (isset($fetchOptions['join'])) {
            throw new XenForo_Exception(sprintf('%s does not support `join` in $fetchOptions', __METHOD__));
        }

        $whereConditions = $this->prepareThreadConditions($conditions, $fetchOptions);
        $sqlClauses = $this->prepareThreadFetchOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
        $forceIndex = (!empty($fetchOptions['forceThreadIndex']) ? 'FORCE INDEX (' . $fetchOptions['forceThreadIndex'] . ')' : '');

        return $this->_getDb()->fetchCol($this->limitQueryResults('
            SELECT thread.thread_id
            FROM xf_thread AS thread ' . $forceIndex . '
            ' . $sqlClauses['joinTables'] . '
            WHERE ' . $whereConditions . '
            ' . $sqlClauses['orderClause'] . '
        ', $limitOptions['limit'], $limitOptions['offset']));
    }

    public function getThreadsByIdsInOrder(array $threadIds, array $fetchOptions)
    {
        if (empty($threadIds)) {
            return array();
        }

        $threads = $this->_getThreadModel()->getThreadsByIds($threadIds, $fetchOptions);

        $ordered = array();
        foreach ($threadIds as $threadId) {
            if (isset($threads[$threadId])) {
                $ordered[$threadId] = $threads[$threadId];
            }
        }

        return $ordered;
    }

    public function countThreads(array $conditions, array $fetchOptions = array())
    {
        $whereConditions = $this->prepareThreadConditions($conditions, $fetchOptions);
        $sqlClauses = $this->prepareThreadFetchOptions($fetchOptions);

        return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_thread AS thread
			' . $sqlClauses['joinTables'] . '
			WHERE ' . $whereConditions . '
		');
    }

    public function prepareThreadForRendererThreads(
        array $thread,
        array $forum,
        array $nodePermissions = null,
        array $viewingUser = null
    ) {
        $thread = $this->_getThreadModel()->prepareThread($thread, $forum, $nodePermissions, $viewingUser);

        $thread['canInlineMod'] = false;
        $thread['canEditThread'] = false;

        if (!empty($thread['fetched_last_post_user'])) {
            $thread['user_id'] = $thread['last_post_user_id'];
            $thread['username'] = $thread['last_post_username'];

            $thread['lastPostInfo'] = array_merge($thread['lastPostInfo'], array(
                'user_id' => $thread['last_post_user_id'],
                'gravatar' => $thread['gravatar'],
                'avatar_date' => $thread['avatar_date'],
            ));
        }

        $thread['forum'] = $forum;

        return $thread;
    }

    public function prepareThreadConditions(array $conditions, array &$fetchOptions)
    {
        $result = $this->_getThreadModel()->prepareThreadConditions($conditions, $fetchOptions);
        $sqlConditions = array($result);

        if (isset($conditions[self::CONDITIONS_THREAD_ID])) {
            if (is_array($conditions[self::CONDITIONS_THREAD_ID])) {
                $sqlConditions[] = sprintf(
                    'thread.thread_id IN (%s)',
                    $this->_getDb()->quote($conditions[self::CONDITIONS_THREAD_ID])
                );
            } else {
                $sqlConditions[] = 'thread.thread_id = '
                    . $this->_getDb()->quote($conditions[self::CONDITIONS_THREAD_ID]);
            }
        }

        if (isset($conditions[self::CONDITIONS_THREAD_ID_NOT])) {
            if (is_array($conditions[self::CONDITIONS_THREAD_ID_NOT])) {
                $sqlConditions[] = sprintf(
                    'thread.thread_id NOT IN (%s)',
                    $this->_getDb()->quote($conditions[self::CONDITIONS_THREAD_ID_NOT])
                );
            } else {
                $sqlConditions[] = 'thread.thread_id <> '
                    . $this->_getDb()->quote($conditions[self::CONDITIONS_THREAD_ID_NOT]);
            }
        }

        if (isset($conditions[self::CONDITIONS_TAG_ID])) {
            $fetchOptions[self::FETCH_OPTIONS_JOIN_XF_TAG_CONTENT] = true;

            $sqlConditions[] = 'tagged.tag_id = '
                . $this->_getDb()->quote($conditions[self::CONDITIONS_TAG_ID]);

            if (isset($conditions['post_date'])
                && is_array($conditions['post_date'])
            ) {
                $sqlConditions[] = $this->getCutOffCondition('tagged.content_date', $conditions['post_date']);
            }
        }

        if (count($sqlConditions) > 1) {
            // some of our conditions have been found
            return $this->getConditionsForClause($sqlConditions);
        } else {
            return $result;
        }
    }

    public function prepareThreadFetchOptions(array $fetchOptions)
    {
        $result = $this->_getThreadModel()->prepareThreadFetchOptions($fetchOptions);

        if (!empty($fetchOptions[self::FETCH_OPTIONS_JOIN_XF_TAG_CONTENT])) {
            $result['joinTables'] .= '
                LEFT JOIN xf_tag_content AS tagged
                    ON (tagged.content_type = "thread"
                    AND tagged.content_id = thread.thread_id)';
        }

        if (!empty($fetchOptions['order'])
            && $fetchOptions['order'] === self::FETCH_OPTIONS_ORDER_RANDOM
        ) {
            $result['orderClause'] = 'ORDER BY RAND()';
        }

        return $result;
    }

    /**
     * @return XenForo_Model_Thread
     */
    protected function _getThreadModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_Thread');
    }
}
