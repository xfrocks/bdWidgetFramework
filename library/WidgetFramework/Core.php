<?php

class WidgetFramework_Core
{
    const PARAM_TO_BE_PROCESSED = '_WidgetFramework_toBeProcessed';
    const PARAM_POSITION_CODE = '_WidgetFramework_positionCode';
    const PARAM_IS_HOOK = '_WidgetFramework_isHook';
    const PARAM_IS_GROUP = '_WidgetFramework_isGroup';
    const PARAM_GROUP_ID = '_WidgetFramework_groupId';
    const PARAM_PARENT_GROUP_ID = '_WidgetFramework_parentGroupId';
    const PARAM_PARENT_TEMPLATE = '_WidgetFramework_parentTemplate';
    const PARAM_VIEW_OBJECT = '_WidgetFramework_viewObj';
    const PARAM_TEMPLATE_OBJECTS = '_WidgetFramework_templateObjects';
    const PARAM_CURRENT_WIDGET_ID = '_WidgetFramework_currentWidgetId';

    // these two are deprecated, use the _ID constant please
    const PARAM_GROUP_NAME = '_WidgetFramework_groupId';
    const PARAM_PARENT_GROUP_NAME = '_WidgetFramework_parentGroupId';

    const NO_VISITOR_PANEL_MARKUP = '<!-- no visitor panel please -->';
    const NO_VISITOR_PANEL_FLAG = 'WidgetFramework_WidgetRenderer_Empty.noVisitorPanel';

    protected static $_instance;
    protected static $_debug;
    protected static $_rendererInstances = array();

    protected $_renderers = array();
    protected $_widgetCount = 0;
    protected $_positions = array();
    protected $_templateForHooks = array();
    protected $_models = array();

    public function __construct()
    {
        $renderers = array();
        $this->_registerDefaultRenderers($renderers);
        XenForo_CodeEvent::fire('widget_framework_ready', array(&$renderers));
        foreach ($renderers as $renderer) {
            $this->_renderers[] = $renderer;
        }
    }

    protected function _registerDefaultRenderers(array &$renderers)
    {
        $renderers = array(
            'WidgetFramework_WidgetGroup',
            'WidgetFramework_WidgetRenderer_Birthday',
            'WidgetFramework_WidgetRenderer_Callback',
            'WidgetFramework_WidgetRenderer_CallbackWithoutWrapper',
            'WidgetFramework_WidgetRenderer_Empty',
            'WidgetFramework_WidgetRenderer_FacebookFacepile',
            'WidgetFramework_WidgetRenderer_FeedReader',
            'WidgetFramework_WidgetRenderer_Html',
            'WidgetFramework_WidgetRenderer_HtmlWithoutWrapper',
            'WidgetFramework_WidgetRenderer_OnlineStaff',
            'WidgetFramework_WidgetRenderer_OnlineUsers',
            'WidgetFramework_WidgetRenderer_Poll',
            'WidgetFramework_WidgetRenderer_ProfilePosts',
            'WidgetFramework_WidgetRenderer_RecentStatus',
            'WidgetFramework_WidgetRenderer_ShareThisPage',
            'WidgetFramework_WidgetRenderer_Stats',
            'WidgetFramework_WidgetRenderer_Template',
            'WidgetFramework_WidgetRenderer_TemplateWithoutWrapper',
            'WidgetFramework_WidgetRenderer_Threads',
            'WidgetFramework_WidgetRenderer_Users',
            'WidgetFramework_WidgetRenderer_UsersFind',
            'WidgetFramework_WidgetRenderer_UsersStaff',
            'WidgetFramework_WidgetRenderer_VisitorPanel',
        );

        // since 2.2
        if (self::xfrmFound()) {
            // XFRM is installed
            $renderers[] = 'WidgetFramework_WidgetRenderer_XFRM_Resources';
        }

        // since 2.6.0
        if (self::xfmgFound()) {
            $renderers[] = 'WidgetFramework_WidgetRenderer_XFMG_Albums';
            $renderers[] = 'WidgetFramework_WidgetRenderer_XFMG_Comments';
            $renderers[] = 'WidgetFramework_WidgetRenderer_XFMG_Contributors';
            $renderers[] = 'WidgetFramework_WidgetRenderer_XFMG_Media';
            $renderers[] = 'WidgetFramework_WidgetRenderer_XFMG_Statistics';
        }
    }

