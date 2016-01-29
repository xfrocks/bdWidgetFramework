<?php

class WidgetFramework_Option
{
    const UPDATER_URL = 'https://xfrocks.com/api/index.php?updater';

    /** @var XenForo_Options */
    protected static $_options = null;

    protected static $_layoutEditorEnabled = null;

    public static function get($key)
    {
        if (self::$_options === null) {
            self::$_options = XenForo_Application::get('options');
        }

        switch ($key) {
            case 'applicationVersionId':
                return XenForo_Application::$versionId;
            case 'cacheCutoffDays':
                return 7;
            case 'indexTabId':
                return 'WidgetFramework_home';
            case 'layoutEditorEnabled':
                if (self::$_layoutEditorEnabled === null) {
                    if (!XenForo_Application::isRegistered('session')) {
                        // no session yet...
                        return false;
                    }
                    $session = XenForo_Application::getSession();
                    self::$_layoutEditorEnabled = ($session->get('_WidgetFramework_layoutEditor') === true);

                    if (!self::$_layoutEditorEnabled
                        && !empty($_REQUEST['_layoutEditor'])
                    ) {
                        $visitor = XenForo_Visitor::getInstance();
                        if ($visitor->hasAdminPermission('style')) {
                            self::$_layoutEditorEnabled = true;
                        }
                    }
                }

                // use the cached value
                return self::$_layoutEditorEnabled;
        }

        return self::$_options->get('wf_' . $key);
    }

    public static function setIndexNodeId($nodeId)
    {
        $optionDw = XenForo_DataWriter::create('XenForo_DataWriter_Option');
        $optionDw->setExistingData('wf_indexNodeId');
        $optionDw->set('option_value', $nodeId);
        $optionDw->save();
    }

    public static function renderWidgetPages(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
    {
        /** @var WidgetFramework_Model_WidgetPage $widgetPageModel */
        $widgetPageModel = XenForo_Model::create('WidgetFramework_Model_WidgetPage');

        $widgetPages = $widgetPageModel->getList();
        $choices = array(0 => '');
        foreach ($widgetPages as $widgetPageId => $widgetPageTitle) {
            $choices[$widgetPageId] = $widgetPageTitle;
        }

        $editLink = $view->createTemplateObject('option_list_option_editlink', array(
            'preparedOption' => $preparedOption,
            'canEditOptionDefinition' => $canEdit
        ));

        return $view->createTemplateObject('option_list_option_select', array(
            'fieldPrefix' => $fieldPrefix,
            'listedFieldName' => $fieldPrefix . '_listed[]',
            'preparedOption' => $preparedOption,
            'formatParams' => $choices,
            'editLink' => $editLink,
        ));
    }

}
