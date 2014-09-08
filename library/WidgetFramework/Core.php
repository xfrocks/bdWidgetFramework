<?php
class WidgetFramework_Core
{
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
		foreach ($renderers as $renderer)
		{
			$this->_renderers[] = $renderer;
		}
	}

	protected function _registerDefaultRenderers(array &$renderers)
	{
		$renderers[] = 'WidgetFramework_WidgetRenderer_Empty';
		$renderers[] = 'WidgetFramework_WidgetRenderer_OnlineStaff';
		$renderers[] = 'WidgetFramework_WidgetRenderer_OnlineUsers';
		$renderers[] = 'WidgetFramework_WidgetRenderer_Stats';
		$renderers[] = 'WidgetFramework_WidgetRenderer_ShareThisPage';

		$renderers[] = 'WidgetFramework_WidgetRenderer_Users';
		$renderers[] = 'WidgetFramework_WidgetRenderer_Threads';
		$renderers[] = 'WidgetFramework_WidgetRenderer_Poll';
		$renderers[] = 'WidgetFramework_WidgetRenderer_Html';

		/* added 17-04-2011 */
		$renderers[] = 'WidgetFramework_WidgetRenderer_VisitorPanel';

		// since 1.0.9
		$renderers[] = 'WidgetFramework_WidgetRenderer_RecentStatus';
		$renderers[] = 'WidgetFramework_WidgetRenderer_HtmlWithoutWrapper';

		// since 1.0.10
		$renderers[] = 'WidgetFramework_WidgetRenderer_Callback';
		$renderers[] = 'WidgetFramework_WidgetRenderer_Birthday';

		// since 1.2
		$renderers[] = 'WidgetFramework_WidgetRenderer_Template';
		$renderers[] = 'WidgetFramework_WidgetRenderer_TemplateWithoutWrapper';

		//since 1.5
		$renderers[] = 'WidgetFramework_WidgetRenderer_UsersFind';

		// since 2.1
		$renderers[] = 'WidgetFramework_WidgetRenderer_FeedReader';

		// since 2.2
		if (self::xfrmFound())
		{
			// XFRM is installed
			$renderers[] = 'WidgetFramework_WidgetRenderer_XFRM_Resources';
		}

		// since 2.4
		$renderers[] = 'WidgetFramework_WidgetRenderer_UsersStaff';
		$renderers[] = 'WidgetFramework_WidgetRenderer_FacebookFacepile';

		// since 2.4.2
		$renderers[] = 'WidgetFramework_WidgetRenderer_CallbackWithoutWrapper';

		// since 2.6
		$renderers[] = 'WidgetFramework_WidgetRenderer_ProfilePosts';
	}

	public function bootstrap()
	{
		if (defined('WIDGET_FRAMEWORK_LOADED'))
		{
			return false;
		}

		$globalWidgets = $this->_getModelWidget()->getGlobalWidgets(true, false);
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
		if (empty($this->_models[$class]))
		{
			$this->_models[$class] = XenForo_Model::create($class);
		}

		return $this->_models[$class];
	}

	public function addWidgets(array $widgets)
	{
		$this->_widgetCount += count($widgets);
		$positionsAdded = array();

		foreach ($widgets as &$widget)
		{
			if (empty($widget['active']))
			{
				continue;
			}

			$widgetPositions = explode(',', $widget['position']);
			$widget['tab_group'] = (!empty($widget['options']['tab_group']) ? $widget['options']['tab_group'] : '');

			foreach ($widgetPositions as $position)
			{
				$position = trim($position);
				if (empty($position))
				{
					continue;
				}

				if (!isset($this->_positions[$position]))
				{
					$this->_positions[$position] = array(
						'widgets' => array(),
						'prepared' => false,
					);
				}
				$positionsAdded[] = $position;

				$this->_addWidgets_addWidgetToWidgetsByGroup($widget, $this->_positions[$position]['widgets']);

				// get template for hooks data from the widget
				// merge it to template for hook property of this object
				// to use it later (prepare widgets in template creation)
				// since 2.0
				if (!empty($widget['template_for_hooks']))
				{
					foreach ($widget['template_for_hooks'] as $hookPositionCode => $templateForHooks)
					{
						foreach ($templateForHooks as $templateName)
						{
							if (!isset($this->_templateForHooks[$templateName]))
							{
								$this->_templateForHooks[$templateName] = array();
							}

							if (!isset($this->_templateForHooks[$templateName][$hookPositionCode]))
							{
								$this->_templateForHooks[$templateName][$hookPositionCode] = 1;
							}
							else
							{
								$this->_templateForHooks[$templateName][$hookPositionCode]++;
							}
						}
					}
				}
			}
		}

		foreach ($positionsAdded as $position)
		{
			uasort($this->_positions[$position]['widgets'], array(
				'WidgetFramework_Helper_Sort',
				'widgetGroups'
			));
		}
	}

	protected function _addWidgets_addWidgetToWidgetsByGroup(array &$newWidget, array &$widgets, $groupPrefix = '')
	{
		$group = '';

		if (!empty($newWidget['tab_group']))
		{
			$group = $newWidget['tab_group'];

			foreach ($widgets as &$widgetRef)
			{
				if (empty($widgetRef['name']) OR !isset($widgetRef['widgets']))
				{
					// not a group
					continue;
				}

				if ($group === $widgetRef['name'])
				{
					$widgetRef['widgets'][$newWidget['widget_id']] = &$newWidget;

					return true;
				}
				elseif (strpos($group, $widgetRef['name']) === 0)
				{
					$added = $this->_addWidgets_addWidgetToWidgetsByGroup($newWidget, $widgetRef['widgets'], $widgetRef['name']);

					if ($added)
					{
						return true;
					}
				}
			}
		}

		$groupWithoutPrefix = substr($group, strlen($groupPrefix));
		$groupWithoutPrefix = trim($groupWithoutPrefix, '/');

		if (empty($groupWithoutPrefix))
		{
			$widgets[$newWidget['widget_id']] = array(
				'name' => $newWidget['tab_group'],
				'widgets' => array($newWidget['widget_id'] => &$newWidget),

				'widget_id' => $newWidget['widget_id'],
				'position' => $newWidget['position'],
				'tab_group' => $newWidget['tab_group'],
				'display_order' => $newWidget['display_order'],
			);

			return true;
		}
		else
		{
			$groupPrefixParts = preg_split('#/#', $groupPrefix, -1, PREG_SPLIT_NO_EMPTY);
			$groupWithoutPrefixParts = preg_split('#/#', $groupWithoutPrefix, -1, PREG_SPLIT_NO_EMPTY);
			$groupPrefixParts[] = array_shift($groupWithoutPrefixParts);
			$groupPrefixAppended = implode('/', $groupPrefixParts);

			$widgets[$newWidget['widget_id']] = array(
				'name' => $groupPrefixAppended,
				'widgets' => array(),

				'widget_id' => $newWidget['widget_id'],
				'position' => $newWidget['position'],
				'tab_group' => $groupPrefixAppended,
				'display_order' => $newWidget['display_order'],
			);

			return $this->_addWidgets_addWidgetToWidgetsByGroup($newWidget, $widgets, $groupPrefix);
		}
	}

	public function prepareWidgetsFor($templateName, array $params, XenForo_Template_Abstract $template)
	{
		if (WidgetFramework_WidgetRenderer::isIgnoredTemplate($templateName, $params))
		{
			return false;
		}

		$this->_prepareWidgetsFor($templateName, $params, $template);

		return true;
	}

	public function prepareWidgetsForHooksIn($templateName, array $params, XenForo_Template_Abstract $template)
	{
		if (isset($this->_templateForHooks[$templateName]))
		{
			foreach ($this->_templateForHooks[$templateName] as $hookPositionCode => $count)
			{
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
		if (isset($this->_positions[$positionCode]) AND !empty($this->_positions[$positionCode]['prepared']))
		{
			// prepared
			return true;
		}

		if (substr($positionCode, 0, 5) !== 'hook:' AND !empty($this->_positions['all']['widgets']))
		{
			// only append `all` widgets for template position code
			$allWidgets = array();

			foreach ($this->_positions['all']['widgets'] as $allWidgetGroup)
			{
				foreach ($allWidgetGroup['widgets'] as $allWidget)
				{
					$found = false;

					if (!empty($this->_positions[$positionCode]['widgets']))
					{
						$widgetsContainsWidgetId = $this->_getModelWidget()->getWidgetsContainsWidgetId($this->_positions[$positionCode]['widgets'], $allWidget['widget_id']);
						$found = !empty($widgetsContainsWidgetId);
					}

					if (!$found)
					{
						// avoid having duplicated widget in the same position
						// TODO: alter widget_id?
						$allWidget['position'] = $positionCode;
						$allWidgets[$allWidget['widget_id']] = $allWidget;
					}
				}
			}

			$this->addWidgets($allWidgets);
		}

		if (!isset($this->_positions[$positionCode]))
		{
			// still no position data at this point?
			// stop working

			return false;
		}
		$position = &$this->_positions[$positionCode];

		$widgetParams = $this->_prepareWidgetParams($params);
		$this->_prepareWidgetsFor_prepareWidgets($position['widgets'], $positionCode, $widgetParams, $template);

		$position['prepared'] = true;

		return true;
	}

	protected function _prepareWidgetsFor_prepareWidgets(array &$widgets, $positionCode, array $widgetParams, XenForo_Template_Abstract $template)
	{
		foreach ($widgets as &$widget)
		{
			if (isset($widget['widgets']))
			{
				// this is a group
				$this->_prepareWidgetsFor_prepareWidgets($widget['widgets'], $positionCode, $widgetParams, $template);
			}
			else
			{
				// this is a widget
				$renderer = self::getRenderer($widget['class'], false);
				if ($renderer)
				{
					$renderer->prepare($widget, $positionCode, $widgetParams, $template);
				}
			}
		}
	}

	protected function _prepareWidgetParams(array $params)
	{
		if (isset($params[WidgetFramework_WidgetRenderer::PARAM_TEMPLATE_OBJECTS]))
		{
			// this is params array from page container
			if (isset($params['contentTemplate']) AND isset($params[WidgetFramework_WidgetRenderer::PARAM_TEMPLATE_OBJECTS][$params['contentTemplate']]))
			{
				// found content template params, merge it
				$params = array_merge($params[WidgetFramework_WidgetRenderer::PARAM_TEMPLATE_OBJECTS][$params['contentTemplate']]->getParams(), $params);
			}
		}

		return $params;
	}

	public function renderWidgetsFor($templateName, array $params, XenForo_Template_Abstract $template, array &$containerData)
	{
		if (WidgetFramework_WidgetRenderer::isIgnoredTemplate($templateName, $params))
		{
			return false;
		}

		$originalHtml = isset($containerData['sidebar']) ? $containerData['sidebar'] : '';

		$html = $this->_renderWidgetsFor($templateName, array_merge($params, array(WidgetFramework_WidgetRenderer::PARAM_POSITION_CODE => $templateName)), $template, $originalHtml);

		if (defined(WidgetFramework_WidgetRenderer_Empty::NO_VISITOR_PANEL_FLAG))
		{
			// the flag is used to avoid string searching as much as possible
			// the string search is also required to confirm the noVisitorPanel request
			$count = 0;
			$html = str_replace(WidgetFramework_WidgetRenderer_Empty::NO_VISITOR_PANEL_MARKUP, '', $html, $count);

			if ($count > 0)
			{
				$containerData['noVisitorPanel'] = true;
			}
		}

		if ($html != $originalHtml)
		{
			$containerData['sidebar'] = utf8_trim($html);

			if (!empty($containerData['sidebar']) AND self::debugMode())
			{
				$containerData['sidebar'] .= sprintf('<div>Widget Framework is in debug mode<br/>Renderers: %d<br/>Widgets: %d<br/></div>', count($this->_renderers), $this->_widgetCount);
			}
		}

		return true;
	}

	public function renderWidgetsForHook($hookName, array $hookParams, XenForo_Template_Abstract $template, &$hookHtml)
	{
		$hookParams[WidgetFramework_WidgetRenderer::PARAM_PARENT_TEMPLATE] = $template->getTemplateName();
		$hookParams[WidgetFramework_WidgetRenderer::PARAM_POSITION_CODE] = 'hook:' . $hookName;
		$hookParams[WidgetFramework_WidgetRenderer::PARAM_IS_HOOK] = true;

		// sondh@2013-04-02
		// merge hook params with template's params
		$hookParams = array_merge($template->getParams(), $hookParams);

		$hookHtml = $this->_renderWidgetsFor('hook:' . $hookName, $hookParams, $template, $hookHtml);

		return true;
	}

	protected function _renderWidgetsFor($positionCode, array $params, XenForo_Template_Abstract $template, $html)
	{
		$renderArea = false;
		if (WidgetFramework_Option::get('layoutEditorEnabled'))
		{
			$areaSaveParams = array('position' => $positionCode);
			if (!empty($params[WidgetFramework_WidgetRenderer::PARAM_IS_HOOK]))
			{
				// hook position, only render for some hooks
				if ($positionCode == 'hook:wf_widget_page_contents')
				{
					$renderArea = true;
					$areaSaveParams['widget_page_id'] = $params['widgetPage']['node_id'];
				}
				else
				{
					$renderArea = in_array($positionCode, array(
						'hook:ad_above_content',
						'hook:ad_below_content',
					));
				}
			}
			else
			{
				// page position, always render for sidebar
				$renderArea = true;
				if ($positionCode == 'wf_widget_page')
				{
					$areaSaveParams['widget_page_id'] = $params['widgetPage']['node_id'];
				}
			}
		}

		if (!isset($this->_positions[$positionCode]))
		{
			if ($renderArea)
			{
				$this->_positions[$positionCode] = array(
					'widgets' => array(),
					'prepared' => true,
				);
			}
			else
			{
				// stop rendering if no widget configured for this position
				return $html;
			}
		}
		elseif (WidgetFramework_Option::get('layoutEditorEnabled'))
		{
			$renderArea = true;
		}

		$position = &$this->_positions[$positionCode];

		if (empty($position['prepared']))
		{
			// stop rendering if not prepared
			return $html;
		}

		if ($renderArea AND !empty($html))
		{
			$html = WidgetFramework_Helper_String::createArrayOfStrings(array(
				'<div title="',
				new XenForo_Phrase('wf_original_contents'),
				'" class="original-contents Tooltip">',
				$html,
				'</div>'
			));
		}

		$widgetParams = $this->_prepareWidgetParams($params);
		$this->_renderWidgetsFor_renderWidgetsContainer($position, $positionCode, $widgetParams, $template, $html);

		if ($renderArea)
		{
			$conditionalParams = WidgetFramework_Template_Helper_Layout::prepareConditionalParams($widgetParams);
			if (!empty($areaSaveParams['widget_page_id']) AND !empty($conditionalParams['widgetPage']))
			{
				unset($conditionalParams['widgetPage']);
			}

			$areaParams = array(
				'positionCode' => $positionCode,
				'conditionalParams' => $conditionalParams,
				'areaSaveParams' => $areaSaveParams,
				'contents' => $html,
			);

			$html = $template->create('wf_layout_editor_area', $areaParams);
		}

		return $html;
	}

	protected function _renderWidgetsFor_renderWidgetsContainer(array &$widgetsContainer, $positionCode, array $widgetParams, XenForo_Template_Abstract $template, &$html)
	{
		foreach ($widgetsContainer['widgets'] as &$widgetElement)
		{
			$rendered = array();

			if (empty($widgetElement['keys']))
			{
				if (!empty($widgetElement['widgets']))
				{
					// the element is a group
					$widgetElement['keys'] = array_keys($widgetElement['widgets']);
				}
				elseif (!empty($widgetElement['widget_id']))
				{
					// the widget element is a widget
					$widgetElement['keys'] = array($widgetElement['widget_id']);
				}
			}

			$widgetElementName = $widgetElement['name'];
			if (empty($widgetElementName))
			{
				$widgetElementName = 'widgets-' . substr(md5(implode(',', $widgetElement['keys'])), 0, 5);
			}

			foreach ($widgetElement['keys'] as $key)
			{
				if (isset($widgetElement['widgets'][$key]))
				{
					$widget = &$widgetElement['widgets'][$key];
				}
				elseif (!empty($widgetElement['widget_id']) AND $widgetElement['widget_id'] == $key)
				{
					$widget = &$widgetElement;
				}
				$widgetHtml = '';
				$renderer = null;

				if (!empty($widget['widgets']))
				{
					// the sub-element is a group
					// we do not pass the $html itself but we use $widgetHtml instead
					// that means Empty renderer will not work if it is a member a group
					$subWidgetsContainer = array('widgets' => array($key => &$widget));
					$subWidgetParams = $widgetParams;
					$subWidgetParams[WidgetFramework_WidgetRenderer::PARAM_PARENT_GROUP_NAME] = $widgetElementName;
					$this->_renderWidgetsFor_renderWidgetsContainer($subWidgetsContainer, $positionCode, $subWidgetParams, $template, $widgetHtml);
				}
				elseif (!empty($widget['class']))
				{
					$renderer = self::getRenderer($widget['class'], false);

					if (!empty($renderer))
					{
						$widgetHtml = strval($renderer->render($widget, $positionCode, $widgetParams, $template, $html));

						// extra-preparation (this will be run everytime the widget is ready to display)
						// this method can change the final html in some way if it needs to do that
						// the changed html won't be store in the cache (caching is processed inside
						// WidgetFramework_Renderer::render())
						$widget['extraData'] = $renderer->extraPrepare($widget, $widgetHtml);

						$widget['title'] = WidgetFramework_Helper_String::createWidgetTitleDelayed($renderer, $widget);
					}
					elseif (WidgetFramework_Option::get('layoutEditorEnabled'))
					{
						$widgetHtml = new XenForo_Phrase('wf_layout_editor_widget_no_renderer');
					}
				}

				if (!empty($widgetHtml) OR WidgetFramework_Option::get('layoutEditorEnabled'))
				{
					$rendered[$key] = array_merge($widget, array(
						'html' => $widgetHtml,
						'positionCode' => $positionCode,

						WidgetFramework_WidgetRenderer::PARAM_IS_HOOK => !empty($params[WidgetFramework_WidgetRenderer::PARAM_IS_HOOK]),
						WidgetFramework_WidgetRenderer::PARAM_IS_GROUP => !empty($widget['widgets']),
					));

					if (!empty($renderer))
					{
						$rendered[$key]['useWrapper'] = $renderer->useWrapper($widget);
						$rendered[$key]['title'] = WidgetFramework_Helper_String::createWidgetTitleDelayed($renderer, $widget);
					}
				}
			}

			if (count($rendered) > 0)
			{
				$wrapped = $this->_wrapWidgets($rendered, $widgetParams, $template, $widgetElementName);

				if (empty($html))
				{
					$html = $wrapped;
				}
				elseif ($widgetElement['display_order'] >= 0)
				{
					$html = WidgetFramework_Helper_String::createArrayOfStrings(array(
						$html,
						$wrapped
					));
				}
				else
				{
					$html = WidgetFramework_Helper_String::createArrayOfStrings(array(
						$wrapped,
						$html
					));
				}
			}
		}
	}

	protected function _wrapWidgets(array $tabs, array $params, XenForo_Template_Abstract $template, $groupId)
	{
		$normalizedGroupId = WidgetFramework_Helper_String::normalizeHtmlElementId($groupId);
		$groupIdParts = explode('/', $groupId);
		$groupIdLastPart = array_pop($groupIdParts);
		$isColumns = strpos($groupIdLastPart, 'column') === 0;
		$isRows = strpos($groupIdLastPart, 'row') === 0;
		$isRandom = strpos($groupIdLastPart, 'random') === 0;

		if (WidgetFramework_Option::get('layoutEditorEnabled') == false AND $isRandom)
		{
			$randomKey = array_rand($tabs, 1);
			$tabs = array($randomKey => $tabs[$randomKey]);
		}
		$firstTab = reset($tabs);

		$wrapperTemplateName = 'wf_widget_wrapper';
		$wrapperParams = array_merge($params, array(
			'tabs' => $tabs,
			'firstTab' => $firstTab,
			'groupId' => $groupId,

			'isTabs' => (!$isColumns AND !$isRows AND !$isRandom),
			'isColumns' => $isColumns,
			'isRows' => $isRows,
			'isRandom' => $isRandom,
			'normalizedGroupId' => $normalizedGroupId,
		));

		if (WidgetFramework_Option::get('layoutEditorEnabled'))
		{
			$wrapperTemplateName = 'wf_layout_editor_widget_wrapper';

			$wrapperParams['groupSaveParams'] = array('position_widget' => $firstTab['widget_id']);
			if (!empty($firstTab['widget_page_id']))
			{
				$wrapperParams['groupSaveParams']['widget_page_id'] = $firstTab['widget_page_id'];
			}

			$wrapperParams['conditionalParams'] = WidgetFramework_Template_Helper_Layout::prepareConditionalParams($params);
			if (!empty($wrapperParams['groupSaveParams']['widget_page_id']) AND !empty($wrapperParams['conditionalParams']['widgetPage']))
			{
				unset($wrapperParams['conditionalParams']['widgetPage']);
			}

			if (!empty($wrapperParams[WidgetFramework_WidgetRenderer::PARAM_PARENT_GROUP_NAME]))
			{
				$wrapperParams['groupParentGroupNameNormalized'] = WidgetFramework_Helper_String::normalizeHtmlElementId($wrapperParams[WidgetFramework_WidgetRenderer::PARAM_PARENT_GROUP_NAME]);
			}
		}

		$wrapperTemplateObj = $template->create($wrapperTemplateName, $wrapperParams);

		return $wrapperTemplateObj;
	}

	public function getWidgetGroupsByPosition($positionCode)
	{
		if (!isset($this->_positions[$positionCode]))
		{
			return array();
		}

		return $this->_positions[$positionCode]['widgets'];
	}

	public function getRenderedGroupByWidgetId($widgetId)
	{
		if (isset($this->_renderedGroupsByWidgetId[$widgetId]))
		{
			return $this->_renderedGroupsByWidgetId[$widgetId];
		}

		return '';
	}

	public function getRenderedHtmlByWidgetId($widgetId)
	{
		foreach ($this->_positions as &$positionRef)
		{
			if (!empty($positionRef['html']))
			{
				foreach ($positionRef['html'] as $_widgetId => &$widgetHtml)
				{
					if ($_widgetId == $widgetId)
					{
						return $widgetHtml;
					}
				}
			}
		}

		return '';
	}

	public function getRenderedTemplateObjByWidgetId($widgetId)
	{
		$groupId = $this->getRenderedGroupByWidgetId($widgetId);

		if (!empty($groupId))
		{
			return $this->getRenderedTemplateObjByGroupId($groupId);
		}

		return '';
	}

	public function getRenderedTemplateObjByGroupId($groupId)
	{
		if (isset($this->_renderedTemplateObjByGroupId[$groupId]))
		{
			return $this->_renderedTemplateObjByGroupId[$groupId];
		}

		return '';
	}

	protected function _getPermissionCombinationId($useUserCache)
	{
		if ($useUserCache)
		{
			return XenForo_Visitor::getInstance()->get('permission_combination_id');
		}
		else
		{
			return 1;
		}
	}

	protected function _preloadCachedWidget($cacheId, $useUserCache, $useLiveCache)
	{
		// disable cache in debug environment...
		if (self::debugMode())
		{
			return false;
		}

		if ($useLiveCache)
		{
			// no preloading for live cache
			return false;
		}

		$cacheModel = $this->_getModelCache();
		$permissionCombinationId = $this->_getPermissionCombinationId($useUserCache);
		$cachedWidgets = $cacheModel->queueCachedWidgets($cacheId, $permissionCombinationId);

		return true;
	}

	protected function _loadCachedWidget($cacheId, $useUserCache, $useLiveCache)
	{
		// disable cache in debug environment...
		if (self::debugMode() OR WidgetFramework_Option::get('layoutEditorEnabled'))
		{
			return false;
		}

		$cacheModel = $this->_getModelCache();

		$permissionCombinationId = $this->_getPermissionCombinationId($useUserCache);

		if ($useLiveCache)
		{
			return $cacheModel->getLiveCache($cacheId, $permissionCombinationId);
		}
		else
		{
			$cachedWidgets = $cacheModel->getCachedWidgets($cacheId, $permissionCombinationId);

			if (isset($cachedWidgets[$cacheId]))
			{
				return $cachedWidgets[$cacheId];
			}
			else
			{
				return false;
			}
		}
	}

	protected function _saveCachedWidget($cacheId, $html, array $extraData, $useUserCache, $useLiveCache)
	{
		// disable cache in debug environment...
		if (self::debugMode())
		{
			return false;
		}

		$cacheModel = $this->_getModelCache();

		$cacheData = array(
			WidgetFramework_Model_Cache::KEY_HTML => $html,
			WidgetFramework_Model_Cache::KEY_TIME => XenForo_Application::$time,
		);
		if (!empty($extraData))
		{
			$cacheData[WidgetFramework_Model_Cache::KEY_EXTRA_DATA] = $extraData;
		}

		$permissionCombinationId = $this->_getPermissionCombinationId($useUserCache);

		if ($useLiveCache)
		{
			$cacheModel->setLiveCache($cacheData, $cacheId, $permissionCombinationId);
		}
		else
		{
			$cachedWidgets = $cacheModel->getCachedWidgets($cacheId, $permissionCombinationId);
			$cachedWidgets[$cacheId] = $cacheData;

			$cacheModel->setCachedWidgets($cachedWidgets, $cacheId, $permissionCombinationId);
		}
	}

	protected function _removeCachedWidget($widgetId)
	{
		$this->_getModelCache()->invalidateCache($widgetId);
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

	public static function preloadCachedWidget($cacheId, $useUserCache, $useLiveCache)
	{
		return self::getInstance()->_preloadCachedWidget($cacheId, $useUserCache, $useLiveCache);
	}

	public static function loadCachedWidget($cacheId, $useUserCache, $useLiveCache)
	{
		return self::getInstance()->_loadCachedWidget($cacheId, $useUserCache, $useLiveCache);
	}

	public static function preSaveWidget(array $widget, $positionCode, array $params, &$html)
	{
		return self::getInstance()->_getModelCache()->preSaveWidget($widget, $positionCode, $params, $html);
	}

	public static function saveCachedWidget($cacheId, $html, array $extraData, $useUserCache, $useLiveCache)
	{
		self::getInstance()->_saveCachedWidget($cacheId, $html, $extraData, $useUserCache, $useLiveCache);
	}

	public static function clearCachedWidgetById($widgetId)
	{
		$instance = self::getInstance();
		$instance->bootstrap();

		$instance->_removeCachedWidget($widgetId);
	}

	public static function clearCachedWidgetByClass($class)
	{
		$instance = self::getInstance();
		$instance->bootstrap();

		$widgets = $instance->_getModelWidget()->getGlobalWidgets(false, false);

		foreach ($widgets as $widget)
		{
			if ($widget['class'] == $class)
			{
				$instance->_removeCachedWidget($widget['widget_id']);
			}
		}
	}

	/**
	 * @return WidgetFramework_Core
	 */
	public static function getInstance()
	{
		if (!self::$_instance)
		{
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * @return WidgetFramework_WidgetRenderer
	 */
	public static function getRenderer($class, $throw = true)
	{
		$instance = self::getInstance();
		try
		{
			if (in_array($class, $instance->_renderers))
			{
				if (!isset(self::$_rendererInstances[$class]))
				{
					self::$_rendererInstances[$class] = WidgetFramework_WidgetRenderer::create($class);
				}
				return self::$_rendererInstances[$class];
			}
			elseif ($class == 'WidgetFramework_WidgetRenderer_None')
			{
				return WidgetFramework_WidgetRenderer::create($class);
			}
			else
			{
				if ($throw)
				{
					throw new XenForo_Exception(new XenForo_Phrase('wf_invalid_widget_renderer_x', array('renderer' => $class)), true);
				}
			}
		}
		catch (Exception $e)
		{
			if ($throw)
			{
				throw $e;
			}
			else
			{
				return null;
			}
		}
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
		$moderatorModel = XenForo_Model::create('XenForo_Model_Moderator');
		$gmigi = $moderatorModel->getGeneralModeratorInterfaceGroupIds();
		return in_array('resourceModeratorPermissions', $gmigi);
	}

}
