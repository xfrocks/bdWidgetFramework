<?php

class WidgetFramework_Model_WidgetPage extends XenForo_Model
{

	public function canViewWidgetPage(array $widgetPage, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($widgetPage['node_id'], $viewingUser, $nodePermissions);

		return XenForo_Permission::hasContentPermission($nodePermissions, 'view');
	}

	public function getList(array $conditions = array(), array $fetchOptions = array())
	{
		$data = $this->getWidgetPages($conditions, $fetchOptions);
		$list = array();

		foreach ($data as $id => $row)
		{
			$list[$id] = $row['title'];
		}

		return $list;
	}

	public function getWidgetPageById($id, array $fetchOptions = array())
	{
		$data = $this->getWidgetPages(array('node_id' => $id), $fetchOptions);

		return reset($data);
	}

	public function getWidgetPageByName($name, array $fetchOptions = array())
	{
		$data = $this->getWidgetPages(array('node_name' => $name), $fetchOptions);

		return reset($data);
	}

	public function getWidgetPages(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareWidgetPageConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareWidgetPageOrderOptions($fetchOptions);
		$joinOptions = $this->prepareWidgetPageFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		$all = $this->fetchAllKeyed($this->limitQueryResults("
				SELECT widget_page.*,
				node.*
				$joinOptions[selectFields]
				FROM `xf_widgetframework_widget_page` AS widget_page
				INNER JOIN `xf_node` AS node ON (node.node_id = widget_page.node_id)
				$joinOptions[joinTables]
				WHERE $whereConditions
				$orderClause
				", $limitOptions['limit'], $limitOptions['offset']), 'node_id');

		foreach ($all as &$widgetPage)
		{
			$widgetPage['widgets'] = @unserialize($widgetPage['widgets']);
			$widgetPage['options'] = @unserialize($widgetPage['options']);
		}

		return $all;
	}

	public function countWidgetPages(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareWidgetPageConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareWidgetPageOrderOptions($fetchOptions);
		$joinOptions = $this->prepareWidgetPageFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne("
				SELECT COUNT(*)
				FROM `xf_widgetframework_widget_page` AS widget_page
				$joinOptions[joinTables]
				WHERE $whereConditions
				");
	}

	public function prepareWidgetPageConditions(array $conditions = array(), array $fetchOptions = array())
	{
		$sqlConditions = array();
		$db = $this->_getDb();

		if (isset($conditions['node_id']))
		{
			if (is_array($conditions['node_id']))
			{
				if (!empty($conditions['node_id']))
				{
					// only use IN condition if the array is not empty (nasty!)
					$sqlConditions[] = "widget_page.node_id IN (" . $db->quote($conditions['node_id']) . ")";
				}
			}
			else
			{
				$sqlConditions[] = "widget_page.node_id = " . $db->quote($conditions['node_id']);
			}
		}

		if (isset($conditions['node_name']))
		{
			if (is_array($conditions['node_name']))
			{
				if (!empty($conditions['node_name']))
				{
					// only use IN condition if the array is not empty (nasty!)
					$sqlConditions[] = "node.node_name IN (" . $db->quote($conditions['node_name']) . ")";
				}
			}
			else
			{
				$sqlConditions[] = "node.node_name = " . $db->quote($conditions['node_name']);
			}
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	public function prepareWidgetPageFetchOptions(array $fetchOptions = array())
	{
		$selectFields = '';
		$joinTables = '';

		$db = $this->_getDb();

		if (!empty($fetchOptions['permissionCombinationId']))
		{
			$selectFields .= ',
					permission.cache_value AS node_permission_cache';
			$joinTables .= '
					LEFT JOIN xf_permission_cache_content AS permission
					ON (permission.permission_combination_id = ' . $db->quote($fetchOptions['permissionCombinationId']) . '
							AND permission.content_type = \'node\'
							AND permission.content_id = widget_page.node_id)';
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables' => $joinTables
		);
	}

	public function prepareWidgetPageOrderOptions(array $fetchOptions = array(), $defaultOrderSql = '')
	{
		$choices = array();

		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}

}
