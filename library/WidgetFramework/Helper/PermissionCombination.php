<?php

class WidgetFramework_Helper_PermissionCombination
{
    public static function isGroupOnly($id)
    {
        $groupOnlyCombinationIds = self::getGroupOnlyCombinationIds();
        return in_array($id, $groupOnlyCombinationIds);
    }

    public static function rebuildGroupOnlyCombinationIds()
    {
        $ids = XenForo_Application::getDb()->fetchCol('
            SELECT permission_combination_id
            FROM `xf_permission_combination`
            WHERE user_id = 0
        ');

        XenForo_Application::setSimpleCacheData(
            WidgetFramework_Core::SIMPLE_CACHE_GROUP_ONLY_PERMISSION_COMBINATION_IDS,
            $ids
        );

        return $ids;
    }

    public static function getGroupOnlyCombinationIds()
    {
        $ids = XenForo_Application::getSimpleCacheData(
            WidgetFramework_Core::SIMPLE_CACHE_GROUP_ONLY_PERMISSION_COMBINATION_IDS
        );
        if (!is_array($ids)) {
            $ids = self::rebuildGroupOnlyCombinationIds();
        }

        return $ids;
    }
}
