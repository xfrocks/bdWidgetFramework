<?php
abstract class WidgetFramework_WidgetRenderer
{
	const PARAM_TO_BE_PROCESSED = '_WidgetFramework_toBeProcessed';
	const PARAM_POSITION_CODE = '_WidgetFramework_positionCode';
	const PARAM_IS_HOOK = '_WidgetFramework_isHook';
	const PARAM_PARENT_TEMPLATE = '_WidgetFramework_parentTemplate';
	const PARAM_VIEW_OBJECT = '_WidgetFramework_viewObj';

	/**
	 * Required method: define basic configuration of the renderer.
	 * Available configuration parameters:
	 * 	- name: The display name of the renderer
	 * 	- options: An array of renderer's options
	 * 	- useCache: Flag to determine the renderer can be cached or not
	 * 	- useUserCache: Flag to determine the renderer needs to be cached by an
	 * 					user-basis.
	 * 					Internally, this is implemented by getting the current user permission
	 * 					combination id (not the user id as normally expected). This is done to
	 * 					make sure the cache is used effectively
	 * 	- useLiveCache: Flag to determine the renderer wants to by pass writing to
	 * 					database
	 * 					when it's being cached. This may be crucial if the renderer does a lot
	 * 					of thing on a big board. It's recommended to use a option for this
	 * 					because not all forum owner has a live cache system setup
	 * 					(XCache/memcached)
	 * 	- cacheSeconds: A numeric value to specify the maximum age of the cache (in
	 * 					seconds).
	 * 					If the cache is too old, the widget will be rendered from scratch
	 * 	- useWrapper: Flag to determine the widget should be wrapped with a wrapper.
	 * 					Renderers
	 * 					that support wrapper will have an additional benefits of tabs: only
	 * 					wrapper-enabled widgets will be possible to use in tabbed interface
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
	abstract protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject);

	protected function _renderOptions(XenForo_Template_Abstract $template)
	{
		return true;
	}

	protected function _validateOptionValue($optionKey, &$optionValue)
	{
		if ($optionKey === 'cache_seconds')
		{
			if (!is_numeric($optionValue))
			{
				$optionValue = '';
			}
			elseif ($optionValue < 0)
			{
				$optionValue = 0;
			}
		}

		return true;
	}

	protected function _prepare(array $widget, $positionCode, array $params)
	{
		return true;
	}

	protected function _getExtraDataLink(array $widget)
	{
		return false;
	}

	/**
	 * Helper method to prepare source array for <xen:select /> or similar tags
	 *
	 * @param array $selected an array of selected values
	 * @param bool $useSpecialForums flag to determine the usage of special forum
	 * indicator
	 */
	protected function _helperPrepareForumsOptionSource(array $selected = array(), $useSpecialForums = false)
	{
		$forums = array();
		$nodes = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Node')->getAllNodes();

		if ($useSpecialForums)
		{
			foreach (array(
			self::FORUMS_OPTION_SPECIAL_CURRENT,
			self::FORUMS_OPTION_SPECIAL_CURRENT_AND_CHILDREN,
			self::FORUMS_OPTION_SPECIAL_PARENT,
			self::FORUMS_OPTION_SPECIAL_PARENT_AND_CHILDREN,
			) as $specialId)
			{
				$forums[] = array(
					'value' => $specialId,
					'label' => new XenForo_Phrase('wf_' . $specialId),
					'selected' => in_array($specialId, $selected),
				);
			}
		}

		foreach ($nodes as $node)
		{
			if (in_array($node['node_type_id'], array(
				'Category',
				'LinkForum',
				'Page',
				'WF_WidgetPage'
			)))
			{
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
	 */
	protected function _helperDetectSpecialForums($forumIds)
	{
		if (!is_array($forumIds))
		{
			return false;
		}

		foreach ($forumIds as $forumId)
		{
			switch ($forumId)
			{
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
	 * Helper method to get an array of forum ids ready to be used.
	 * The forum ids are taken after processing the `forums` option.
	 * Look into the source code of built-in renderer to understand
	 * how to use this method.
	 *
	 * @param array $forumsOption the `forums` option
	 * @param array $templateParams depending on the option, this method
	 * 				requires information from the template params.
	 * @param bool $asGuest flag to use guest permissions instead of
	 * 				current user permissions
	 *
	 * @return array of forum ids
	 */
	protected function _helperGetForumIdsFromOption(array $forumsOption, array $templateParams = array(), $asGuest = false)
	{
		if (empty($forumsOption))
		{
			$forumIds = array_keys($this->_helperGetViewableNodeList($asGuest));
		}
		else
		{
			$forumIds = array_values($forumsOption);
			$forumIds2 = array();
			$templateNode = null;

			if (!empty($templateParams['forum']['node_id']))
			{
				$templateNode = $templateParams['forum'];
			}
			elseif (!empty($templateParams['category']['node_id']))
			{
				$templateNode = $templateParams['category'];
			}
			elseif (!empty($templateParams['page']['node_id']))
			{
				$templateNode = $templateParams['page'];
			}
			elseif (!empty($templateParams['widgetPage']['node_id']))
			{
				$templateNode = $templateParams['widgetPage'];
			}

			foreach (array_keys($forumIds) as $i)
			{
				switch ($forumIds[$i])
				{
					case self::FORUMS_OPTION_SPECIAL_CURRENT:
						if (!empty($templateNode))
						{
							$forumIds2[] = $templateNode['node_id'];
						}
						unset($forumIds[$i]);
						break;
					case self::FORUMS_OPTION_SPECIAL_CURRENT_AND_CHILDREN:
						if (!empty($templateNode))
						{
							$forumIds2[] = $templateNode['node_id'];

							$viewableNodeList = $this->_helperGetViewableNodeList($asGuest);
							$this->_helperMergeChildForumIds($forumIds2, $viewableNodeList, $templateNode['node_id']);
						}
						unset($forumIds[$i]);
						break;
					case self::FORUMS_OPTION_SPECIAL_PARENT:
						if (!empty($templateNode))
						{
							$forumIds2[] = $templateNode['parent_node_id'];
						}
						unset($forumIds[$i]);
						break;
					case self::FORUMS_OPTION_SPECIAL_PARENT_AND_CHILDREN:
						if (!empty($templateNode))
						{
							$forumIds2[] = $templateNode['parent_node_id'];

							$viewableNodeList = $this->_helperGetViewableNodeList($asGuest);
							$this->_helperMergeChildForumIds($forumIds2, $viewableNodeList, $templateNode['parent_node_id']);
						}
						unset($forumIds[$i]);
						break;
				}
			}

			if (!empty($forumIds2))
			{
				// only merge 2 arrays if some new ids are found...
				$forumIds = array_unique(array_merge($forumIds, $forumIds2));
			}
		}

		return $forumIds;
	}

	/**
	 * Helper method to traverse a list of nodes looking for
	 * children forums of a specified node
	 *
	 * @param unknown_type $forumIds the result array (this array will be modified)
	 * @param unknown_type $nodes the nodes array to process
	 * @param unknown_type $parentNodeId the parent node id to use and check against
	 */
	protected function _helperMergeChildForumIds(array &$forumIds, array &$nodes, $parentNodeId)
	{
		foreach ($nodes as $node)
		{
			if ($node['parent_node_id'] == $parentNodeId)
			{
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
	 * @param $asGuest flag to use guest permissions instead of current user
	 * permissions
	 *
	 * @return array of viewable node (node_id as array key)
	 */
	protected function _helperGetViewableNodeList($asGuest)
	{
		if ($asGuest)
		{
			return $this->_helperGetViewableNodeListGuestOnly();
		}

		static $viewableNodeList = false;

		if ($viewableNodeList === false)
		{
			$viewableNodeList = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Node')->getViewableNodeList();
		}

		return $viewableNodeList;
	}

	protected function _helperGetViewableNodeListGuestOnly()
	{
		static $viewableNodeList = false;

		if ($viewableNodeList === false)
		{
			/* @var $nodeModel XenForo_Model_Node */
			$nodeModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Node');

			$nodePermissions = $nodeModel->getNodePermissionsForPermissionCombination(1);
			$viewableNodeList = $nodeModel->getViewableNodeList($nodePermissions);
		}

		return $viewableNodeList;
	}

	protected static $_widgetTemplates = array();
	protected $_configuration = false;

	public function getConfiguration()
	{
		if ($this->_configuration === false)
		{
			$default = array(
				'name' => 'Name',
				'options' => array(),
				'useCache' => false, // output of this widget can be cached
				'useUserCache' => false, // output should be cached by user permission (must have
				// `useCache` enabled)
				'useLiveCache' => false, // output will be cached with live cache only (bypass
				// database completely)
				'cacheSeconds' => 0, // cache older will be ignored, 0 means forever
				'useWrapper' => true,
			);

			$this->_configuration = XenForo_Application::mapMerge($default, $this->_getConfiguration());

			if ($this->_configuration['useWrapper'])
			{
				$this->_configuration['options']['tab_group'] = XenForo_Input::STRING;
			}

			if ($this->_configuration['useCache'])
			{
				$this->_configuration['options']['cache_seconds'] = XenForo_Input::STRING;
			}

			$this->_configuration['options']['expression'] = XenForo_Input::STRING;
			$this->_configuration['options']['deactivate_for_mobile'] = XenForo_Input::UINT;
			$this->_configuration['options']['layout_row'] = XenForo_Input::UINT;
			$this->_configuration['options']['layout_col'] = XenForo_Input::UINT;
			$this->_configuration['options']['layout_sizeRow'] = XenForo_Input::UINT;
			$this->_configuration['options']['layout_sizeCol'] = XenForo_Input::UINT;
		}

		return $this->_configuration;
	}

	public function getName()
	{
		$configuration = $this->getConfiguration();
		return $configuration['name'];
	}

	public function useWrapper(array $widget)
	{
		$configuration = $this->getConfiguration();
		return !empty($configuration['useWrapper']);
	}

	public function useCache(array $widget)
	{
		if (isset($widget['options']['cache_seconds']) AND $widget['options']['cache_seconds'] === '0')
		{
			return false;
		}

		$configuration = $this->getConfiguration();
		return !empty($configuration['useCache']);
	}

	public function useUserCache(array $widget)
	{
		$configuration = $this->getConfiguration();
		return !empty($configuration['useUserCache']);
	}

	public function useLiveCache(array $widget)
	{
		$configuration = $this->getConfiguration();
		return !empty($configuration['useLiveCache']);
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

		if ($this->_getOptionsTemplate())
		{
			$optionsTemplate = $viewRenderer->createTemplateObject($this->_getOptionsTemplate(), $templateParams);

			$this->_renderOptions($optionsTemplate);

			$templateParams['optionsRendered'] = $optionsTemplate->render();
		}
	}

	public function parseOptionsInput(XenForo_Input $input, array $widget)
	{
		$configuration = $this->getConfiguration();
		$options = empty($widget['options']) ? array() : $widget['options'];

		foreach ($configuration['options'] as $optionKey => $optionType)
		{
			$optionValue = $input->filterSingle(self::getNamePrefix() . $optionKey, $optionType);
			if ($this->_validateOptionValue($optionKey, $optionValue) !== false)
			{
				$options[$optionKey] = $optionValue;
			}
		}

		if (!empty($widget['widget_page_id']))
		{
			if (empty($options['layout_sizeRow']))
			{
				$options['layout_sizeRow'] = 1;
			}
			if (empty($options['layout_sizeCol']))
			{
				$options['layout_sizeCol'] = 1;
			}
		}

		return $options;
	}

	public function prepare(array $widget, $positionCode, array $params, XenForo_Template_Abstract $template)
	{
		if ($this->useWrapper($widget))
		{
			$template->preloadTemplate('wf_widget_wrapper');
		}

		$renderTemplate = $this->_getRenderTemplate($widget, $positionCode, $params);
		if (!empty($renderTemplate))
		{
			$template->preloadTemplate($renderTemplate);
			self::$_widgetTemplates[$renderTemplate] = true;
		}

		if ($this->useCache($widget))
		{
			// sondh@2013-04-02
			// please keep this block of code in-sync'd with its original
			// implemented in WidgetFramework_WidgetRenderer::render
			$cacheId = $this->_getCacheId($widget, $positionCode, $params);
			$useUserCache = $this->useUserCache($widget);
			$useLiveCache = $this->useLiveCache($widget);

			WidgetFramework_Core::preloadCachedWidget($cacheId, $useUserCache, $useLiveCache);
		}

		$this->_prepare($widget, $positionCode, $params);
	}

	protected function _executeExpression($expression, array $params)
	{
		$expression = trim($expression);
		if (empty($expression))
			return true;

		$sandbox = @create_function('$params', 'extract($params); return (' . $expression . ');');

		if (!empty($sandbox))
		{
			return call_user_func($sandbox, $params);
		}
		else
		{
			throw new Exception('Syntax error');
		}
	}

	protected function _getCacheId(array $widget, $positionCode, array $params, array $suffix = array())
	{
		if (empty($suffix))
		{
			return sprintf('%s_%s', $positionCode, $widget['widget_id']);
		}
		else
		{
			return sprintf('%s_%s_%s', $positionCode, implode('_', $suffix), $widget['widget_id']);
		}
	}

	protected function _acquireLock(array $widget, $positionCode, array $param)
	{
		if (!$this->requireLock($widget))
		{
			return '';
		}

		$lockId = $this->_getCacheId($widget, $positionCode, $param, array('lock'));

		$isLocked = false;
		$cached = WidgetFramework_Core::loadCachedWidget($lockId, false, true);
		if (!empty($cached) AND is_array($cached))
		{
			if (!empty($cached[WidgetFramework_Model_Cache::KEY_TIME]) AND XenForo_Application::$time - $cached[WidgetFramework_Model_Cache::KEY_TIME] < 10)
			{
				$isLocked = !empty($cached[WidgetFramework_Model_Cache::KEY_HTML]) AND $cached[WidgetFramework_Model_Cache::KEY_HTML] === '1';
			}
		}
		if ($isLocked)
		{
			// locked by some other requests!
			return false;
		}

		WidgetFramework_Core::saveCachedWidget($lockId, '1', array(), false, true);
		return $lockId;
	}

	protected function _releaseLock($lockId)
	{
		if (!empty($lockId))
		{
			WidgetFramework_Core::saveCachedWidget($lockId, '0', array(), false, true);
		}
	}

	protected function _restoreFromCache($cached, &$html, &$containerData, &$requiredExternals)
	{
		$html = $cached[WidgetFramework_Model_Cache::KEY_HTML];

		if (!empty($cached[WidgetFramework_Model_Cache::KEY_EXTRA_DATA][self::EXTRA_CONTAINER_DATA]))
		{
			$containerData = $cached[WidgetFramework_Model_Cache::KEY_EXTRA_DATA][self::EXTRA_CONTAINER_DATA];
		}

		if (!empty($cached[WidgetFramework_Model_Cache::KEY_EXTRA_DATA][self::EXTRA_REQUIRED_EXTERNALS]))
		{
			$requiredExternals = $cached[WidgetFramework_Model_Cache::KEY_EXTRA_DATA][self::EXTRA_REQUIRED_EXTERNALS];
		}
	}

	public function render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $template, &$output)
	{
		$cacheHit = false;
		$html = false;
		$containerData = array();
		$requiredExternals = array();

		// always check for expression if it's available
		// otherwise the cached widget will show up every where... (the cache test also
		// moved down below this)
		// since 1.2.1
		if (isset($widget['options']['expression']))
		{
			try
			{
				if (!$this->_executeExpression($widget['options']['expression'], $params))
				{
					// exepression failed, stop rendering...
					$html = '';
				}
			}
			catch (Exception $e)
			{
				// problem executing expression... Stop rendering anyway
				if (WidgetFramework_Core::debugMode())
				{
					$html = $e->getMessage();
				}
				else
				{
					$html = '';
				}
			}
		}

		// add check for mobile (user agent spoofing)
		// since 2.2.2
		if (!empty($widget['options']['deactivate_for_mobile']))
		{
			if (XenForo_Visitor::isBrowsingWith('mobile'))
			{
				$html = '';
			}
		}

		// check for cache after expression test
		// since 1.2.1
		$cacheId = false;
		$useUserCache = false;
		$useLiveCache = false;
		$lockId = '';

		if ($html === false AND $this->useCache($widget))
		{
			// sondh@2013-04-02
			// please keep this block of code in-sync'd with its copycat
			// implemented in WidgetFramework_WidgetRenderer::prepare
			$cacheId = $this->_getCacheId($widget, $positionCode, $params);
			$useUserCache = $this->useUserCache($widget);
			$useLiveCache = $this->useLiveCache($widget);

			$cached = WidgetFramework_Core::loadCachedWidget($cacheId, $useUserCache, $useLiveCache);
			if (!empty($cached) AND is_array($cached))
			{
				if ($this->isCacheUsable($cached, $widget))
				{
					// found fresh cached html, use it asap
					$this->_restoreFromCache($cached, $html, $containerData, $requiredExternals);
					$cacheHit = true;
				}
				else
				{
					// cached html has expired: try to acquire lock
					$lockId = $this->_acquireLock($widget, $positionCode, $params);

					if ($lockId === false)
					{
						// a lock cannot be acquired, an expired cached html is the second best choice
						$this->_restoreFromCache($cached, $html, $containerData, $requiredExternals);
						$cacheHit = true;
					}
				}
			}
			else
			{
				// no cache found
				$lockId = $this->_acquireLock($widget, $positionCode, $params);
			}
		}

		if ($html === false AND $lockId === false)
		{
			// a lock is required but we failed to acquired it
			// also, a cached could not be found
			// stop rendering
			$html = '';
		}

		// expression executed just fine
		if ($html === false)
		{
			$renderTemplate = $this->_getRenderTemplate($widget, $positionCode, $params);
			if (!empty($renderTemplate))
			{
				$renderTemplateObject = $template->create($renderTemplate, array_merge($params, array('widget' => $widget)));

				// reset required externals
				$existingRequiredExternals = WidgetFramework_Template_Extended::WidgetFramework_getRequiredExternals();
				WidgetFramework_Template_Extended::WidgetFramework_setRequiredExternals(array());

				$html = $this->_render($widget, $positionCode, $params, $renderTemplateObject);

				// get container data (using template_post_render listener)
				$containerData = self::_getContainerData($widget);
				// get widget required externals
				$requiredExternals = WidgetFramework_Template_Extended::WidgetFramework_getRequiredExternals();
				WidgetFramework_Template_Extended::WidgetFramework_setRequiredExternals($existingRequiredExternals);
			}
			else
			{
				$html = $this->_render($widget, $positionCode, $params, $template);
			}
			$html = trim($html);

			if ($cacheId !== false)
			{
				$extraData = array();
				if (!empty($containerData))
				{
					$extraData[self::EXTRA_CONTAINER_DATA] = $containerData;
				}
				if (!empty($requiredExternals))
				{
					$extraData[self::EXTRA_REQUIRED_EXTERNALS] = $requiredExternals;
				}

				WidgetFramework_Core::preSaveWidget($widget, $positionCode, $params, $html);

				WidgetFramework_Core::saveCachedWidget($cacheId, $html, $extraData, $useUserCache, $useLiveCache);
			}
		}

		$this->_releaseLock($lockId);

		if (!empty($containerData))
		{
			// apply container data
			WidgetFramework_Template_Extended::WidgetFramework_mergeExtraContainerData($containerData);
		}

		if (!empty($requiredExternals))
		{
			// register required external
			foreach ($requiredExternals as $type => $requirements)
			{
				foreach ($requirements as $requirement)
				{
					$template->addRequiredExternal($type, $requirement);
				}
			}
		}

		return $html;
	}

	public function extraPrepare(array $widget, &$html)
	{
		$extra = array();

		$link = $this->_getExtraDataLink($widget);
		if (!empty($link))
		{
			$extra['link'] = $link;
		}

		return $extra;
	}

	public function extraPrepareTitle(array $widget)
	{
		if (!empty($widget['title']))
		{
			if (preg_match('/^{xen:phrase ([^}]+)}$/i', $widget['title'], $matches))
			{
				return new XenForo_Phrase($matches[1]);
			}
			else
			{
				return $widget['title'];
			}
		}
		else
		{
			return $this->getName();
		}
	}

	public function isCacheUsable(array &$cached, array $widget)
	{
		$configuration = $this->getConfiguration();
		if (empty($configuration['useCache']))
		{
			return false;
		}

		$cacheSeconds = $configuration['cacheSeconds'];

		if (!empty($widget['options']['cache_seconds']))
		{
			$cacheSeconds = intval($widget['options']['cache_seconds']);
		}

		if ($cacheSeconds < 0)
		{
			return true;
		}

		$seconds = XenForo_Application::$time - $cached['time'];
		if ($seconds > $cacheSeconds)
		{
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

	public static function wrap(array $tabs, array $params, XenForo_Template_Abstract $template, $groupId = false)
	{
		$isColumns = strpos($groupId, 'columns') === 0;

		if (empty($groupId))
		{
			$groupId = 'nope';
		}

		$normalizedGroupId = sprintf('%s-%s', $groupId, substr(md5(serialize(array_keys($tabs))), 0, 5));
		$normalizedGroupId = preg_replace('/[^a-zA-Z0-9\-]/', '', $normalizedGroupId);

		$wrapper = $template->create('wf_widget_wrapper', array_merge($params, array(
			'tabs' => $tabs,
			'groupId' => $groupId,

			'isColumns' => $isColumns,
			'normalizedGroupId' => $normalizedGroupId,
		)));

		return $wrapper;
	}

	public static function create($class)
	{
		static $instances = array();

		if (!isset($instances[$class]))
		{
			$createClass = XenForo_Application::resolveDynamicClass($class, 'widget_renderer');
			if (!$createClass)
			{
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

	public static function markTemplateToProcess(XenForo_ControllerResponse_View $view)
	{
		if (!empty($view->templateName))
		{
			$view->params[self::PARAM_TO_BE_PROCESSED] = $view->templateName;
		}

		if (!empty($view->subView))
		{
			// also mark any direct sub view to be processed
			self::markTemplateToProcess($view->subView);
		}
	}

	public static function isIgnoredTemplate($templateName, array $templateParams)
	{
		if (!empty(self::$_widgetTemplates[$templateName]))
		{
			// our templates are ignored, of course
			return true;
		}

		// sondh@2013-04-02
		// switch to use custom parameter set by markTemplateToProcess
		// to determine which template to ignore
		if (empty($templateParams[self::PARAM_TO_BE_PROCESSED]) OR $templateParams[self::PARAM_TO_BE_PROCESSED] != $templateName)
		{
			return true;
		}

		return false;
	}

	public static function setContainerData($widget, array $containerData)
	{
		if (is_array($widget))
		{
			if (empty(self::$_containerData[$widget['widget_id']]))
			{
				self::$_containerData[$widget['widget_id']] = $containerData;
			}
			else
			{
				self::$_containerData[$widget['widget_id']] = XenForo_Application::mapMerge(self::$_containerData[$widget['widget_id']], $containerData);
			}
		}
	}

	protected static function _getContainerData(array $widget)
	{
		if (isset(self::$_containerData[$widget['widget_id']]))
		{
			return self::$_containerData[$widget['widget_id']];
		}
		else
		{
			return array();
		}
	}

	public static function getViewObject(array $params, XenForo_Template_Abstract $templateObj)
	{
		if (isset($params[self::PARAM_VIEW_OBJECT]))
		{
			return $params[self::PARAM_VIEW_OBJECT];
		}

		$viewObj = $templateObj->getParam(self::PARAM_VIEW_OBJECT);
		if (!empty($viewObj))
		{
			return $viewObj;
		}

		if (empty(self::$_pseudoViewObj))
		{
			if (!empty(WidgetFramework_Listener::$fc) AND !empty(WidgetFramework_Listener::$viewRenderer))
			{
				if (WidgetFramework_Listener::$viewRenderer instanceof XenForo_ViewRenderer_HtmlPublic)
				{
					self::$_pseudoViewObj = new XenForo_ViewPublic_Base(WidgetFramework_Listener::$viewRenderer, WidgetFramework_Listener::$fc->getResponse());
				}
			}
		}

		if (!empty(self::$_pseudoViewObj))
		{
			return self::$_pseudoViewObj;
		}

		if (WidgetFramework_Core::debugMode())
		{
			// log the exception for admin examination (in our debug mode only)
			XenForo_Error::logException(new XenForo_Exception(sprintf('Unable to get view object for %s', $templateObj->getTemplateName())), false, '[bd] Widget Framework');
		}

		return null;
	}

}
