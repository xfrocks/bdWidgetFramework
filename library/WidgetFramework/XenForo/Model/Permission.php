<?php

class WidgetFramework_XenForo_Model_Permission extends XFCP_WidgetFramework_XenForo_Model_Permission
{
    protected $_WidgetFramework_rebuildGroupOnlyCombinationIds = 0;

    public function findOrCreatePermissionCombination($userId, array $userGroupIds, $buildOnCreate = true)
    {
        $this->_WidgetFramework_rebuildGroupOnlyCombinationIds++;

        $permissionCombinationId = parent::findOrCreatePermissionCombination($userId, $userGroupIds, $buildOnCreate);

        $this->_WidgetFramework_rebuildGroupOnlyCombinationIds--;

        return $permissionCombinationId;
    }

    public function rebuildPermissionCache($maxExecution = 0, $startCombinationId = 0)
    {
        // we hook ourselves here because XenForo_DataWriter_Node::_postSave trigger this
        // when parent_node_id is changed
        if ($startCombinationId === 0) {
            WidgetFramework_Helper_Index::rebuildChildNodesCache();
            WidgetFramework_Helper_PermissionCombination::rebuildGroupOnlyCombinationIds();
        }

        return parent::rebuildPermissionCache($maxExecution, $startCombinationId);
    }

    public function rebuildPermissionCombination(array $combination, array $permissionsGrouped, array $entries)
    {
        if ($this->_WidgetFramework_rebuildGroupOnlyCombinationIds > 0) {
            WidgetFramework_Helper_PermissionCombination::rebuildGroupOnlyCombinationIds();
        }

        return parent::rebuildPermissionCombination($combination, $permissionsGrouped, $entries);
    }
}
