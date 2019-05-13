<?php

class WidgetFramework_Listener
{
    const UPDATER_URL = 'https://xfrocks.com/api/index.php?updater';

    /**
     * @var XenForo_ViewRenderer_HtmlPublic
     */
    public static $viewRenderer = null;

    /**
     * @var bool
     */
    public static $renderSidebarWidgets = false;

    protected static $_navigationTabsForums = '';
    protected static $_saveLayoutEditorRendered = false;
    protected static $_layoutEditorRendered = array();

    public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
    {
        XenForo_Template_Helper_Core::$helperCallbacks[strtolower('WidgetFramework_snippet')] = array(
            'WidgetFramework_Template_Helper_Core',
            'snippet'
        );

        XenForo_Template_Helper_Core::$helperCallbacks[strtolower('WidgetFramework_canToggle')] = array(
            'WidgetFramework_Template_Helper_Core',
            'canToggle'
        );

        XenForo_Template_Helper_Core::$helperCallbacks[strtolower('WidgetFramework_getOption')] = array(
            'WidgetFramework_Option',
            'get'
        );

        $indexNodeId = WidgetFramework_Option::get('indexNodeId');
        if ($indexNodeId > 0) {
            WidgetFramework_Helper_Index::setup();
        }

        if (isset($data['routesAdmin'])) {
            WidgetFramework_ShippableHelper_Updater::onInitDependencies(
                $dependencies,
                self::UPDATER_URL,
                'widget_framework'
            );
        }
    }

    public static function navigation_tabs(array &$extraTabs, $selectedTabId)
    {
        $indexNodeId = WidgetFramework_Option::get('indexNodeId');

        if ($indexNodeId > 0
            && XenForo_Template_Helper_Core::styleProperty('wf_homeNavTab')
        ) {
            $tabId = WidgetFramework_Option::get('indexTabId');

            $linksTemplate = 'wf_home_navtab_links';
            if (!XenForo_Template_Helper_Core::styleProperty('wf_homeLinksChildNodes')
                && !XenForo_Template_Helper_Core::styleProperty('wf_homeLinksForums')
            ) {
                $linksTemplate = '';
            }

            $extraTabs[$tabId] = array(
                'title' => new XenForo_Phrase('wf_home_navtab'),
                'href' => XenForo_Link::buildPublicLink('full:widget-page-index'),
                'position' => 'home',

                'linksTemplate' => $linksTemplate,
                'indexNodeId' => $indexNodeId,
                'childNodes' => WidgetFramework_Helper_Index::getChildNodes(),
            );
        }
    }

    public static function template_create(&$templateName, array &$params, XenForo_Template_Abstract $template)
    {
        if (defined('WIDGET_FRAMEWORK_LOADED')) {
            $core = WidgetFramework_Core::getInstance();

            if (self::$renderSidebarWidgets) {
                $core->prepareWidgetsFor($templateName, $params, $template);
            }

            $core->prepareWidgetsForHooksIn($templateName, $params, $template);

            if ($templateName === 'PAGE_CONTAINER') {
                if (WidgetFramework_Option::get('indexNodeId')) {
                    // preload our links template for performance
                    $template->preloadTemplate('wf_home_navtab_links');
                }

                WidgetFramework_Template_Extended::WidgetFramework_setPageContainer($template);

                if (isset($params['contentTemplate'])
                    && $params['contentTemplate'] === 'wf_widget_page'
                    && empty($params['selectedTabId'])
                ) {
                    // make sure a navtab is selected if user is viewing our (as index) widget page
                    if (!XenForo_Template_Helper_Core::styleProperty('wf_homeNavTab')) {
                        // oh, our "Home" navtab has been disable...
                        // try something from $params['tabs'] OR $params['extraTabs']
                        if (isset($params['tabs'])
                            && isset($params['extraTabs'])
                        ) {
                            WidgetFramework_Helper_Index::setNavtabSelected($params['tabs'], $params['extraTabs']);
                        }
                    }
                }
            }
        }
    }

