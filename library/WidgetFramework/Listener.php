<?php

class WidgetFramework_Listener
{
    /**
     * @var XenForo_Dependencies_Abstract
     */
    public static $dependencies = null;

    /**
     * @var XenForo_FrontController
     */
    public static $fc = null;

    /**
     * @var XenForo_ViewRenderer_Abstract
     */
    public static $viewRenderer = null;

    protected static $_navigationTabsForums = '';
    protected static $_saveLayoutEditorRendered = false;
    protected static $_layoutEditorRendered = array();

    public static function init_dependencies(
        XenForo_Dependencies_Abstract $dependencies,
        /** @noinspection PhpUnusedParameterInspection */
        array $data
    ) {
        self::$dependencies = $dependencies;

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

        WidgetFramework_ShippableHelper_Updater::onInitDependencies($dependencies,
            WidgetFramework_Option::UPDATER_URL, 'widget_framework');
    }

    public static function navigation_tabs(
        array &$extraTabs,
        /** @noinspection PhpUnusedParameterInspection */
        $selectedTabId
    ) {
        $indexNodeId = WidgetFramework_Option::get('indexNodeId');

        if ($indexNodeId > 0
            && XenForo_Template_Helper_Core::styleProperty('wf_homeNavTab')
        ) {
            $tabId = WidgetFramework_Option::get('indexTabId');

            $extraTabs[$tabId] = array(
                'title' => new XenForo_Phrase('wf_home_navtab'),
                'href' => XenForo_Link::buildPublicLink('full:widget-page-index'),
                'position' => 'home',

                'linksTemplate' => 'wf_home_navtab_links',
                'indexNodeId' => $indexNodeId,
                'childNodes' => WidgetFramework_Helper_Index::getChildNodes(),
            );
        }
    }

    public static function template_create(&$templateName, array &$params, XenForo_Template_Abstract $template)
    {
        if (defined('WIDGET_FRAMEWORK_LOADED')) {
            WidgetFramework_Core::getInstance()->prepareWidgetsFor($templateName, $params, $template);

            WidgetFramework_Core::getInstance()->prepareWidgetsForHooksIn($templateName, $params, $template);

            if ($templateName === 'PAGE_CONTAINER') {
                $template->preloadTemplate('wf_hook_footer');
                $template->preloadTemplate('wf_hook_moderator_bar');

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
                $rendered = WidgetFramework_Core::getInstance()->renderWidgetsFor(
                    $templateName, $template->getParams(), $template, $containerData);

                if ($rendered) {
                    if (!isset($containerData[WidgetFramework_Core::PARAM_TEMPLATE_OBJECTS])) {
                        $containerData[WidgetFramework_Core::PARAM_TEMPLATE_OBJECTS] = array();
                    }
                    $containerData[WidgetFramework_Core::PARAM_TEMPLATE_OBJECTS][$templateName] = $template;
                }
            }

            $currentWidgetId = $template->getParam(WidgetFramework_Core::PARAM_CURRENT_WIDGET_ID);
            if ($currentWidgetId > 0) {
                WidgetFramework_WidgetRenderer::setContainerData($currentWidgetId, $containerData);
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
                        self::$_layoutEditorRendered[$widget['widget_id']] = $output;
                        break;
                }
            }

            if ($templateName === 'PAGE_CONTAINER') {
                WidgetFramework_Template_Extended::WidgetFramework_processLateExtraData($output, $template);

                if (!empty(self::$_navigationTabsForums)) {
                    $output = str_replace('<!-- navigation_tabs_forums for wf_home_navtab_links -->',
                        self::$_navigationTabsForums, $output);
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

            if ($hookName == 'moderator_bar') {
                $ourParams = $template->getParams();
                $ourParams['hasAdminPermStyle'] = XenForo_Visitor::getInstance()->hasAdminPermission('style');

                $ourTemplate = $template->create('wf_hook_moderator_bar', $ourParams);
                $contents .= $ourTemplate->render();
            } elseif ($hookName === 'footer') {
                $ourTemplate = $template->create('wf_hook_footer', $template->getParams());
                $contents .= $ourTemplate->render();
            } elseif (in_array($hookName, array(
                'page_container_breadcrumb_top',
                'page_container_content_title_bar'
            ))) {
                if (!!$template->getParam('widgetPageOptionsBreakContainer')) {
                    $contents = '';
                }
            }
        }
    }

    public static function template_hook_navigation_tabs_forums(
        /** @noinspection PhpUnusedParameterInspection */
        $hookName,
        &$contents,
        array $hookParams,
        XenForo_Template_Abstract $template
    ) {
        self::$_navigationTabsForums = $contents;
    }

    public static function init_router_public(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_Dependencies_Abstract $dependencies,
        XenForo_Router $router
    ) {
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
        /** @noinspection PhpUnusedParameterInspection */
        array &$containerParams
    ) {
        self::$fc = $fc;
        self::$viewRenderer = $viewRenderer;

        if ($fc->getDependencies() instanceof XenForo_Dependencies_Public) {
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

            if (!empty($_REQUEST['_getRender']) && !empty($_REQUEST['_renderedIds'])) {
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

    public static function load_class($class, array &$extend)
    {
        static $classesNeedsExtending = array(
            'XenForo_BbCode_Formatter_Base',
            'XenForo_BbCode_Formatter_HtmlEmail',
            'XenForo_BbCode_Formatter_Text',

            'XenForo_ControllerPublic_Forum',
            'XenForo_ControllerPublic_Index',
            'XenForo_ControllerPublic_Misc',
            'XenForo_ControllerPublic_Thread',

            'XenForo_Model_Permission',
            'XenForo_Model_ProfilePost',
            'XenForo_Model_Thread',
            'XenForo_Model_User',

            'XenForo_Route_Prefix_Index',

            'bdCache_Model_Cache',
        );

        if (in_array($class, $classesNeedsExtending)) {
            $extend[] = 'WidgetFramework_' . $class;
        }
    }

    public static function load_class_view($class, array &$extend)
    {
        static $extended1 = false;
        static $extended2 = false;

        if (defined('WIDGET_FRAMEWORK_LOADED')
            && !empty($class)
        ) {
            // check for empty($class) to avoid a bug with XenForo 1.2.0
            // http://xenforo.com/community/threads/57064/

            if (empty($extended1)) {
                $extend[] = 'WidgetFramework_XenForo_View1';
                $extended1 = $class;
            } elseif (empty($extended2)) {
                $extend[] = 'WidgetFramework_XenForo_View2';
                $extended2 = $class;
            }
        }

        if ($class === 'XenForo_ViewAdmin_StyleProperty_List') {
            $extend[] = 'WidgetFramework_' . $class;
        }
    }

    public static function file_health_check(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_ControllerAdmin_Abstract $controller,
        array &$hashes
    ) {
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

}
