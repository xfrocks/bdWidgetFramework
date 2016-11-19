<?php

class WidgetFramework_Model_ProfilePost extends XenForo_Model
{
    public function getProfilePostIdsOfUserStatuses(array $userIds, $limit = 0)
    {
        if (count($userIds) == 0) {
            return array();
        }

        return $this->_getDb()->fetchCol($this->limitQueryResults('
			SELECT profile_post_id
			FROM `xf_profile_post`
			WHERE user_id IN (' . $this->_getDb()->quote($userIds) . ')
				AND profile_user_id = user_id
			ORDER BY post_date DESC
		', $limit));
    }

    public function getLatestProfilePosts(array $conditions = array(), array $fetchOptions = array())
    {
        if (XenForo_Application::$versionId > 1040000) {
            return $this->_getProfilePostModel()->getLatestProfilePosts($conditions, $fetchOptions);
        }

        $whereClause = $this->_getProfilePostModel()->prepareProfilePostConditions($conditions, $fetchOptions);
        $sqlClauses = $this->_getProfilePostModel()->prepareProfilePostFetchOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        return $this->fetchAllKeyed($this->limitQueryResults('
            SELECT profile_post.*
                ' . $sqlClauses['selectFields'] . '
            FROM xf_profile_post AS profile_post
            ' . $sqlClauses['joinTables'] . '
            WHERE ' . $whereClause . '
            ORDER BY profile_post.post_date DESC
        ', $limitOptions['limit'], $limitOptions['offset']), 'profile_post_id');
    }

    /**
     * @return XenForo_Model_ProfilePost
     */
    protected function _getProfilePostModel()
    {
        return $this->getModelFromCache('XenForo_Model_ProfilePost');
    }
}
