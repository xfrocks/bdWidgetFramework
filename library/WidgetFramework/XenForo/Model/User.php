<?php

class WidgetFramework_XenForo_Model_User extends XFCP_WidgetFramework_XenForo_Model_User
{

	const CONDITIONS_STATUS_DATE = 'WidgetFramework_status_date';
	const CONDITIONS_DOB = 'WidgetFramework_dob';
	const ORDER_STATUS_DATE = 'WidgetFramework_status_date';
	const ORDER_RESOURCE_COUNT = 'WidgetFramework_resource_count';

	public function prepareUserConditions(array $conditions, array &$fetchOptions)
	{
		$result = parent::prepareUserConditions($conditions, $fetchOptions);
		$db = $this->_getDb();
		$sqlConditions = array($result);

		if (isset($conditions[self::CONDITIONS_STATUS_DATE]) AND is_array($conditions[self::CONDITIONS_STATUS_DATE]))
		{
			list($operator, $cutOff) = $conditions[self::CONDITIONS_STATUS_DATE];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "user_profile.status_date $operator " . $db->quote($cutOff);
		}

		if (isset($conditions[self::CONDITIONS_DOB]) AND is_array($conditions[self::CONDITIONS_DOB]))
		{
			if (!empty($conditions[self::CONDITIONS_DOB]['d']))
			{
				// direct mode like
				// array('d' => 1, 'm' => 1)
				// we will make it an array of array like this
				$conditions[self::CONDITIONS_DOB] = array($conditions[self::CONDITIONS_DOB]);
			}

			$tmp = array();
			foreach ($conditions[self::CONDITIONS_DOB] as $pair)
			{
				$tmp[] = '(user_profile.dob_day = ' . $db->quote($pair['d']) . ' AND user_profile.dob_month = ' . $db->quote($pair['m']) . ')';
			}
			$sqlConditions[] = '(' . implode(' OR ', $tmp) . ')';
			$sqlConditions[] = 'user_option.show_dob_date = 1';
		}

		if (count($sqlConditions) > 1)
		{
			// there some of our custom conditions found
			$result = $this->getConditionsForClause($sqlConditions);
		}

		return $result;
	}

	public function getOrderByClause(array $choices, array $fetchOptions, $defaultOrderSql = '')
	{
		$choices[self::ORDER_STATUS_DATE] = 'user_profile.status_date';

		return parent::getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}

}
