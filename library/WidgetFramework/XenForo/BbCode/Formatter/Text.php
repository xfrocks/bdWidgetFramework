<?php

class WidgetFramework_XenForo_BbCode_Formatter_Text extends XFCP_WidgetFramework_XenForo_BbCode_Formatter_Text
{
    public function getTags()
    {
        $tags = parent::getTags();

        // do not render preview break
        $tags['prbreak'] = array(
            'hasOption' => false,
            'replace' => array('', ''),
        );

        return $tags;
    }

}
