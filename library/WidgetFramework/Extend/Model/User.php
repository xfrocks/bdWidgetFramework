<?php

class WidgetFramework_Extend_Model_User extends XFCP_WidgetFramework_Extend_Model_User {
	public function prepareUserConditions(array $conditions, array &$fetchOptions) {
		$result = parent::prepareUserConditions($conditions, $fetchOptions);
		$db = $this->_getDb();
		$sqlConditions = array($result);
		
		if (isset($conditions['WidgetFramework_status_date']) AND is_array($conditions['WidgetFramework_status_date'])) {
			list($operator, $cutOff) = $conditions['WidgetFramework_status_date'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "user_profile.status_date $operator " . $db->quote($cutOff);
		}
		
		if (count($sqlConditions) > 1) {
			// there some of our custom conditions found
			$result = $this->getConditionsForClause($sqlConditions);
		}
		
		return $result;
	}
	
	public function getOrderByClause(array $choices, array $fetchOptions, $defaultOrderSql = '') {
		$choices['WidgetFramework_status_date'] = 'user_profile.status_date';
		
		return parent::getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
}