    public static function template_post_render(
        $templateName,
        &$output,
        array &$containerData,
        XenForo_Template_Abstract $template
    ) {
        if (defined('WIDGET_FRAMEWORK_LOADED')) {
            if (!preg_match('#^wf_.+_wrapper$#', $templateName)) {
                $rendered = false;

                if (self::$renderSidebarWidgets) {
                    $rendered = WidgetFramework_Core::getInstance()->renderWidgetsFor(
                        $templateName,
                        $template->getParams(),
                        $template,
                        $containerData
                    );
                }

                if ($rendered) {
                    if (!isset($containerData[WidgetFramework_Core::PARAM_TEMPLATE_OBJECTS])) {
                        $containerData[WidgetFramework_Core::PARAM_TEMPLATE_OBJECTS] = array();
                    }
                    $containerData[WidgetFramework_Core::PARAM_TEMPLATE_OBJECTS][$templateName] = $template;
                }
            }

            if (self::$_saveLayoutEditorRendered) {
                switch ($templateName) {
                    case 'wf_widget_group_wrapper':
                    case 'wf_layout_editor_widget_group_wrapper':
                        $group = $template->getParam('group');
                        if (!empty($group)) {
                            self::$_layoutEditorRendered[$group['widget_id']] = $output;

                            $widgets = $template->getParam('widgets');
                            foreach ($widgets as $widget) {
                                self::$_layoutEditorRendered[$widget['widget_id']] = array('groupId' => $group['widget_id']);
                            }
                        }
                        break;
                    case 'wf_widget_wrapper':
                    case 'wf_layout_editor_widget_wrapper':
                    case 'wf_layout_editor_widget':
                        $widget = $template->getParam('widget');
                        if (!empty($widget['_runtime']['html'])
                            && $templateName !== 'wf_layout_editor_widget_wrapper'
                        ) {
                            self::$_layoutEditorRendered[$widget['widget_id']] = $widget['_runtime']['html'];
                        } else {
                            self::$_layoutEditorRendered[$widget['widget_id']] = $output;
                        }
                        break;
                }
            }

            if ($templateName === 'PAGE_CONTAINER') {
                WidgetFramework_Template_Extended::WidgetFramework_processLateExtraData($output, $template);

                if (!empty(self::$_navigationTabsForums)) {
                    $output = str_replace(
                        '<!-- navigation_tabs_forums for wf_home_navtab_links -->',
                        self::$_navigationTabsForums,
                        $output
                    );
                }
            }
        }
    }

    public static function template_hook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
    {
        if (defined('WIDGET_FRAMEWORK_LOADED')) {
            $renderWidgets = true;

            if ($template->getTemplateName() == 'PAGE_CONTAINER'
                && $template->getParam('contentTemplate') == 'wf_widget_page'
                && WidgetFramework_Option::get('layoutEditorEnabled')
                && $hookName != 'wf_widget_page_contents'
            ) {
                $renderWidgets = false;
            }

            if ($renderWidgets) {
                WidgetFramework_Core::getInstance()->renderWidgetsForHook($hookName, $hookParams, $template, $contents);
            }

            if (in_array($hookName, array(
                'page_container_breadcrumb_bottom',
                'page_container_breadcrumb_top',
                'page_container_content_title_bar',
            ), true)) {
                if (!!$template->getParam('widgetPageOptionsBreakContainer')) {
                    $contents = '';
                }
            }
        }
    }

    public static function template_hook_navigation_tabs_forums(
        $hookName,
        &$contents,
        array $hookParams,
        XenForo_Template_Abstract $template
    ) {
        self::$_navigationTabsForums = $contents;
    }

    public static function init_router_public(XenForo_Dependencies_Abstract $dependencies, XenForo_Router $router)
    {
        if (WidgetFramework_Option::get('indexNodeId') > 0) {
            // one of our widget pages was selected as the index page
            // modify the router rules to serve http://domain.com/xenforo/page-x urls
            $rules = $router->getRules();
            $router->resetRules();

            // insert our filter as the first rule
            $router->addRule(new WidgetFramework_Route_Filter_PageX(), 'WidgetFramework_Route_Filter_PageX');

            foreach ($rules as $ruleName => $rule) {
                $router->addRule($rule, $ruleName);
            }
        }
    }

    public static function front_controller_pre_view(
        XenForo_FrontController $fc,
        XenForo_ControllerResponse_Abstract &$controllerResponse,
        XenForo_ViewRenderer_Abstract &$viewRenderer,
        array &$containerParams
    ) {
        self::$renderSidebarWidgets = true;

        if ($fc->getDependencies() instanceof XenForo_Dependencies_Public) {
            self::$viewRenderer = $viewRenderer;
            WidgetFramework_Core::getInstance()->bootstrap();
        }

        if (defined('WIDGET_FRAMEWORK_LOADED')) {
            if ($controllerResponse instanceof XenForo_ControllerResponse_View) {
                self::_markTemplateToProcess($controllerResponse);
            }

            if (WidgetFramework_Option::get('layoutEditorEnabled')) {
                self::saveLayoutEditorRendered(true);
            }
        }
    }

