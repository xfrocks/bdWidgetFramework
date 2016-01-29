<?php

abstract class WidgetFramework_WidgetRenderer
{
    // these constants are kept here for compatibility only
    // please use constants from WidgetFramework_Core from now on
    const PARAM_TO_BE_PROCESSED = '_WidgetFramework_toBeProcessed';
    const PARAM_POSITION_CODE = '_WidgetFramework_positionCode';
    const PARAM_IS_HOOK = '_WidgetFramework_isHook';
    const PARAM_IS_GROUP = '_WidgetFramework_isGroup';
    const PARAM_GROUP_NAME = '_WidgetFramework_groupId';
    const PARAM_PARENT_GROUP_NAME = '_WidgetFramework_parentGroupId';
    const PARAM_PARENT_TEMPLATE = '_WidgetFramework_parentTemplate';
    const PARAM_VIEW_OBJECT = '_WidgetFramework_viewObj';
    const PARAM_TEMPLATE_OBJECTS = '_WidgetFramework_templateObjects';
    // please use constants from WidgetFramework_Core from now on

    /**
     * Required method: define basic configuration of the renderer.
     * Available configuration parameters:
     *    - name: The display name of the renderer
     *    - isHidden: Flag to hide the renderer when creating new widget
     *    - options: An array of renderer's options
     *    - useCache: Flag to determine the renderer can be cached or not
     *    - useUserCache: Flag to determine the renderer needs to be cached by an
     *                      user-basis. Internally, this is implemented by getting the current user
     *                      permission combination id (not the user id as normally expected). This is
     *                      done to make sure the cache is used effectively.
     *    - cacheSeconds: A numeric value to specify the maximum age of the cache (in seconds).
     *                      If the cache is too old, the widget will be rendered from scratch.
     *    - useWrapper: Flag to determine the widget should be wrapped with a wrapper
     *    - canAjaxLoad: Flag to determine the widget can be loaded via ajax
     */
    abstract protected function _getConfiguration();

    /**
     * Required method: get the template title of the options template (to be used in
     * AdminCP).
     * If this is not used, simply returns false.
     */
    abstract protected function _getOptionsTemplate();

    /**
     * Required method: get the template title of the render template (to be used in
     * front-end).
     *
     * @param array $widget
     * @param string $positionCode
     * @param array $params
     */
    abstract protected function _getRenderTemplate(array $widget, $positionCode, array $params);