    public function bootstrap()
    {
        if (defined('WIDGET_FRAMEWORK_LOADED')) {
            return;
        }

        if (WidgetFramework_Option::get('layoutEditorEnabled')) {
            $globalWidgets = $this->_getModelWidget()->getWidgets(array(
                'widget_page_id' => 0
            ));
        } else {
            $globalWidgets = $this->_getModelWidget()->getCachedWidgets();
        }

        $this->addWidgets($globalWidgets);

        // sondh@2013-04-02
        // detect if we are in debug mode
        // previously, put WF in debug mode when XF is in debug mode
        // it's no longer the case now, we will look for wfDebug flag in config.php
        $wfDebug = XenForo_Application::getConfig()->get('wfDebug');
        self::$_debug = !empty($wfDebug);

        define('WIDGET_FRAMEWORK_LOADED', 1);
    }

    public function shutdown()
    {
        // shutdown stuff?
    }

    public function getModelFromCache($class)
    {
        if (empty($this->_models[$class])) {
            $this->_models[$class] = XenForo_Model::create($class);
        }

        return $this->_models[$class];
    }

    public function addRenderer($renderer)
    {
        if (!in_array($renderer, $this->_renderers, true)) {
            $this->_renderers[] = $renderer;
        }
    }

    public function addWidgets(array $widgets)
    {
        $this->_widgetCount += count($widgets);

        $added = WidgetFramework_Helper_Sort::addWidgetToPositions($this->_positions, $widgets);

        foreach ($added['positionCodes'] as $positionCode) {
            $positionRef =& $this->_positions[$positionCode];

            foreach ($positionRef['widgetsByIds'] as $widgetId => &$widgetRef) {
                if (!empty($widgetRef['template_for_hooks'])) {
                    foreach ($widgetRef['template_for_hooks'] as $hookPositionCode => $templateForHooks) {
                        foreach ($templateForHooks as $templateName) {
                            if (!isset($this->_templateForHooks[$templateName])) {
                                $this->_templateForHooks[$templateName] = array();
                            }

                            if (!isset($this->_templateForHooks[$templateName][$hookPositionCode])) {
                                $this->_templateForHooks[$templateName][$hookPositionCode] = 1;
                            } else {
                                $this->_templateForHooks[$templateName][$hookPositionCode]++;
                            }
                        }
                    }
                }
            }
        }
    }

    public function removeAllWidgets()
    {
        $this->_widgetCount = 0;
        $this->_positions = array();
        $this->_templateForHooks = array();
    }

    public function prepareWidgetsFor($templateName, array $params, XenForo_Template_Abstract $template)
    {
        if ($this->_isIgnoredTemplate($templateName, $params)) {
            return false;
        }

        $this->_prepareWidgetsFor($templateName, $params, $template);

        return true;
    }

    public function prepareWidgetsForHooksIn($templateName, array $params, XenForo_Template_Abstract $template)
    {
        if (isset($this->_templateForHooks[$templateName])) {
            foreach ($this->_templateForHooks[$templateName] as $hookPositionCode => $count) {
                $this->_prepareWidgetsFor($hookPositionCode, $params, $template);
            }
        }
    }

    public function prepareWidgetsForHook($hookName, array $params, XenForo_Template_Abstract $template)
    {
        $this->_prepareWidgetsFor('hook:' . $hookName, $params, $template);
    }

    protected function _prepareWidgetsFor($positionCode, array $params, XenForo_Template_Abstract $template)
    {
        if (isset($this->_positions[$positionCode])) {
            if (!empty($this->_positions[$positionCode]['prepared'])) {
                // prepared
                return true;
            }
        } else {
            $this->_positions[$positionCode] = array();
        }

        $positionRef = &$this->_positions[$positionCode];
        $positionRef['prepared'] = true;

        if (substr($positionCode, 0, 5) !== 'hook:'
            && !empty($this->_positions['all']['widgets'])
        ) {
            // only append `all` widgets for template position code
            $widgetsFromAll = array();

            foreach ($this->_positions['all']['widgets'] as $_widget) {
                $this->_prepareWidgetsFor_prepareWidgetsFromAll($widgetsFromAll, $_widget, $positionCode);
            }

            $this->addWidgets($widgetsFromAll);
        }

        if (!empty($positionRef['widgets'])) {
            $widgetParams = $this->_prepareWidgetParams($params);

            foreach ($positionRef['widgets'] as &$widgetRef) {
                $renderer = self::getRenderer($widgetRef['class'], false);
                if ($renderer) {
                    $renderer->prepare($widgetRef, $positionCode, $widgetParams, $template);
                }
            }
        }

        return true;
    }

