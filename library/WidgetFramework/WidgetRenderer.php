<?php
abstract class WidgetFramework_WidgetRenderer {
	abstract protected function _getConfiguration();
	abstract protected function _getOptionsTemplate();
	abstract protected function _getRenderTemplate(array $widget, $positionCode, array $params);
	abstract protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject);
	
	protected function _renderOptions(XenForo_Template_Abstract $template) { return true; }
	protected function _validateOptionValue($optionKey, &$optionValue) { return true; }
	protected function _getRequiredExternal(array $widget) {
		return array(
			/* example:
			 * 
			 * array('js', 'js/xenforo/cool-effect.js'), 
			 * array('css', 'beautiful-style-with-no-extension'),
			 * 
			 * */
		);
	}
	protected function _prepare(array $widget, $positionCode, array $params) { return true; }
	protected function _getExtraDataLink(array $widget) { return false; }
	
	protected static $_widgetTemplates = array();
	protected $_configuration = false;
	
	public function getConfiguration() {
		if ($this->_configuration === false) {
			$default = array(
				'name' => 'Name',
				'options' => array(),
				'useCache' => false, // output of this widget can be cached
				'useUserCache' => false,  // output should be cached by user permission (must have `useCache` enabled)
				'cacheSeconds' => 0, // cache older will be ignored, 0 means forever
				'useWrapper' => true,
			);
			
			$this->_configuration = XenForo_Application::mapMerge($default, $this->_getConfiguration());
			
			if ($this->_configuration['useWrapper']) {
				$this->_configuration['options']['tab_group'] = XenForo_Input::STRING;
			}
			
			$this->_configuration['options']['expression'] = XenForo_Input::STRING;
			$this->_configuration['options']['expression_debug'] = XenForo_Input::UINT;
		}
		
		return $this->_configuration;
	}
	
	public function getName() {
		$configuration = $this->getConfiguration();
		return $configuration['name'];
	}
	
	public function useWrapper() {
		$configuration = $this->getConfiguration();
		return !empty($configuration['useWrapper']);
	}
	
	public function useCache() {
		$configuration = $this->getConfiguration();
		return !empty($configuration['useCache']);
	}
	
	public function useUserCache() {
		$configuration = $this->getConfiguration();
		return !empty($configuration['useUserCache']);
	}
	
	public function renderOptions(XenForo_ViewRenderer_Abstract $viewRenderer, array &$templateParams) {
		$templateParams['namePrefix'] = self::getNamePrefix();
		$templateParams['options_loaded'] = get_class($this);
		$templateParams['options'] = (!empty($templateParams['widget']['options']))?$templateParams['widget']['options']:array();
		$templateParams['rendererConfiguration'] = $this->getConfiguration();
		
		if ($this->_getOptionsTemplate()) {
			$optionsTemplate = $viewRenderer->createTemplateObject($this->_getOptionsTemplate(), $templateParams);
			
			$this->_renderOptions($optionsTemplate);
			
			$templateParams['optionsRendered'] = $optionsTemplate->render();
		}
	}
	
	public function parseOptionsInput(XenForo_Input $input, array $widget) {
		$configuration = $this->getConfiguration();
		$options = empty($widget['options'])?array():$widget['options'];
		
		foreach ($configuration['options'] as $optionKey => $optionType) {
			$optionValue = $input->filterSingle(self::getNamePrefix() . $optionKey, $optionType);
			if ($this->_validateOptionValue($optionKey, $optionValue) !== false) {
				$options[$optionKey] = $optionValue;
			}
		}

		return $options;
	}
	
	public function prepare(array $widget, $positionCode, array $params, XenForo_Template_Abstract $template) {
		if ($this->useWrapper()) {
			$template->preloadTemplate('wf_widget_wrapper');
			self::$_widgetTemplates['wf_widget_wrapper'] = true;
		}
		
		$renderTemplate = $this->_getRenderTemplate($widget, $positionCode, $params);
		if (!empty($renderTemplate)) {
			$template->preloadTemplate($renderTemplate);
			self::$_widgetTemplates[$renderTemplate] = true;
		}
		
		$requiredExternal = $this->_getRequiredExternal($widget);
		if (!empty($requiredExternal)) {
			foreach ($requiredExternal as $requirement) {
				$template->addRequiredExternal($requirement[0], $requirement[1]);
			}	
		}
		
		$this->_prepare($widget, $positionCode, $params);
	}
	
	protected function _executeExpression($expression, array $params) {
		$expression = trim($expression);
		if (empty($expression)) return true;
		
		$sandbox = @create_function('$params', 'extract($params); return (' . $expression . ');');
		
		if (!empty($sandbox)) {
			return call_user_func($sandbox, $params);
		} else {
			throw new Exception('Syntax error');
		}				
	}
	
	public function render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $template, &$output) {
		$html = false;

		// always check for expression if it's available
		// otherwise the cached widget will show up every where... (the cache test also moved down below this)
		// since 1.2.1
		if (isset($widget['options']['expression'])) {
			try {
				if (!$this->_executeExpression($widget['options']['expression'], $params)) {
					// exepression failed, stop rendering...
					$html = '';
				}
			} catch (Exception $e) {
				// problem executing expression... Stop rendering anyway
				if (!empty($widget['options']['expression_debug'])) {
					$html = $e->getMessage();
				} else {
					$html = '';
				}
			}
		}
		
		// check for cache after expression test
		// since 1.2.1
		if ($html === false AND $this->useCache()) {
			$cached = WidgetFramework_Core::loadCachedWidget($widget['widget_id'], $this->useUserCache());
			if (!empty($cached) AND is_array($cached) AND $this->isCacheUsable($cached)) {
				$html = $cached['html'];
			}
		}
		
		// expression executed just fine
		if ($html === false) {
			$renderTemplate = $this->_getRenderTemplate($widget, $positionCode, $params);
			if (!empty($renderTemplate)) {
				$renderTemplateObject = $template->create($renderTemplate, $params);
				$renderTemplateObject->setParam('widget', $widget);
				$html = $this->_render($widget, $positionCode, $params, $renderTemplateObject);
			} else {
				$html = $this->_render($widget, $positionCode, $params, $template);
			}
			$html = trim($html);
			
			if ($this->useCache()) {
				WidgetFramework_Core::saveCachedWidget($widget['widget_id'], $html, $this->useUserCache());
			}
		}

		if ($this->useWrapper()) {
			// only return html if this renderer use wrapper
			return trim($html);
		} else {
			// directly send output
			$output .= $html;
			return false;
		}
	}
	
	public function extraPrepare(array $widget, &$html) {
		return array(
			'link' => $this->_getExtraDataLink($widget),
			// want extra data here?
			// simply override this method in sub-classes
		);
	}
	
	public function isCacheUsable(array &$cached) {
		$configuration = $this->getConfiguration();
		if (empty($configuration['useCache'])) return false; // what?
		if ($configuration['cacheSeconds'] <= 0) return true;

		$seconds = XenForo_Application::$time - $cached['time'];
		if ($seconds > $configuration['cacheSeconds']) return false;

		return true;
	}
	
	public static function wrap(array $tabs, XenForo_Template_Abstract $template, $groupId = false) {
		if ($groupId === false) $groupId = 'widget-rand-' . rand(1000,9999);
		$groupId = preg_replace('/[^a-zA-Z0-9\-]/', '', $groupId);
		
		$wrapper = $template->create('wf_widget_wrapper', array('tabs' => $tabs, 'groupId' => $groupId));
		
		return $wrapper->render();
	}
	
	public static function create($class) {
		// TODO: do we need to resolve dynamic class?
		/*
		$createClass = XenForo_Application::resolveDynamicClass($class, 'widget_renderer');
		if (!$createClass) {
			throw new XenForo_Exception("Invalid widget renderer '$class' specified");
		}
		*/
		$createClass = $class;

		if (class_exists($createClass)) {
			return new $createClass;
		} else {
			throw new XenForo_Exception("Invalid widget renderer '$class' specified");
		}
	}
	
	public static function getNamePrefix() {
		return 'options_';
	}
	
	public static function isWidgetTemplate($templateName) {
		return !empty(self::$_widgetTemplates[$templateName]);
	}
}