    /**
     * Required method: prepare data or whatever to get the render template ready to
     * be rendered.
     *
     * @param array $widget
     * @param string $positionCode
     * @param array $params
     * @param XenForo_Template_Abstract $renderTemplateObject
     */
    abstract protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    );

    protected function _renderOptions(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_Template_Abstract $template
    ) {
        return true;
    }

    protected function _validateOptionValue($optionKey, &$optionValue)
    {
        if ($optionKey === 'cache_seconds') {
            if (!is_numeric($optionValue)) {
                $optionValue = '';
            } elseif ($optionValue < 0) {
                $optionValue = 0;
            }
        } elseif ($optionKey === 'conditional') {
            $raw = '';
            if (!empty($optionValue['raw'])) {
                $raw = $optionValue['raw'];
            }

            if (!empty($raw)) {
                $optionValue = array(
                    'raw' => $raw,
                    'parsed' => WidgetFramework_Helper_Conditional::parse($raw),
                );
            } else {
                $optionValue = array();
            }
        }

        return true;
    }

    protected function _prepare(
        /** @noinspection PhpUnusedParameterInspection */
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $template
    ) {
        return true;
    }

    protected function _getExtraDataLink(
        /** @noinspection PhpUnusedParameterInspection */
        array $widget
    ) {
        return false;
    }

    /**
     * Helper method to prepare source array for <xen:select /> or similar tags
     *
     * @param array $selected an array of selected values
     * @param bool $useSpecialForums flag to determine the usage of special forum
     * indicator
     * @return array
     */
    protected function _helperPrepareForumsOptionSource(array $selected = array(), $useSpecialForums = false)
    {
        $forums = array();
        /** @var XenForo_Model_Node $nodeModel */
        $nodeModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Node');
        $nodes = $nodeModel->getAllNodes();

        if ($useSpecialForums) {
            // new XenForo_Phrase('wf_current_forum')
            // new XenForo_Phrase('wf_current_forum_and_children')
            // new XenForo_Phrase('wf_parent_forum')
            // new XenForo_Phrase('wf_parent_forum_and_children')
            foreach (array(
                         self::FORUMS_OPTION_SPECIAL_CURRENT,
                         self::FORUMS_OPTION_SPECIAL_CURRENT_AND_CHILDREN,
                         self::FORUMS_OPTION_SPECIAL_PARENT,
                         self::FORUMS_OPTION_SPECIAL_PARENT_AND_CHILDREN,
                     ) as $specialId) {
                $forums[] = array(
                    'value' => $specialId,
                    'label' => new XenForo_Phrase(sprintf('wf_%s', $specialId)),
                    'selected' => in_array($specialId, $selected),
                );
            }
        }

        foreach ($nodes as $node) {
            if (in_array($node['node_type_id'], array(
                'Category',
                'LinkForum',
                'Page',
                'WF_WidgetPage'
            ))) {
                continue;
            }

            $forums[] = array(
                'value' => $node['node_id'],
                'label' => str_repeat('--', $node['depth']) . ' ' . $node['title'],
                'selected' => in_array($node['node_id'], $selected),
            );
        }

        return $forums;
    }

    /**
     * Helper method to look for special forum ids in an array of forum ids
     *
     * @param array $forumIds
     * @return bool
     */
    protected function _helperDetectSpecialForums($forumIds)
    {
        if (!is_array($forumIds)) {
            return false;
        }

        foreach ($forumIds as $forumId) {
            switch ($forumId) {
                case self::FORUMS_OPTION_SPECIAL_CURRENT:
                case self::FORUMS_OPTION_SPECIAL_CURRENT_AND_CHILDREN:
                case self::FORUMS_OPTION_SPECIAL_PARENT:
                case self::FORUMS_OPTION_SPECIAL_PARENT_AND_CHILDREN:
                    return true;
            }
        }

        return false;
    }

    /**
     * Helper method to be used within _getCacheId.
     *
     * @param array $forumsOption the `forums` option
     * @param array $templateParams depending on the option, this method
     *                requires information from the template params.
     * @param bool $asGuest flag to use guest permissions instead of
     *                current user permissions
     *
     * @return string forum id or empty string
     */
    protected function _helperGetForumIdForCache(
        array $forumsOption,
        array $templateParams = array(),
        /** @noinspection PhpUnusedParameterInspection */
        $asGuest = false
    ) {
        if (!empty($forumsOption)) {
            $templateNode = null;

            if (!empty($templateParams['forum']['node_id'])) {
                $templateNode = $templateParams['forum'];
            } elseif (!empty($templateParams['category']['node_id'])) {
                $templateNode = $templateParams['category'];
            } elseif (!empty($templateParams['page']['node_id'])) {
                $templateNode = $templateParams['page'];
            } elseif (!empty($templateParams['widgetPage']['node_id'])) {
                $templateNode = $templateParams['widgetPage'];
            }

            if (!empty($templateNode)) {
                return $templateNode['node_id'];
            }
        }

        return '';
    }

    /**
     * Helper method to get an array of forum ids ready to be used.
     * The forum ids are taken after processing the `forums` option.
     * Look into the source code of built-in renderer to understand
     * how to use this method.
     *
     * @param array $forumsOption the `forums` option
     * @param array $templateParams depending on the option, this method
     *                requires information from the template params.
     * @param bool $asGuest flag to use guest permissions instead of
     *                current user permissions
     *
     * @return array of forum ids
     */
    protected function _helperGetForumIdsFromOption(
        array $forumsOption,
        array $templateParams = array(),
        $asGuest = false
    ) {
        if (empty($forumsOption)) {
            $forumIds = array_keys($this->_helperGetViewableNodeList($asGuest));
        } else {
            $forumIds = array_values($forumsOption);
            $forumIdsSpecial = array();
            $templateNode = null;

            if (!empty($templateParams['forum']['node_id'])) {
                $templateNode = $templateParams['forum'];
            } elseif (!empty($templateParams['category']['node_id'])) {
                $templateNode = $templateParams['category'];
            } elseif (!empty($templateParams['page']['node_id'])) {
                $templateNode = $templateParams['page'];
            } elseif (!empty($templateParams['widgetPage']['node_id'])) {
                $templateNode = $templateParams['widgetPage'];
            }

            foreach (array_keys($forumIds) as $i) {
                switch ($forumIds[$i]) {
                    case self::FORUMS_OPTION_SPECIAL_CURRENT:
                        if (!empty($templateNode)) {
                            $forumIdsSpecial[] = $templateNode['node_id'];
                        }
                        unset($forumIds[$i]);
                        break;
                    case self::FORUMS_OPTION_SPECIAL_CURRENT_AND_CHILDREN:
                        if (!empty($templateNode)) {
                            $templateNodeId = $templateNode['node_id'];
                            $forumIdsSpecial[] = $templateNodeId;

                            $viewableNodeList = $this->_helperGetViewableNodeList($asGuest);
                            $this->_helperMergeChildForumIds($forumIdsSpecial, $viewableNodeList, $templateNodeId);
                        }
                        unset($forumIds[$i]);
                        break;
                    case self::FORUMS_OPTION_SPECIAL_PARENT:
                        if (!empty($templateNode)) {
                            $forumIdsSpecial[] = $templateNode['parent_node_id'];
                        }
                        unset($forumIds[$i]);
                        break;
                    case self::FORUMS_OPTION_SPECIAL_PARENT_AND_CHILDREN:
                        if (!empty($templateNode)) {
                            $templateNodeId = $templateNode['parent_node_id'];
                            $forumIdsSpecial[] = $templateNodeId;

                            $viewableNodeList = $this->_helperGetViewableNodeList($asGuest);
                            $this->_helperMergeChildForumIds($forumIdsSpecial, $viewableNodeList, $templateNodeId);
                        }
                        unset($forumIds[$i]);
                        break;
                }
            }

            if (!empty($forumIdsSpecial)) {
                // only merge 2 arrays if some new ids are found...
                $forumIds = array_unique(array_merge($forumIds, $forumIdsSpecial));
            }
        }

        sort($forumIds);

        return $forumIds;
    }

    /**
     * Helper method to traverse a list of nodes looking for
     * children forums of a specified node
     *
     * @param array $forumIds the result array (this array will be modified)
     * @param array $nodes the nodes array to process
     * @param int $parentNodeId the parent node id to use and check against
     */
    protected function _helperMergeChildForumIds(array &$forumIds, array &$nodes, $parentNodeId)
    {
        foreach ($nodes as $node) {
            if ($node['parent_node_id'] == $parentNodeId) {
                $forumIds[] = $node['node_id'];
                $this->_helperMergeChildForumIds($forumIds, $nodes, $node['node_id']);
            }
        }
    }

    /**
     * Helper method to get viewable node list. Renderers need this information
     * should use call this method to get it. The node list is queried and cached
     * to improve performance.
     *
     * @param bool $asGuest flag to use guest permissions instead of current user
     * permissions
     *
     * @return array of viewable node (node_id as array key)
     */
    protected function _helperGetViewableNodeList($asGuest)
    {
        if ($asGuest) {
            return $this->_helperGetViewableNodeListGuestOnly();
        }

        static $viewableNodeList = false;

        if ($viewableNodeList === false) {
            /** @var XenForo_Model_Node $nodeModel */
            $nodeModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Node');
            $viewableNodeList = $nodeModel->getViewableNodeList();
        }

        return $viewableNodeList;
    }

    protected function _helperGetViewableNodeListGuestOnly()
    {
        static $viewableNodeList = false;

        if ($viewableNodeList === false) {
            /* @var $nodeModel XenForo_Model_Node */
            $nodeModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Node');

            $nodePermissions = $nodeModel->getNodePermissionsForPermissionCombination(1);
            $viewableNodeList = $nodeModel->getViewableNodeList($nodePermissions);
        }

        return $viewableNodeList;
    }

    protected $_configuration = false;

    public function getConfiguration()
    {
        if ($this->_configuration === false) {
            $default = array(
                'name' => 'Name',
                'options' => array(),

                'useCache' => false,
                'useUserCache' => false,
                'useLiveCache' => false,
                'cacheSeconds' => 0,

                'useWrapper' => true,

                'canAjaxLoad' => false,
            );

            $this->_configuration = XenForo_Application::mapMerge($default, $this->_getConfiguration());

            if ($this->_configuration['useCache']) {
                $this->_configuration['options']['cache_seconds'] = XenForo_Input::STRING;
            }

            $this->_configuration['options']['expression'] = XenForo_Input::STRING;
            $this->_configuration['options']['conditional'] = XenForo_Input::ARRAY_SIMPLE;
            $this->_configuration['options']['deactivate_for_mobile'] = XenForo_Input::UINT;
        }

        return $this->_configuration;
    }

    public function getName()
    {
        $configuration = $this->getConfiguration();
        return $configuration['name'];
    }

    public function isHidden()
    {
        $configuration = $this->getConfiguration();
        return !empty($configuration['isHidden']);
    }

    public function useWrapper(
        /** @noinspection PhpUnusedParameterInspection */
        array $widget
    ) {
        $configuration = $this->getConfiguration();
        return !empty($configuration['useWrapper']);
    }

    public function useCache(array $widget)
    {
        if (WidgetFramework_Core::debugMode()
            || WidgetFramework_Option::get('layoutEditorEnabled')
            || WidgetFramework_Option::get('cacheStore') === '0'
        ) {
            return false;
        }

        if (isset($widget['options']['cache_seconds'])
            && $widget['options']['cache_seconds'] === '0'
        ) {
            return false;
        }

        $configuration = $this->getConfiguration();
        return !empty($configuration['useCache']);
    }

    public function useUserCache(
        /** @noinspection PhpUnusedParameterInspection */
        array $widget
    ) {
        $configuration = $this->getConfiguration();
        return !empty($configuration['useUserCache']);
    }

    public function canAjaxLoad(
        /** @noinspection PhpUnusedParameterInspection */
        array $widget
    ) {
        $configuration = $this->getConfiguration();
        return !empty($configuration['canAjaxLoad']);
    }

    public function requireLock(array $widget)
    {
        // sondh@2013-04-09
        // if a renderer needs caching -> require lock all the time
        // TODO: separate configuration option?
        return $this->useCache($widget);
    }

    public function renderOptions(XenForo_ViewRenderer_Abstract $viewRenderer, array &$templateParams)
    {
        $templateParams['namePrefix'] = self::getNamePrefix();
        $templateParams['options_loaded'] = get_class($this);
        $templateParams['options'] = (!empty($templateParams['widget']['options'])) ? $templateParams['widget']['options'] : array();
        $templateParams['rendererConfiguration'] = $this->getConfiguration();

        if ($this->_getOptionsTemplate()) {
            $optionsTemplate = $viewRenderer->createTemplateObject($this->_getOptionsTemplate(), $templateParams);

            $this->_renderOptions($optionsTemplate);

            $templateParams['optionsRendered'] = $optionsTemplate->render();
        }
    }

    public function parseOptionsInput(XenForo_Input $input, array $widget)
    {
        $configuration = $this->getConfiguration();
        $options = empty($widget['options']) ? array() : $widget['options'];

        foreach ($configuration['options'] as $optionKey => $optionType) {
            $optionValue = $input->filterSingle(self::getNamePrefix() . $optionKey, $optionType);
            if ($this->_validateOptionValue($optionKey, $optionValue) !== false) {
                $options[$optionKey] = $optionValue;
            }
        }

        if (!empty($widget['widget_page_id'])) {
            if (empty($options['layout_sizeRow'])) {
                $options['layout_sizeRow'] = 1;
            }
            if (empty($options['layout_sizeCol'])) {
                $options['layout_sizeCol'] = 1;
            }
        }

        if (!empty($options['conditional'])
            && !empty($options['expression'])
        ) {
            unset($options['expression']);
        }

        return $options;
    }

    public function prepare(array &$widgetRef, $positionCode, array $params, XenForo_Template_Abstract $template)
    {
        $template->preloadTemplate('wf_widget_wrapper');

        $renderTemplate = $this->_getRenderTemplate($widgetRef, $positionCode, $params);
        if (!empty($renderTemplate)) {
            $template->preloadTemplate($renderTemplate);
        }

        if ($this->useCache($widgetRef)) {
            $cacheId = $this->_getCacheId($widgetRef, $positionCode, $params);
            $this->_getCacheModel()->preloadCache($cacheId);
        }

        $this->_prepare($widgetRef, $positionCode, $params, $template);
    }

    /**
     * @param $expression
     * @param array $params
     * @return bool
     * @throws Exception
     */
    protected function _executeExpression($expression, array $params)
    {
        if (WidgetFramework_Core::debugMode()) {
            XenForo_Error::logError('Widget Expression has been deprecated: %s', $expression);
        }

        $expression = trim($expression);
        if (empty($expression)) {
            return true;
        }

        $sandbox = @create_function('$params', 'extract($params); return (' . $expression . ');');

        if (!empty($sandbox)) {
            return call_user_func($sandbox, $params);
        } else {
            throw new Exception('Syntax error');
        }
    }

    protected function _testConditional(array $widget, array $params)
    {
        if (isset($widget['_ajaxLoadParams'])) {
            // ignore for ajax load, it should be tested before the tab is rendered
            // there is a small security risk here but nothing too serious
            return true;
        }

        if (!empty($widget['options']['conditional'])) {
            $conditional = $widget['options']['conditional'];

            if (!empty($conditional['raw'])
                && !empty($conditional['parsed'])
            ) {
                return WidgetFramework_Helper_Conditional::test($conditional['raw'], $conditional['parsed'], $params);
            }
        } elseif (!empty($widget['options']['expression'])) {
            return $this->_executeExpression($widget['options']['expression'], $params);
        }

        return true;
    }

    protected function _getCacheId(
        /** @noinspection PhpUnusedParameterInspection */
        array $widget,
        $positionCode,
        array $params,
        array $suffix = array()
    ) {
        $permissionCombination = 1;
        if ($this->useUserCache($widget)) {
            $permissionCombination = XenForo_Visitor::getInstance()->get('permission_combination_id');
        }

        if (empty($suffix)) {
            return sprintf('%s_pc%d', $positionCode, $permissionCombination);
        } else {
            return sprintf('%s_pc%d_s%s', $positionCode, $permissionCombination, implode('_', $suffix));
        }
    }

    protected function _acquireLock(array $widget, $positionCode, array $param)
    {
        if (!$this->requireLock($widget)) {
            return '';
        }

        $cacheModel = $this->_getCacheModel();
        $lockId = $this->_getCacheId($widget, $positionCode, $param, array('lock', $widget['widget_id']));

        $isLocked = false;
        $cached = $cacheModel->getCache(0, $lockId, array(
            WidgetFramework_Model_Cache::OPTION_CACHE_STORE => WidgetFramework_Model_Cache::OPTION_CACHE_STORE_FILE
        ));
        if (!empty($cached)
            && is_array($cached)
        ) {
            if (!empty($cached[WidgetFramework_Model_Cache::KEY_TIME])
                && XenForo_Application::$time - $cached[WidgetFramework_Model_Cache::KEY_TIME] < 10
            ) {
                $isLocked = !empty($cached[WidgetFramework_Model_Cache::KEY_HTML])
                    && $cached[WidgetFramework_Model_Cache::KEY_HTML] === '1';
            }
        }

        if ($isLocked) {
            // locked by some other requests!
            return false;
        }

        $cacheModel->setCache(0, $lockId, '1', array(), array(
            WidgetFramework_Model_Cache::OPTION_CACHE_STORE => WidgetFramework_Model_Cache::OPTION_CACHE_STORE_FILE
        ));

        return $lockId;
    }

    protected function _releaseLock($lockId)
    {
        if (!empty($lockId)) {
            $this->_getCacheModel()->setCache(0, $lockId, '0', array(), array(
                WidgetFramework_Model_Cache::OPTION_CACHE_STORE => WidgetFramework_Model_Cache::OPTION_CACHE_STORE_FILE
            ));
        }
    }

    protected function _restoreFromCache($cached, &$html, &$containerData, &$requiredExternals)
    {
        $html = $cached[WidgetFramework_Model_Cache::KEY_HTML];

        if (!empty($cached[WidgetFramework_Model_Cache::KEY_EXTRA_DATA][self::EXTRA_CONTAINER_DATA])) {
            $containerData = $cached[WidgetFramework_Model_Cache::KEY_EXTRA_DATA][self::EXTRA_CONTAINER_DATA];
        }

        if (!empty($cached[WidgetFramework_Model_Cache::KEY_EXTRA_DATA][self::EXTRA_REQUIRED_EXTERNALS])) {
            $requiredExternals = $cached[WidgetFramework_Model_Cache::KEY_EXTRA_DATA][self::EXTRA_REQUIRED_EXTERNALS];
        }
    }

    public function render(
        array &$widgetRef,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $template,
        /** @noinspection PhpUnusedParameterInspection */
        &$output
    ) {
        $html = false;
        $containerData = array();
        $requiredExternals = array();

        try {
            if (!$this->_testConditional($widgetRef, $params)) {
                // expression failed, stop rendering...
                if (WidgetFramework_Option::get('layoutEditorEnabled')) {
                    $html = new XenForo_Phrase('wf_layout_editor_widget_conditional_failed');
                } else {
                    $html = '';
                }
            }
        } catch (Exception $e) {
            // problem while testing conditional, stop rendering...
            if (WidgetFramework_Core::debugMode() OR WidgetFramework_Option::get('layoutEditorEnabled')) {
                $html = $e->getMessage();
            } else {
                $html = '';
            }
        }

        // add check for mobile (user agent spoofing)
        // since 2.2.2
        if (!empty($widgetRef['options']['deactivate_for_mobile'])) {
            if (XenForo_Visitor::isBrowsingWith('mobile')) {
                $html = '';
            }
        }

        // check for cache
        // since 1.2.1
        $cacheId = false;
        $lockId = '';

        if ($html === false
            && $this->useCache($widgetRef)
        ) {
            $cacheId = $this->_getCacheId($widgetRef, $positionCode, $params);
            $cached = $this->_getCacheModel()->getCache($widgetRef['widget_id'], $cacheId);

            if (!empty($cached)
                && is_array($cached)
            ) {
                if ($this->isCacheUsable($cached, $widgetRef)) {
                    // found fresh cached html, use it asap
                    $this->_restoreFromCache($cached, $html, $containerData, $requiredExternals);
                } else {
                    // cached html has expired: try to acquire lock
                    $lockId = $this->_acquireLock($widgetRef, $positionCode, $params);

                    if ($lockId === false) {
                        // a lock cannot be acquired, an expired cached html is the second best choice
                        $this->_restoreFromCache($cached, $html, $containerData, $requiredExternals);
                    }
                }
            } else {
                // no cache found
                $lockId = $this->_acquireLock($widgetRef, $positionCode, $params);
            }
        }

        if ($html === false
            && $lockId === false
        ) {
            // a lock is required but we failed to acquired it
            // also, a cached could not be found
            // stop rendering
            $html = '';
        }

        // conditional executed just fine
        if ($html === false) {
            $renderTemplate = $this->_getRenderTemplate($widgetRef, $positionCode, $params);
            if (!empty($renderTemplate)) {
                $renderTemplateParams = $params;
                $renderTemplateParams['widget'] =& $widgetRef;
                $renderTemplateObject = $template->create($renderTemplate, $renderTemplateParams);
                $renderTemplateObject->setParam(WidgetFramework_Core::PARAM_CURRENT_WIDGET_ID, $widgetRef['widget_id']);

                // reset required externals
                $existingRequiredExternals = WidgetFramework_Template_Extended::WidgetFramework_getRequiredExternals();
                WidgetFramework_Template_Extended::WidgetFramework_setRequiredExternals(array());

                $html = $this->_render($widgetRef, $positionCode, $params, $renderTemplateObject);

                if ($cacheId !== false) {
                    // force render template (if any) to collect required externals
                    // only do that if caching is enabled though
                    $html = strval($html);
                }

                // get container data (using template_post_render listener)
                $containerData = self::_getContainerData($widgetRef);
                // get widget required externals
                $requiredExternals = WidgetFramework_Template_Extended::WidgetFramework_getRequiredExternals();
                WidgetFramework_Template_Extended::WidgetFramework_setRequiredExternals($existingRequiredExternals);
            } else {
                $html = $this->_render($widgetRef, $positionCode, $params, $template);
            }
            $html = trim($html);

            if ($cacheId !== false) {
                $extraData = array();
                if (!empty($containerData)) {
                    $extraData[self::EXTRA_CONTAINER_DATA] = $containerData;
                }
                if (!empty($requiredExternals)) {
                    $extraData[self::EXTRA_REQUIRED_EXTERNALS] = $requiredExternals;
                }

                $this->_getCacheModel()->setCache($widgetRef['widget_id'], $cacheId, $html, $extraData);
            }
        }

        $this->_releaseLock($lockId);

        if (!empty($containerData)) {
            // apply container data
            WidgetFramework_Template_Extended::WidgetFramework_mergeExtraContainerData($containerData);
        }

        if (!empty($requiredExternals)) {
            // register required external
            foreach ($requiredExternals as $type => $requirements) {
                foreach ($requirements as $requirement) {
                    $template->addRequiredExternal($type, $requirement);
                }
            }
        }

        return $html;
    }

    public function getAjaxLoadUrl(array $widget, $positionCode, array $params, XenForo_Template_Abstract $template)
    {
        $ajaxLoadParams = $this->_getAjaxLoadParams($widget, $positionCode, $params, $template);
        return XenForo_Link::buildPublicLink('full:misc/wf-widget', null, array(
            'widget_id' => $widget['widget_id'],
            'alp' => json_encode($ajaxLoadParams),
        ));
    }

    protected function _getAjaxLoadParams(
        /** @noinspection PhpUnusedParameterInspection */
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $template
    ) {
        return array(
            self::PARAM_IS_HOOK => !empty($params[self::PARAM_IS_HOOK]),
        );
    }

    public function extraPrepare(
        array $widget,
        /** @noinspection PhpUnusedParameterInspection */
        &$html
    ) {
        $extra = array();

        $link = $this->_getExtraDataLink($widget);
        if (!empty($link)) {
            $extra['link'] = $link;
        }

        return $extra;
    }

    public function extraPrepareTitle(array $widget)
    {
        if (!empty($widget['title'])) {
            if (is_string($widget['title'])
                && preg_match('/^{xen:phrase ([^}]+)}$/i', $widget['title'], $matches)
            ) {
                // {xen:phrase title} as widget title, use the specified phrase

                if (XenForo_Application::debugMode()) {
                    // this kind of usage is deprecated, log server error entry if debug mode is on
                    XenForo_Error::logError(sprintf(
                        'Widget title support for {xen:phrase title} has been deprecated. '
                        . 'Please update widget #%d.', $widget['widget_id']
                    ));
                }

                return new XenForo_Phrase($matches[1]);
            } else {
                if (!empty($widget['options'][WidgetFramework_DataWriter_Widget::WIDGET_OPTION_ADDON_VERSION_ID])
                    && $widget['widget_id'] > 0
                ) {
                    // since 2.6.0
                    // use self-managed phrase for widget title
                    /** @var WidgetFramework_Model_Widget $widgetModel */
                    $widgetModel = WidgetFramework_Core::getInstance()->getModelFromCache('WidgetFramework_Model_Widget');
                    return new XenForo_Phrase($widgetModel->getWidgetTitlePhrase($widget['widget_id']));
                } else {
                    // legacy support
                    return $widget['title'];
                }
            }
        } else {
            return $this->getName();
        }
    }

    public function isCacheUsable(array &$cached, array $widget)
    {
        $configuration = $this->getConfiguration();
        if (empty($configuration['useCache'])) {
            return false;
        }

        $cacheSeconds = $configuration['cacheSeconds'];

        if (!empty($widget['options']['cache_seconds'])) {
            $cacheSeconds = intval($widget['options']['cache_seconds']);
        }

        if ($cacheSeconds < 0) {
            return true;
        }

        $seconds = XenForo_Application::$time - $cached['time'];
        if ($seconds > $cacheSeconds) {
            return false;
        }

        return true;
    }

    const FORUMS_OPTION_SPECIAL_CURRENT = 'current_forum';
    const FORUMS_OPTION_SPECIAL_CURRENT_AND_CHILDREN = 'current_forum_and_children';
    const FORUMS_OPTION_SPECIAL_PARENT = 'parent_forum';
    const FORUMS_OPTION_SPECIAL_PARENT_AND_CHILDREN = 'parent_forum_and_children';
    const EXTRA_CONTAINER_DATA = 'containerData';
    const EXTRA_REQUIRED_EXTERNALS = 'requiredExternals';

    protected static $_containerData = array();

    /**
     * @var XenForo_View
     */
    protected static $_pseudoViewObj = null;

    public static function create($class)
    {
        static $instances = array();

        if (!isset($instances[$class])) {
            $createClass = XenForo_Application::resolveDynamicClass($class, 'widget_renderer');
            if (!$createClass) {
                throw new XenForo_Exception("Invalid renderer '$class' specified");
            }

            $instances[$class] = new $createClass;
        }

        return $instances[$class];
    }

    public static function getNamePrefix()
    {
        return 'options_';
    }

    public static function setContainerData($widgetId, array $containerData)
    {
        if (!isset(self::$_containerData[$widgetId])) {
            self::$_containerData[$widgetId] = $containerData;
        } else {
            self::$_containerData[$widgetId] = XenForo_Application::mapMerge(
                self::$_containerData[$widgetId], $containerData);
        }
    }

    protected static function _getContainerData(array $widget)
    {
        if (isset(self::$_containerData[$widget['widget_id']])) {
            return self::$_containerData[$widget['widget_id']];
        } else {
            return array();
        }
    }

    public static function getViewObject(array $params, XenForo_Template_Abstract $templateObj)
    {
        if (isset($params[WidgetFramework_Core::PARAM_VIEW_OBJECT])) {
            return $params[WidgetFramework_Core::PARAM_VIEW_OBJECT];
        }

        $viewObj = $templateObj->getParam(WidgetFramework_Core::PARAM_VIEW_OBJECT);
        if (!empty($viewObj)) {
            return $viewObj;
        }

        if (empty(self::$_pseudoViewObj)) {
            if (!empty(WidgetFramework_Listener::$fc)
                && !empty(WidgetFramework_Listener::$viewRenderer)
            ) {
                if (WidgetFramework_Listener::$viewRenderer instanceof XenForo_ViewRenderer_HtmlPublic) {
                    self::$_pseudoViewObj = new XenForo_ViewPublic_Base(
                        WidgetFramework_Listener::$viewRenderer,
                        WidgetFramework_Listener::$fc->getResponse()
                    );
                }
            }
        }

        if (!empty(self::$_pseudoViewObj)) {
            return self::$_pseudoViewObj;
        }

        if (WidgetFramework_Core::debugMode()) {
            // log the exception for admin examination (in our debug mode only)
            XenForo_Error::logException(new XenForo_Exception(sprintf('Unable to get view object for %s',
                $templateObj->getTemplateName())), false, '[bd] Widget Framework');
        }

        return null;
    }

    /**
     * @return WidgetFramework_Model_Cache
     */
    protected function _getCacheModel()
    {
        return WidgetFramework_Core::getInstance()->getModelFromCache('WidgetFramework_Model_Cache');
    }
}
