<?php

class WidgetFramework_XenForo_Model_ProfilePost extends XFCP_WidgetFramework_XenForo_Model_ProfilePost
{
	public function WidgetFramework_getProfilePostIdsOfUserStatuses($userIds, $limit = 0)
	{
		return $this->_getDb()->fetchCol('
			SELECT profile_post_id
			FROM `xf_profile_post`
			WHERE user_id IN (' . $this->_getDb()->quote($userIds) . ')
				AND profile_user_id = user_id
			ORDER BY post_date DESC
			' . ($limit > 0 ? sprintf('LIMIT %d', $limit) : '') . '
		');
	}

	public function WidgetFramework_getLatestProfilePosts(array $conditions = array(), array $fetchOptions = array())
	{
		if (XenForo_Application::$versionId > 1040000)
		{
			return parent::getLatestProfilePosts($conditions, $fetchOptions);
		}

		$whereClause = $this->prepareProfilePostConditions($conditions, $fetchOptions);

		$sqlClauses = $this->prepareProfilePostFetchOptions($fetchOptions);
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

}
