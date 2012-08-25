<?php
class WidgetFramework_Core {
	protected static $_instance;
	protected static $_rendererInstances = array();
	
	protected $_renderers = array();
	protected $_widgets = array();
	protected $_positions = array();
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
	}
	
	public function bootstrap() {
		if (defined('WIDGET_FRAMEWORK_LOADED')) return false;
		
		$this->_widgets = $this->getModelFromCache('WidgetFramework_Model_Widget')->getAllWidgets();
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
					);
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
		if (WidgetFramework_WidgetRenderer::isWidgetTemplate($templateName)) {
			return false;
		}
		
		if ('all' != $templateName) {
			$this->prepareWidgetsFor('all', $params, $template);
		}
		
		if (!isset($this->_positions[$templateName])) return false;
		
		$position =& $this->_positions[$templateName];
		if (!empty($position['prepared'])) return false; // prepared

		foreach ($position['widgets'] as &$widgetGroup) {
			foreach ($widgetGroup['widgets'] as &$widget) {
				$renderer = self::getRenderer($widget['class'], false);
				if ($renderer) {
					$renderer->prepare($widget, $templateName, $params, $template);
				}
			}
		}
		
		$position['prepared'] = true;
	}
	
	public function renderWidgetsFor($templateName, array $params, XenForo_Template_Abstract $template, array &$containerData) {
		if (WidgetFramework_WidgetRenderer::isWidgetTemplate($templateName)) {
			return false;
		}
		
		$originalHtml = isset($containerData['sidebar']) ? $containerData['sidebar'] : '';
		$html = $this->_renderWidgetsFor($templateName, $params, $template, $originalHtml);
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
				
				foreach ($widgetGroup['keys'] as $key) {
					$widget =& $widgetGroup['widgets'][$key];
					
					if (!empty($position['html'][$widget['widget_id']])) {
						$tabs[$widget['widget_id']] = array(
							'widget_id' => $widget['widget_id'],
							'title' => $widget['title'],
							'html' => $position['html'][$widget['widget_id']],
							// since 1.0.9
							'class' => $widget['class'],
							'extraData' => $position['extraData'][$widget['widget_id']],
							'options' => $widget['options'],
						);
					}
				}
				
				$html .= WidgetFramework_WidgetRenderer::wrap($tabs, $template, $widgetGroup['name']);
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
	
	protected function _loadCachedWidget($cacheId, $useUserCache = false) {
		// disable cache in debug environment...
		if (self::debugMode()) {
			return false;
		}
		
		$cachedWidgets = $this->getModelFromCache('WidgetFramework_Model_Cache')->getCachedWidgets(
			$this->_getPermissionCombinationId($useUserCache)
		);
		
		if (isset($cachedWidgets[$cacheId])) {
			return $cachedWidgets[$cacheId];
		} else {
			return false;
		}
	}
	
	protected function _saveCachedWidget($cacheId, $html, $useUserCache = false) {
		// disable cache in debug environment...
		if (self::debugMode()) {
			return false;
		}
		
		$permissionCombinationId = $this->_getPermissionCombinationId($useUserCache);
		
		$cachedWidgets = $this->_getModelCache()->getCachedWidgets($permissionCombinationId);
		$cachedWidgets[$cacheId] = array(
			WidgetFramework_Model_Cache::KEY_HTML => $html,
			WidgetFramework_Model_Cache::KEY_TIME => XenForo_Application::$time,
		);
		
		$this->_getModelCache()->setCachedWidgets(
			$cachedWidgets,
			$permissionCombinationId
		);
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
	
	public static function loadCachedWidget($cacheId, $useUserCache = false) {
		return self::getInstance()->_loadCachedWidget($cacheId, $useUserCache);
	}
	
	public static function saveCachedWidget($cacheId, $html, $useUserCache = false) {
		self::getInstance()->_saveCachedWidget($cacheId, $html, $useUserCache); 
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
				throw new XenForo_Exception(new XenForo_Phrase('wf_invalid_widget_renderer_x', array('renderer' => $class)), true);
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