    protected function _prepareWidgetsFor_prepareWidgetsFromAll(array &$preparedRef, array $_widget, $positionCode)
    {
        if (isset($preparedRef[$_widget['widget_id']])) {
            return;
        }

        $preparedRef[$_widget['widget_id']] = $_widget;
        $preparedRef[$_widget['widget_id']]['position'] = $positionCode;
        $preparedRef[$_widget['widget_id']]['positionCodes'] = array($positionCode);

        if (!empty($_widget['widgets'])) {
            foreach ($_widget['widgets'] as $subWidget) {
                $this->_prepareWidgetsFor_prepareWidgetsFromAll($preparedRef, $subWidget, $positionCode);
            }
        }
    }

    protected function _prepareWidgetParams(array $params)
    {
        if (isset($params[self::PARAM_TEMPLATE_OBJECTS])) {
            // this is params array from page container
            if (isset($params['contentTemplate'])
                && isset($params[self::PARAM_TEMPLATE_OBJECTS][$params['contentTemplate']])
            ) {
                // found content template params, merge it
                /** @var XenForo_Template_Abstract $templateObj */
                $templateObj = $params[self::PARAM_TEMPLATE_OBJECTS][$params['contentTemplate']];
                $params = array_merge($templateObj->getParams(), $params);
            }
        }

        if (!empty($params[self::PARAM_IS_HOOK])) {
            $params['_classSection'] = 'section sectionMain widget-container act-as-sidebar sidebar';
            $params['_classInnerSection'] = 'widget hook-widget';
        } else {
            $params['_classSection'] = 'section';
            $params['_classInnerSection'] = 'secondaryContent widget sidebar-widget';
        }

        return $params;
    }

    public function renderWidgetsFor(
        $templateName,
        array $params,
        XenForo_Template_Abstract $template,
        array &$containerData
    ) {
        if ($this->_isIgnoredTemplate($templateName, $params)) {
            return false;
        }

        $originalHtml = isset($containerData['sidebar']) ? $containerData['sidebar'] : '';

        $html = $this->_renderWidgetsFor($templateName,
            array_merge($params, array(self::PARAM_POSITION_CODE => $templateName)), $template, $originalHtml);

        if (defined(self::NO_VISITOR_PANEL_FLAG)) {
            // the flag is used to avoid string searching as much as possible
            // the string search is also required to confirm the noVisitorPanel request
            $count = 0;
            $html = str_replace(self::NO_VISITOR_PANEL_MARKUP, '', $html, $count);

            if ($count > 0) {
                $containerData['noVisitorPanel'] = true;
            }
        }

        if ($html !== $originalHtml) {
            $containerData['sidebar'] = utf8_trim($html);

            if (!empty($containerData['sidebar'])
                && self::debugMode()
            ) {
                $containerData['sidebar'] .= sprintf('<div>Widget Framework is in debug mode<br/>'
                    . 'Renderers: %d<br/>Widgets: %d<br/></div>',
                    count($this->_renderers), $this->_widgetCount);
            }
        }

        return true;
    }

    public function renderWidgetsForHook($hookName, array $hookParams, XenForo_Template_Abstract $template, &$hookHtml)
    {
        $hookParams[self::PARAM_PARENT_TEMPLATE] = $template->getTemplateName();
        $hookParams[self::PARAM_POSITION_CODE] = 'hook:' . $hookName;
        $hookParams[self::PARAM_IS_HOOK] = true;

        // sondh@2013-04-02
        // merge hook params with template's params
        $hookParams = array_merge($template->getParams(), $hookParams);

        $hookHtml = $this->_renderWidgetsFor('hook:' . $hookName, $hookParams, $template, $hookHtml);

        return true;
    }

