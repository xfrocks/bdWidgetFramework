<?php

class WidgetFramework_Extend_Model_Thread extends XFCP_WidgetFramework_Extend_Model_Thread
{
	const CONDITIONS_DISCUSSION_TYPE = 'WidgetFramework_discussion_type';
	const CONDITIONS_POST_DATE = 'WidgetFramework_post_date';

	const FETCH_OPTIONS_FORUM_FULL_JOIN = 'WidgetFramework_forum_full_join';
	const FETCH_OPTIONS_LAST_POST_JOIN = 'WidgetFramework_last_post_join';
	const FETCH_OPTIONS_POLL_JOIN = 'WidgetFramework_poll_join';

	public function prepareThreadConditions(array $conditions, array &$fetchOptions)
	{
		$result = parent::prepareThreadConditions($conditions, $fetchOptions);

		$sqlConditions = array($result);
		$db = $this->_getDb();

		if (!empty($conditions[self::CONDITIONS_POST_DATE]) AND is_array($conditions[self::CONDITIONS_POST_DATE]))
		{
			list($operator, $cutOff) = $conditions[self::CONDITIONS_POST_DATE];
			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "thread.post_date $operator " . $this->_getDb()->quote($cutOff);
		}

		if (isset($conditions[self::CONDITIONS_DISCUSSION_TYPE]))
		{
			if (is_array($conditions[self::CONDITIONS_DISCUSSION_TYPE]))
			{
				$sqlConditions[] = "thread.discussion_type IN (" . $this->_getDb()->quote($conditions[self::CONDITIONS_DISCUSSION_TYPE]) . ")";
			}
			else
			{
				$sqlConditions[] = "thread.discussion_type = " . $this->_getDb()->quote($conditions[self::CONDITIONS_DISCUSSION_TYPE]);
			}
		}

		if (count($sqlConditions) > 1)
		{
			// some of our conditions have been found
			return $this->getConditionsForClause($sqlConditions);
		}
		else
		{
			return $result;
		}
	}

	public function prepareThreadFetchOptions(array $fetchOptions)
	{
		$result = parent::prepareThreadFetchOptions($fetchOptions);
		extract($result);

		if (!empty($fetchOptions[self::FETCH_OPTIONS_POLL_JOIN]))
		{
			$selectFields .= ',
					poll.*';
			$joinTables .= '
					LEFT JOIN xf_poll AS poll ON
					(poll.content_type = \'thread\' AND content_id = thread.thread_id)';
		}

		if (!empty($fetchOptions[self::FETCH_OPTIONS_FORUM_FULL_JOIN]) AND empty($fetchOptions['join']))
		{
			$selectFields .= ',
					forum.*';
			$joinTables .= '
					INNER JOIN xf_forum AS forum ON
					(forum.node_id = thread.node_id)';
		}

		if (!empty($fetchOptions[self::FETCH_OPTIONS_LAST_POST_JOIN]) AND empty($fetchOptions['join']))
		{
			if ($fetchOptions[self::FETCH_OPTIONS_LAST_POST_JOIN] & self::FETCH_USER)
			{
				$selectFields .= ',
						user.*';
				$joinTables .= '
						LEFT JOIN xf_user AS user ON
						(user.user_id = thread.last_post_user_id)';
			}
			elseif ($fetchOptions[self::FETCH_OPTIONS_LAST_POST_JOIN] & self::FETCH_AVATAR)
			{
				$selectFields .= ',
						user.gender, user.avatar_date, user.gravatar';
				$joinTables .= '
						LEFT JOIN xf_user AS user ON
						(user.user_id = thread.last_post_user_id)';
			}

		}

		return compact('selectFields', 'joinTables', 'orderClause');
	}

	public function WidgetFramework_prepareThreadForRendererThreads(array $thread, array $forum, array $nodePermissions = null, array $viewingUser = null)
	{
		$thread = $this->prepareThread($thread, $forum, $nodePermissions, $viewingUser);

		$thread['canInlineMod'] = false;
		$thread['canEditThread'] = false;

		return $thread;
	}

}
