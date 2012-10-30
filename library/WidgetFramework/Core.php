<?php
class WidgetFramework_Core {
	protected static $_instance;
	protected static $_rendererInstances = array();
	
	protected $_renderers = array();
	protected $_widgets = array();
	protected $_positions = array();
	protected $_templateForHooks = array();
	protected $_models = array();
	
	public function __construct() {
		$renderers = array();
		$this->_registerDefaultRenderers($renderers);
		XenForo_CodeEvent::fire('widget_framework_ready', array(&$renderers));
		foreach ($renderers as $renderer) {
			$this->_renderers[] = $renderer;
		}
	}
	
	protected function _registerDefaultRenderers(array &$renderers) {
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
	}
	
	public function bootstrap() {
		if (defined('WIDGET_FRAMEWORK_LOADED')) return false;
		
		$this->_widgets = $this->_getModelWidget()->getAllWidgets();
		$this->_getModelWidget()->reverseNegativeDisplayOrderWidgets($this->_widgets);
		
		foreach ($this->_widgets as &$widget) {
			if (empty($widget['active'])) continue;
			
			$widgetPositions = explode(',', $widget['position']);
			
			foreach ($widgetPositions as $position) {
				$position = trim($position);
				if (empty($position)) continue;
				
				if (!isset($this->_positions[$position])) {
					$this->_positions[$position] = array(
						'widgets' => array(),
						'prepared' => false,
						'html' => array(),
						// since 1.0.9
						'extraData' => array(),
					);
				}
				
				if (!empty($widget['options']['tab_group'])) {
					// this widget belongs to a tab group
					if (!isset($this->_positions[$position]['widgets']['tabgroup_' . $widget['options']['tab_group']])) {
						$this->_positions[$position]['widgets']['tabgroup_' . $widget['options']['tab_group']] = array(
							'name' => $widget['options']['tab_group'],
							'widgets' => array(),
							'keys' => false,
							'display_order' => $widget['display_order'], // the group uses the first widget's display order
						);
					}
					
					$this->_positions[$position]['widgets']['tabgroup_' . $widget['options']['tab_group']]['widgets'][$widget['widget_id']] =& $widget;
				} else {
					// no tab group
					$this->_positions[$position]['widgets']['widget_' . $widget['widget_id']] = array(
						'name' => 'no-name',
						'widgets' => array(
							$widget['widget_id'] => &$widget,
						),
						'keys' => array($widget['widget_id']),
						'display_order' => $widget['display_order'],
					);
				}
				
				// get template for hooks data from the widget
				// merge it to template for hook property of this object
				// to use it later (prepare widgets in template creation)
				// since 2.0
				if (!empty($widget['template_for_hooks'])) {
					foreach ($widget['template_for_hooks'] as $hookPositionCode => $templateForHooks) {
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

		define('WIDGET_FRAMEWORK_LOADED', 1);
	}
	
	public function shutdown() {
		// shutdown stuff?
	}
	
	public function getModelFromCache($class) {
		if (empty($this->_models[$class])) {
			$this->_models[$class] = XenForo_Model::create($class);
		}
		
		return $this->_models[$class];
	}
	
	public function prepareWidgetsFor($templateName, array $params, XenForo_Template_Abstract $template) {
		if (WidgetFramework_WidgetRenderer::isIgnoredTemplate($templateName)) {
			return false;
		}
		
		$this->_prepareWidgetsFor($templateName, $params, $template);
		$this->_prepareWidgetsFor('all', $params, $template);
		
		return true;
	}
	
	public function prepareWidgetsForHooksIn($templateName, array $params, XenForo_Template_Abstract $template) {
		if (WidgetFramework_WidgetRenderer::isIgnoredTemplate($templateName)) {
			return false;
		}
		
		if (isset($this->_templateForHooks[$templateName])) {
			foreach ($this->_templateForHooks[$templateName] as $hookPositionCode => $count) {
				$this->_prepareWidgetsFor($hookPositionCode, $params, $template);
			}
		}
	}
	
	protected function _prepareWidgetsFor($positionCode, array $params, XenForo_Template_Abstract $template) {
		if (!isset($this->_positions[$positionCode])) return false;
		
		$position =& $this->_positions[$positionCode];
		if (!empty($position['prepared'])) return false; // prepared

		foreach ($position['widgets'] as &$widgetGroup) {
			foreach ($widgetGroup['widgets'] as &$widget) {
				$renderer = self::getRenderer($widget['class'], false);
				if ($renderer) {
					$renderer->prepare($widget, $positionCode, $params, $template);
				}
			}
		}
		
		$position['prepared'] = true;
		
		return true;
	}
	
	public function renderWidgetsFor($templateName, array $params, XenForo_Template_Abstract $template, array &$containerData) {
		if (WidgetFramework_WidgetRenderer::isIgnoredTemplate($templateName)) {
			return false;
		}
		
		$originalHtml = isset($containerData['sidebar']) ? $containerData['sidebar'] : '';
		
		$params['_WidgetFramework_positionCode'] = $templateName;
		$html = $this->_renderWidgetsFor($templateName, $params, $template, $originalHtml);
		
		$params['_WidgetFramework_positionCode'] = 'all';
		$html = $this->_renderWidgetsFor('all', $params, $template, $html);

		if (defined(WidgetFramework_WidgetRenderer_Empty::NO_VISITOR_PANEL_FLAG)) {
			// the flag is used to avoid string searching as much as possible
			// the string search is also required to confirm the noVisitorPanel request
			$count = 0;
			$html = str_replace(WidgetFramework_WidgetRenderer_Empty::NO_VISITOR_PANEL_MARKUP, '', $html, $count);
			
			if ($count > 0) {
				$containerData['noVisitorPanel'] = true;
			}
		}

		if ($html != $originalHtml) {
			$containerData['sidebar'] = $html;
		}
		
		return true;
	}
	
	public function renderWidgetsForHook($hookName, array $hookParams, XenForo_Template_Abstract $template, &$hookHtml) {
		$hookParams['_WidgetFramework_positionCode'] = 'hook:' . $hookName;
		$hookParams['_WidgetFramework_isHook'] = true;
		
		// sondh@2012-10-30
		// added support for $visitor in hook positions
		$visitor = XenForo_Visitor::getInstance();
		$hookParams['visitor'] = $visitor->toArray();
		
		$hookHtml = $this->_renderWidgetsFor('hook:' . $hookName, $hookParams, $template, $hookHtml);
		
		return true;
	}
	
	protected function _renderWidgetsFor($positionCode, array $params, XenForo_Template_Abstract $template, $html) {
		if (!isset($this->_positions[$positionCode])) return $html;
		$position =& $this->_positions[$positionCode];

		if (empty($position['prepared'])) return $html; // stop rendering if not prepared

		foreach ($position['widgets'] as &$widgetGroup) {
			$count = 0;
			$isRandom = ($widgetGroup['name'] == 'random');
			
			if ($widgetGroup['keys'] === false) {
				$widgetGroup['keys'] = array_keys($widgetGroup['widgets']);
				
				if ($isRandom) {
					shuffle($widgetGroup['keys']);
				}
			}
			
			foreach ($widgetGroup['keys'] as $key) {
				$widget =& $widgetGroup['widgets'][$key];
				$renderer = self::getRenderer($widget['class'], false);
				
				if (!isset($position['html'][$widget['widget_id']])) {
					// render the widget now
					if ($renderer) {
						$widgetHtml = $renderer->render($widget, $positionCode, $params, $template, $html);
					} else {
						$widgetHtml = '';
					}
				} else {
					// yay! The widget is rendered already, use it now
					$widgetHtml = $position['html'][$widget['widget_id']];
				}
				
				// extra-preparation (this will be run everytime the widget is ready to display)
				// this method can change the final html in some way if it needs to do that
				// the changed html won't be store in the cache (caching is processed inside WidgetFramework_Renderer::render())
				if ($renderer) {
					$position['extraData'][$widget['widget_id']] = $renderer->extraPrepare($widget, $widgetHtml);
				}
				
				if (!empty($widgetHtml)) {
					$position['html'][$widget['widget_id']] = $widgetHtml; /* store it for later use */
					$count++;
				} else {
					$position['html'][$widget['widget_id']] = '';
				}
				
				if ($isRandom AND $count > 0) {
					// we are in random group
					// at least 1 widget is rendered
					// stop the foreach loop now
					break;
				}
			}

			if ($count > 0) {
				$tabs = array();
				$noWrapper = array();
				
				foreach ($widgetGroup['keys'] as $key) {
					$widget =& $widgetGroup['widgets'][$key];
					$renderer = self::getRenderer($widget['class'], false);
					
					if (!empty($position['html'][$widget['widget_id']])) {
						if ($renderer->useWrapper($widget)) {
							$widgetClass = $widget['class'];
							if (!empty($params['_WidgetFramework_isHook'])) {
								$widgetClass .= ' non-sidebar-widget';
							}
							
							$tabs[$widget['widget_id']] = array(
								'widget_id' => $widget['widget_id'],
								'title' => $widget['title'],
								'html' => $position['html'][$widget['widget_id']],
								// since 1.0.9
								'class' => $widgetClass,
								'extraData' => $position['extraData'][$widget['widget_id']],
								'options' => $widget['options'],
							);
						} else {
							$noWrapper[$widget['widget_id']] = $position['html'][$widget['widget_id']];
						}
					}
				}
				
				$widgetGroupHtml = implode('', $noWrapper);
				$widgetGroupHtml .= WidgetFramework_WidgetRenderer::wrap($tabs, $template, $widgetGroup['name']);
				
				if ($widgetGroup['display_order'] >= 0) {
					$html .= $widgetGroupHtml;
				} else {
					$html = $widgetGroupHtml . $html;
				}
			}
		}
		
		return $html;
	}
	
	protected function _getPermissionCombinationId($useUserCache) {
		if ($useUserCache) {
			return XenForo_Visitor::getInstance()->get('permission_combination_id');
		} else {
			return 1;
		}
	}
	
	protected function _loadCachedWidget($cacheId, $useUserCache, $useLiveCache) {
		// disable cache in debug environment...
		if (self::debugMode()) {
			return false;
		}
		
		$cacheModel = $this->_getModelCache();
		
		$permissionCombinationId = $this->_getPermissionCombinationId($useUserCache);

		if ($useLiveCache) {
			return $cacheModel->getLiveCache($cacheId, $permissionCombinationId);
		} else {
			$cachedWidgets = $cacheModel->getCachedWidgets($permissionCombinationId);
			
			if (isset($cachedWidgets[$cacheId])) {
				return $cachedWidgets[$cacheId];
			} else {
				return false;
			}
		}
	}
	
	protected function _saveCachedWidget($cacheId, $html, $useUserCache , $useLiveCache) {
		// disable cache in debug environment...
		if (self::debugMode()) {
			return false;
		}
		
		$cacheModel = $this->_getModelCache();
		
		$cacheData = array(
			WidgetFramework_Model_Cache::KEY_HTML => $html,
			WidgetFramework_Model_Cache::KEY_TIME => XenForo_Application::$time,
		);
		
		$permissionCombinationId = $this->_getPermissionCombinationId($useUserCache);
		
		if ($useLiveCache) {
			$cacheModel->setLiveCache($cacheData, $cacheId, $permissionCombinationId);
		} else {
			$cachedWidgets = $cacheModel->getCachedWidgets($permissionCombinationId);
			$cachedWidgets[$cacheId] = $cacheData;
			
			$cacheModel->setCachedWidgets(
				$cachedWidgets,
				$permissionCombinationId
			);
		}
	}
	
	protected function _removeCachedWidget($widgetId) {
		$this->_getModelCache()->invalidateCache($widgetId);
	}
	
	/**
	 * @return WidgetFramework_Model_Cache
	 */
	protected function _getModelCache() {
		return $this->getModelFromCache('WidgetFramework_Model_Cache');
	}
	
	/**
	 * @return WidgetFramework_Model_Widget
	 */
	protected function _getModelWidget() {
		return $this->getModelFromCache('WidgetFramework_Model_Widget');
	}
	
	/* ######################################## STATIC FUNCTIONS BELOW ######################################## */
	
	public static function loadCachedWidget($cacheId, $useUserCache, $useLiveCache) {
		return self::getInstance()->_loadCachedWidget($cacheId, $useUserCache, $useLiveCache);
	}
	
	public static function saveCachedWidget($cacheId, $html, $useUserCache, $useLiveCache) {
		self::getInstance()->_saveCachedWidget($cacheId, $html, $useUserCache, $useLiveCache); 
	}
	
	public static function clearCachedWidgetById($widgetId) {
		$instance = self::getInstance();
		$instance->bootstrap();
		
		$instance->_removeCachedWidget($widgetId);
	}
	
	public static function clearCachedWidgetByClass($class) {
		$instance = self::getInstance();
		$instance->bootstrap();
		
		foreach ($instance->_widgets as $widget) {
			if ($widget['class'] == $class) {
				$instance->_removeCachedWidget($widget['widget_id']);
			}
		}
	}
	
	/**
	 * @return WidgetFramework_Core
	 */
	public static function getInstance() {
		if (!self::$_instance) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
	
	public static function getRenderer($class, $throw = true) {
		$instance = self::getInstance();
		try {
			if (in_array($class, $instance->_renderers)) {
				if (!isset(self::$_rendererInstances[$class])) {
					self::$_rendererInstances[$class] = WidgetFramework_WidgetRenderer::create($class);
				}
				return self::$_rendererInstances[$class];
			} else {
				if ($throw) {
					throw new XenForo_Exception(new XenForo_Phrase('wf_invalid_widget_renderer_x', array('renderer' => $class)), true);
				}
			}
		} catch (Exception $e) {
			if ($throw) {
				throw $e;
			} else {
				return false;
			}
		}
	}
	
	public static function getRenderers() {
		return self::getInstance()->_renderers;
	}
	
	public static function debugMode() {
		return XenForo_Application::debugMode();
	}
}