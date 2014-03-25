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

}
