<?php

class WidgetFramework_Model_User extends XenForo_Model
{
    const CONDITIONS_STATUS_DATE = 'WidgetFramework_status_date';
    const CONDITIONS_DOB = 'WidgetFramework_dob';
    const CONDITIONS_HAS_AVATAR = 'WidgetFramework_has_avatar';
    const ORDER_STATUS_DATE = 'WidgetFramework_status_date';
    const ORDER_RESOURCE_COUNT = 'WidgetFramework_resource_count';

    public function getUserIds(array $conditions, array $fetchOptions = array())
    {
        if (isset($fetchOptions['join'])) {
            throw new XenForo_Exception(sprintf('%s does not support `join` in $fetchOptions', __METHOD__));
        }

        $whereClause = $this->prepareUserConditions($conditions, $fetchOptions);
        $orderClause = $this->_getUserModel()->prepareUserOrderOptions($fetchOptions, 'user.user_id');
        $joinOptions = $this->_getUserModel()->prepareUserFetchOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        return $this->_getDb()->fetchCol($this->limitQueryResults('
			SELECT user.user_id
			FROM xf_user AS user
			' . $joinOptions['joinTables'] . '
			WHERE ' . $whereClause . '
			' . $orderClause . '
		', $limitOptions['limit'], $limitOptions['offset']));
    }

    public function getUsersByIdsInOrder(array $userIds, $fetchOptionsJoin = 0)
    {
        if (empty($userIds)) {
            return array();
        }

        /** @var XenForo_Model_User $userModel */
        $userModel = $this->getModelFromCache('XenForo_Model_User');
        $users = $userModel->getUsersByIds($userIds, array('join' => $fetchOptionsJoin));

        $ordered = array();
        foreach ($userIds as $userId) {
            if (isset($users[$userId])) {
                $ordered[$userId] = $users[$userId];
            }
        }

        return $ordered;
    }

    public function prepareUserConditions(array $conditions, array &$fetchOptions)
    {
        $result = $this->_getUserModel()->prepareUserConditions($conditions, $fetchOptions);
        $db = $this->_getDb();
        $sqlConditions = array($result);

        if (isset($conditions[self::CONDITIONS_STATUS_DATE])
            && is_array($conditions[self::CONDITIONS_STATUS_DATE])
        ) {
            list($operator, $cutOff) = $conditions[self::CONDITIONS_STATUS_DATE];

            $this->assertValidCutOffOperator($operator);
            $sqlConditions[] = "user_profile.status_date $operator " . $db->quote($cutOff);

            if (!isset($fetchOptions['join'])) {
                $fetchOptions['join'] = 0;
            }
            $fetchOptions['join'] |= XenForo_Model_User::FETCH_USER_PROFILE;
        }

        if (isset($conditions[self::CONDITIONS_DOB])
            && is_array($conditions[self::CONDITIONS_DOB])
        ) {
            if (!empty($conditions[self::CONDITIONS_DOB]['d'])) {
                // direct mode like
                // array('d' => 1, 'm' => 1)
                // we will make it an array of array like this
                $conditions[self::CONDITIONS_DOB] = array($conditions[self::CONDITIONS_DOB]);
            }

            $tmp = array();
            foreach ($conditions[self::CONDITIONS_DOB] as $pair) {
                $tmp[] = '(user_profile.dob_day = ' . intval($pair['d'])
                    . ' AND user_profile.dob_month = ' . intval($pair['m']) . ')';
            }
            $sqlConditions[] = '(' . implode(' OR ', $tmp) . ')';
            $sqlConditions[] = 'user_option.show_dob_date = 1';

            if (!isset($fetchOptions['join'])) {
                $fetchOptions['join'] = 0;
            }
            $fetchOptions['join'] |= XenForo_Model_User::FETCH_USER_PROFILE;
            $fetchOptions['join'] |= XenForo_Model_User::FETCH_USER_OPTION;
        }

        if (!empty($conditions[self::CONDITIONS_HAS_AVATAR])) {
            $sqlConditions[] = '(user.avatar_date > 0 OR user.gravatar <> \'\')';
        }

        if (count($sqlConditions) > 1) {
            // there some of our custom conditions found
            $result = $this->getConditionsForClause($sqlConditions);
        }

        return $result;
    }

    public function getOrderByClause(array $choices, array $fetchOptions, $defaultOrderSql = '')
    {
        $choices[self::ORDER_STATUS_DATE] = 'user_profile.status_date';
        $choices[self::ORDER_RESOURCE_COUNT] = 'user.resource_count';

        return parent::getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
    }

    /**
     * @return XenForo_Model_User
     */
    protected function _getUserModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_User');
    }
}
