<?php
class WidgetFramework_Extend_Model_Thread extends XFCP_WidgetFramework_Extend_Model_Thread {
	public function prepareThreadConditions(array $conditions, array &$fetchOptions) {
		$result = parent::prepareThreadConditions($conditions, $fetchOptions);
	
		$sqlConditions = array($result);
		$db = $this->_getDb();
		
		if (!empty($conditions['thread_id'])) {
			if (is_array($conditions['thread_id'])) {
				$sqlConditions[] = 'thread.thread_id IN (' . $db->quote($conditions['thread_id']) . ')';
			} else {
				$sqlConditions[] = 'thread.thread_id = ' . $db->quote($conditions['thread_id']);
			}
		}
		
		if (!empty($conditions['forum_ids'])) {
			// $sqlConditions[] = 'thread.node_id IN (' . $this->_getDb()->quote($conditions['forum_ids']) . ')';
			// throw new XenForo_Exception('forum_ids has been deprecated, please use forum_id OR node_id in $conditions');
			// throwing exception no more... (conflicted with XenPorta)
		}
		
		if (!empty($conditions['post_date']) && is_array($conditions['post_date'])) {
			list($operator, $cutOff) = $conditions['post_date'];
			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "thread.post_date $operator " . $this->_getDb()->quote($cutOff);
		}
		
		if (!empty($conditions['discussion_type'])) {
			$sqlConditions[] = "thread.discussion_type = " . $this->_getDb()->quote($conditions['discussion_type']);
		}
		
		if (count($sqlConditions) > 1) {
			// some of our conditions have been found
			return $this->getConditionsForClause($sqlConditions);
		} else {
			return $result;
		}
	}
	
	public function prepareThreadFetchOptions(array $fetchOptions) {
		$result = parent::prepareThreadFetchOptions($fetchOptions);
		extract($result);
		
		if (!empty($fetchOptions['poll_join'])) {
			$selectFields .= ',
				poll.*';
			$joinTables .= '
				LEFT JOIN xf_poll AS poll ON
					(poll.content_type = \'thread\' AND content_id = thread.thread_id)';
		}
		
		if (!empty($fetchOptions['forum_full_join']) AND empty($fetchOptions['join'])) {
			$selectFields .= ',
				forum.*';
			$joinTables .= '
				INNER JOIN xf_forum AS forum ON
					(forum.node_id = thread.node_id)';
		}
		
		if (!empty($fetchOptions['last_post_join']) AND empty($fetchOptions['join'])) {
			if ($fetchOptions['last_post_join'] & self::FETCH_USER) {
				$selectFields .= ',
					user.*';
				$joinTables .= '
					LEFT JOIN xf_user AS user ON
						(user.user_id = thread.last_post_user_id)';
			} else if ($fetchOptions['last_post_join'] & self::FETCH_AVATAR) {
				$selectFields .= ',
					user.gender, user.avatar_date, user.gravatar';
				$joinTables .= '
					LEFT JOIN xf_user AS user ON
						(user.user_id = thread.last_post_user_id)';
			}
			
			
		}
		
		return compact('selectFields' , 'joinTables', 'orderClause');
	}
}