    protected function _renderWidgetsFor($positionCode, array $params, XenForo_Template_Abstract $template, $html)
    {
        static $renderedPositions = array();
        $renderArea = false;
        $renderSimpleArea = false;

        if (WidgetFramework_Option::get('layoutEditorEnabled')) {
            if (isset($renderedPositions[$positionCode])) {
                // during layout editor, only run through each position once
                return $html;
            }
            $renderedPositions[$positionCode] = true;

            $saveParams = array(
                'position' => $positionCode,
                'group_id' => 0,
            );
            if (!empty($params[self::PARAM_IS_HOOK])) {
                // hook position, only render for some hooks
                if ($positionCode === 'hook:wf_widget_page_contents') {
                    $saveParams['widget_page_id'] = $params['widgetPage']['node_id'];
                    $renderArea = true;
                } elseif (in_array($positionCode, array(
                    'hook:ad_above_content',
                    'hook:ad_below_content',
                ), true)) {
                    $renderArea = true;
                } elseif ($template->getTemplateName() !== 'PAGE_CONTAINER') {
                    // render simple area for content template hooks
                    $renderArea = true;
                    $renderSimpleArea = true;
                }
            } else {
                // page position, always render for sidebar
                $renderArea = true;
                if ($positionCode == 'wf_widget_page') {
                    $saveParams['widget_page_id'] = $params['widgetPage']['node_id'];
                }
            }
        }

        if (empty($this->_positions[$positionCode]['widgets'])) {
            if ($renderArea) {
                $this->_positions[$positionCode]['widgets'] = array();
                $this->_positions[$positionCode]['prepared'] = true;
            } else {
                // stop rendering if no widget configured for this position
                return $html;
            }
        } else {
            if (WidgetFramework_Option::get('layoutEditorEnabled')) {
                $renderArea = true;
            }
            $renderSimpleArea = false;
        }

        $positionRef = &$this->_positions[$positionCode];
        if (empty($positionRef['prepared'])) {
            // stop rendering if not prepared
            return $html;
        }

        $html = trim($html);
        if ($renderArea && !$renderSimpleArea && !empty($html)) {
            $html = WidgetFramework_Helper_String::createArrayOfStrings(array(
                '<div title="',
                new XenForo_Phrase('wf_original_contents'),
                '" class="original-contents Tooltip">',
                $html,
                '</div>',
            ));
        }

        $widgetParams = $this->_prepareWidgetParams($params);
        $this->renderWidgets($positionRef['widgets'], $positionCode, $widgetParams, $template, $html);

        if ($renderArea) {
            $conditionalParams = WidgetFramework_Template_Helper_Layout::prepareConditionalParams($widgetParams);
            if (!empty($saveParams['widget_page_id'])
                && !empty($conditionalParams['widgetPage'])
            ) {
                unset($conditionalParams['widgetPage']);
            }

            $areaParams = array(
                '_areaPositionCode' => $positionCode,
                '_areaConditionalParams' => $conditionalParams,
                '_areaSaveParams' => $saveParams,
                '_areaHtml' => $html,
            );

            $areaTemplate = ($renderSimpleArea ? 'wf_layout_editor_area_simple' : 'wf_layout_editor_area');

            $html = $template->create($areaTemplate, $areaParams);
        }

        return $html;
    }

    public function renderWidgets(
        array &$widgetsRef,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $template,
        &$html
    ) {
        foreach ($widgetsRef as &$widgetRef) {
            $widgetRef['_runtime']['html'] = $this->renderWidget($widgetRef, $positionCode, $params, $template, $html);

            $wrapperTemplateName = '';
            if (WidgetFramework_Option::get('layoutEditorEnabled')
                && $widgetRef['class'] !== 'WidgetFramework_WidgetGroup'
            ) {
                $wrapperTemplateName = 'wf_layout_editor_widget_wrapper';
                $params['_conditionalParams'] = WidgetFramework_Template_Helper_Layout::prepareConditionalParams($params);
            } elseif (!empty($widgetRef['_runtime']['useWrapper'])) {
                $wrapperTemplateName = 'wf_widget_wrapper';
            }

            if ($wrapperTemplateName !== '') {
                $wrapperTemplate = $template->create($wrapperTemplateName, $params);
                $wrapperTemplate->setParam('widget', $widgetRef);

                $widgetHtml = $wrapperTemplate;
            } else {
                $widgetHtml = $widgetRef['_runtime']['html'];
            }

            if (empty($html)) {
                $html = $widgetHtml;
            } elseif ($widgetRef['display_order'] >= 0) {
                $html = WidgetFramework_Helper_String::createArrayOfStrings(array($html, $widgetHtml));
            } else {
                $html = WidgetFramework_Helper_String::createArrayOfStrings(array($widgetHtml, $html));
            }
        }
    }

