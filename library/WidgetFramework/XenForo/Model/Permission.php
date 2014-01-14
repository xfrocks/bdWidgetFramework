<?php

class WidgetFramework_XenForo_Model_Permission extends XFCP_WidgetFramework_XenForo_Model_Permission
{
	public function rebuildPermissionCache($maxExecution = 0, $startCombinationId = 0)
	{
		// we hook ourself here because XenForo_DataWriter_Node::_postSave trigger this
		// when parent_node_id is changed
		if ($startCombinationId === 0)
		{
			WidgetFramework_Helper_Index::rebuildChildNodesCache($this);
		}

		return parent::rebuildPermissionCache($maxExecution, $startCombinationId);
	}

}