    public static function front_controller_post_view(XenForo_FrontController $fc, &$output)
    {
        if (defined('WIDGET_FRAMEWORK_LOADED')) {
            $core = WidgetFramework_Core::getInstance();

            if (!$fc->showDebugOutput() &&
                !empty($_REQUEST['_getRender']) &&
                !empty($_REQUEST['_renderedIds'])
            ) {
                $controllerResponse = new XenForo_ControllerResponse_View();
                $controllerResponse->viewName = 'WidgetFramework_ViewPublic_Widget_Render';
                $controllerResponse->params = $_REQUEST;

                $viewRenderer = $fc->getDependencies()->getViewRenderer($fc->getResponse(), 'json', $fc->getRequest());
                $output = $fc->renderView($controllerResponse, $viewRenderer);
            }

            if (WidgetFramework_Option::get('layoutEditorEnabled')) {
                foreach (array_keys(self::$_layoutEditorRendered) as $key) {
                    $fc->getResponse()->setHeader('X-Widget-Framework-Rendered', $key, false);
                }
            }

            $core->shutdown();
        }
    }

    public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
    {
        $hashes += WidgetFramework_FileSums::getHashes();
    }

    public static function saveLayoutEditorRendered($enabled)
    {
        self::$_saveLayoutEditorRendered = $enabled;
    }

    public static function getLayoutEditorRendered($renderedId)
    {
        if (isset(self::$_layoutEditorRendered[$renderedId])) {
            $rendered = self::$_layoutEditorRendered[$renderedId];

            if (is_string($rendered)) {
                return array($renderedId, $rendered);
            } elseif (is_array($rendered)) {
                if (isset($rendered['groupId'])) {
                    return self::getLayoutEditorRendered($rendered['groupId']);
                }
            }
        }

        return array(0, '');
    }

    protected static function _markTemplateToProcess(XenForo_ControllerResponse_View $view)
    {
        if (!empty($view->templateName)) {
            $view->params[WidgetFramework_Core::PARAM_TO_BE_PROCESSED] = $view->templateName;
        }

        if (!empty($view->subView)) {
            // also mark any direct sub view to be processed
            self::_markTemplateToProcess($view->subView);
        }
    }

    public static function load_class_bdCache_Model_Cache($class, array &$extend)
    {
        if ($class === 'bdCache_Model_Cache') {
            $extend[] = 'WidgetFramework_bdCache_Model_Cache';
        }
    }

    public static function load_class_XenForo_BbCode_Formatter_Base($class, array &$extend)
    {
        if ($class === 'XenForo_BbCode_Formatter_Base') {
            $extend[] = 'WidgetFramework_XenForo_BbCode_Formatter_Base';
        }
    }

    public static function load_class_XenForo_BbCode_Formatter_HtmlEmail($class, array &$extend)
    {
        if ($class === 'XenForo_BbCode_Formatter_HtmlEmail') {
            $extend[] = 'WidgetFramework_XenForo_BbCode_Formatter_HtmlEmail';
        }
    }

    public static function load_class_XenForo_BbCode_Formatter_Text($class, array &$extend)
    {
        if ($class === 'XenForo_BbCode_Formatter_Text') {
            $extend[] = 'WidgetFramework_XenForo_BbCode_Formatter_Text';
        }
    }

    public static function load_class_XenForo_ControllerPublic_Misc($class, array &$extend)
    {
        if ($class === 'XenForo_ControllerPublic_Misc') {
            $extend[] = 'WidgetFramework_XenForo_ControllerPublic_Misc';
        }
    }

    public static function load_class_XenForo_ControllerPublic_Thread($class, array &$extend)
    {
        if ($class === 'XenForo_ControllerPublic_Thread') {
            $extend[] = 'WidgetFramework_XenForo_ControllerPublic_Thread';
        }
    }

    public static function load_class_XenForo_Model_Permission($class, array &$extend)
    {
        if ($class === 'XenForo_Model_Permission') {
            $extend[] = 'WidgetFramework_XenForo_Model_Permission';
        }
    }

    public static function load_class_XenForo_ViewAdmin_StyleProperty_List($class, array &$extend)
    {
        if ($class === 'XenForo_ViewAdmin_StyleProperty_List') {
            $extend[] = 'WidgetFramework_XenForo_ViewAdmin_StyleProperty_List';
        }
    }
}