    public function renderWidget(
        array &$widgetRef,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $template,
        &$html
    ) {
        $widgetHtml = '';
        $renderer = self::getRenderer($widgetRef['class'], false);

        if (!empty($renderer)) {
            $widgetRef['_runtime']['useWrapper'] = $renderer->useWrapper($widgetRef);
            $widgetRef['_runtime']['title']
                = WidgetFramework_Helper_String::createWidgetTitleDelayed($renderer, $widgetRef);
            $widgetHtml = $renderer->render($widgetRef, $positionCode, $params, $template, $html);

            // extra-preparation (this will be run every time the widget is ready to display)
            // this method can change the final html in some way if it needs to do that
            // the changed html won't be store in the cache (caching is processed inside
            // WidgetFramework_Renderer::render())
            $widgetRef['_runtime']['extraData'] = $renderer->extraPrepare($widgetRef, $widgetHtml);
        } elseif (WidgetFramework_Option::get('layoutEditorEnabled')) {
            $widgetHtml = new XenForo_Phrase('wf_layout_editor_widget_no_renderer');
        }

        return $widgetHtml;
    }

    public function getWidgetsAtPosition($positionCode)
    {
        if (!isset($this->_positions[$positionCode])) {
            return array();
        }

        return $this->_positions[$positionCode]['widgets'];
    }

    public function getRenderedHtmlByWidgetId($widgetId)
    {
        foreach ($this->_positions as &$positionRef) {
            if (!empty($positionRef['html'])) {
                foreach ($positionRef['html'] as $_widgetId => &$widgetHtml) {
                    if ($_widgetId == $widgetId) {
                        return $widgetHtml;
                    }
                }
            }
        }

        return '';
    }

    /**
     * @return WidgetFramework_Model_Cache
     */
    protected function _getModelCache()
    {
        return $this->getModelFromCache('WidgetFramework_Model_Cache');
    }

    /**
     * @return WidgetFramework_Model_Widget
     */
    protected function _getModelWidget()
    {
        return $this->getModelFromCache('WidgetFramework_Model_Widget');
    }

    protected function _isIgnoredTemplate($templateName, array $templateParams)
    {
        if (empty($templateParams[WidgetFramework_Core::PARAM_TO_BE_PROCESSED])
            || $templateParams[WidgetFramework_Core::PARAM_TO_BE_PROCESSED] != $templateName
        ) {
            return true;
        }

        return false;
    }

    public static function clearCachedWidgetById($widgetId)
    {
        self::getInstance()->_getModelCache()->invalidateCache($widgetId);
    }

    public static function clearCachedWidgetByClass($class)
    {
        if (self::debugMode()) {
            XenForo_Error::logError(sprintf('%s has been deprecated.', __METHOD__));
        }

        $instance = self::getInstance();

        $widgets = $instance->_getModelWidget()->getWidgets(array(
            'class' => $class,
        ));
        $cacheModel = $instance->_getModelCache();

        foreach ($widgets as $widget) {
            $cacheModel->invalidateCache($widget['widget_id']);
        }
    }

    /**
     * @return WidgetFramework_Core
     */
    public static function getInstance()
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * @param string $class
     * @param bool $throw
     * @throws Exception
     * @return WidgetFramework_WidgetRenderer
     */
    public static function getRenderer($class, $throw = true)
    {
        try {
            if (!isset(self::$_rendererInstances[$class])) {
                self::$_rendererInstances[$class] = WidgetFramework_WidgetRenderer::create($class);
            }
            return self::$_rendererInstances[$class];
        } catch (Exception $e) {
            if ($throw) {
                throw $e;
            }
        }

        return null;
    }

    public static function getRenderers()
    {
        return self::getInstance()->_renderers;
    }

    public static function debugMode()
    {
        return self::$_debug;
    }

    public static function xfrmFound()
    {
        /** @var XenForo_Model_Moderator $moderatorModel */
        $moderatorModel = XenForo_Model::create('XenForo_Model_Moderator');
        $gmigi = $moderatorModel->getGeneralModeratorInterfaceGroupIds();
        return in_array('resourceModeratorPermissions', $gmigi);
    }

    public static function xfmgFound()
    {
        if (XenForo_Application::$versionId < 1030070) {
            // the add-on itself requires XenForo 1.3.0+
            return false;
        }

        $addOns = XenForo_Application::get('addOns');
        return isset($addOns['XenGallery']);
    }

    public static function contentTaggingFound()
    {
        return XenForo_Application::$versionId > 1050000;
    }
}
