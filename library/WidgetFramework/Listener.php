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

	public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
	{
		self::$dependencies = $dependencies;

		if ($dependencies instanceof XenForo_Dependencies_Public)
		{
			XenForo_Template_Helper_Core::$helperCallbacks['widgetframework_snippet'] = array(
				'WidgetFramework_Template_Helper_Core',
				'snippet'
			);

			XenForo_Template_Helper_Core::$helperCallbacks['widgetframework_generatelayoutcss'] = array(
				'WidgetFramework_Template_Helper_Layout',
				'generateCss'
			);
		}
		elseif ($dependencies instanceof XenForo_Dependencies_Admin)
		{
			XenForo_Template_Helper_Core::$helperCallbacks['widgetframework_layoutcontainersize'] = array(
				'WidgetFramework_Template_Helper_Layout',
				'getContainerSize'
			);
			XenForo_Template_Helper_Core::$helperCallbacks['widgetframework_layoutwidgetpositionandsize'] = array(
				'WidgetFramework_Template_Helper_Layout',
				'getWidgetPositionAndSize'
			);
		}

		XenForo_Template_Helper_Core::$helperCallbacks['widgetframework_getoption'] = array(
			'WidgetFramework_Option',
			'get'
		);

		$indexNodeId = WidgetFramework_Option::get('indexNodeId');
		if ($indexNodeId > 0)
		{
			WidgetFramework_Helper_Index::setup();
		}
	}

	public static function navigation_tabs(array &$extraTabs, $selectedTabId)
	{
		$indexNodeId = WidgetFramework_Option::get('indexNodeId');

		if ($indexNodeId > 0 AND XenForo_Template_Helper_Core::styleProperty('wf_homeNavTab'))
		{
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
		if (defined('WIDGET_FRAMEWORK_LOADED'))
		{
			WidgetFramework_Core::getInstance()->prepareWidgetsFor($templateName, $params, $template);

			WidgetFramework_Core::getInstance()->prepareWidgetsForHooksIn($templateName, $params, $template);

			if ($templateName === 'PAGE_CONTAINER')
			{
				$template->preloadTemplate('wf_hook_moderator_bar');
				$template->preloadTemplate('wf_revealer');

				if (WidgetFramework_Option::get('indexNodeId'))
				{
					// preload our links template for performance
					$template->preloadTemplate('wf_home_navtab_links');
				}

				WidgetFramework_Template_Extended::WidgetFramework_setPageContainer($template);

				if (isset($params['contentTemplate']) AND $params['contentTemplate'] === 'wf_widget_page_index' AND empty($params['selectedTabId']))
				{
					// make sure a navtab is selected if user is viewing our (as index) widget page
					if (!XenForo_Template_Helper_Core::styleProperty('wf_homeNavTab'))
					{
						// oh, our "Home" navtab has been disable...
						// try something from $params['tabs'] OR $params['extraTabs']
						if (isset($params['tabs']) AND isset($params['extraTabs']))
						{
							WidgetFramework_Helper_Index::setNavtabSelected($params['tabs'], $params['extraTabs']);
						}
					}
				}
			}
		}
	}

	public static function template_post_render($templateName, &$output, array &$containerData, XenForo_Template_Abstract $template)
	{
		if (defined('WIDGET_FRAMEWORK_LOADED'))
		{
			if ($templateName != 'wf_widget_wrapper')
			{
				WidgetFramework_Core::getInstance()->renderWidgetsFor($templateName, $template->getParams(), $template, $containerData);

				// get a copy of container data for widget rendered
				$positionCode = $template->getParam(WidgetFramework_WidgetRenderer::PARAM_POSITION_CODE);
				if ($positionCode !== null)
				{
					$widget = $template->getParam('widget');

					WidgetFramework_WidgetRenderer::setContainerData($template->getParam('widget'), $containerData);
				}
			}

			if ($templateName === 'PAGE_CONTAINER')
			{
				WidgetFramework_Template_Extended::WidgetFramework_processLateExtraData($output, $containerData, $template);

				if (!empty(self::$_navigationTabsForums))
				{
					$output = str_replace('<!-- navigation_tabs_forums for wf_home_navtab_links -->', self::$_navigationTabsForums, $output);
				}
			}
		}
	}

	public static function template_hook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
	{
		if (defined('WIDGET_FRAMEWORK_LOADED'))
		{
			WidgetFramework_Core::getInstance()->renderWidgetsForHook($hookName, $hookParams, $template, $contents);

			if ($hookName == 'moderator_bar')
			{
				$ourTemplate = $template->create('wf_hook_moderator_bar', $template->getParams());
				$contents .= $ourTemplate->render();
			}
			elseif (in_array($hookName, array(
				'page_container_breadcrumb_top',
				'page_container_content_title_bar'
			)))
			{
				if (!!$template->getParam('widgetPageOptionsBreakContainer'))
				{
					$contents = '';
				}
			}

			static $ignoredHooks = array(
				'ad_sidebar_top', // this one is reserved to show the template name
				'body', // useless
				'footer_links', // ugly!
				'footer_links_legal', // ugly!
				'moderator_bar', // don't mess with our button
				'navigation_tabs_forums', // this just looks ugly!
				'navigation_visitor_tabs_start', // ugly!
				'navigation_visitor_tabs_middle', // ugly!
				'navigation_visitor_tabs_end', // ugly!
				'page_container_head', // stay away from HTML <head />
				'quick_search', // too advanced?
			);
			if (WidgetFramework_Option::get('revealEnabled'))
			{
				if (!in_array($hookName, $ignoredHooks))
				{
					$contentsTrim = trim($contents);
					if (!empty($contentsTrim))
					{
						// show a revealer with negative display order
						$params = array(
							'type' => 'hook',
							'positionCode' => 'hook:' . $hookName,
							'displayOrder' => '-10',
						);
						$revealerTemplate = $template->create('wf_revealer', $params);
						$contents = $revealerTemplate->render() . $contents;
					}

					$params = array(
						'type' => 'hook',
						'positionCode' => 'hook:' . $hookName,
					);
					$revealerTemplate = $template->create('wf_revealer', $params);
					$contents .= $revealerTemplate->render();

					// $ignoredHooks[] = $hookName; // render the revealer once for each hook name
				}
				elseif ($hookName == 'ad_sidebar_top' AND $template->getTemplateName() == 'PAGE_CONTAINER')
				{
					$templateParams = $template->getParams();
					$params = array(
						'type' => 'template',
						'positionCode' => $templateParams['contentTemplate'],
					);
					$revealerTemplate = $template->create('wf_revealer', $params);
					$contents .= $revealerTemplate->render();
				}
			}
		}
	}

	public static function template_hook_navigation_tabs_forums($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
	{
		self::$_navigationTabsForums = $contents;
	}

	public static function init_router_public(XenForo_Dependencies_Abstract $dependencies, XenForo_Router $router)
	{
		$rules = $router->getRules();
		$router->resetRules();

		// insert our filter as the first rule
		$router->addRule(new WidgetFramework_Route_Filter_PageX(), 'WidgetFramework_Route_Filter_PageX');

		foreach ($rules as $ruleName => $rule)
		{
			$router->addRule($rule, $ruleName);
		}
	}

	public static function front_controller_pre_view(XenForo_FrontController $fc, XenForo_ControllerResponse_Abstract &$controllerResponse, XenForo_ViewRenderer_Abstract &$viewRenderer, array &$containerParams)
	{
		self::$fc = $fc;
		self::$viewRenderer = $viewRenderer;

		if ($viewRenderer instanceof XenForo_ViewRenderer_HtmlPublic)
		{
			WidgetFramework_Core::getInstance()->bootstrap();
		}

		if (defined('WIDGET_FRAMEWORK_LOADED'))
		{
			if ($controllerResponse instanceof XenForo_ControllerResponse_View)
			{
				WidgetFramework_WidgetRenderer::markTemplateToProcess($controllerResponse);
			}
		}
	}

	public static function front_controller_post_view(XenForo_FrontController $fc, &$output)
	{
		if (defined('WIDGET_FRAMEWORK_LOADED'))
		{
			WidgetFramework_Core::getInstance()->shutdown();
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

			'XenForo_DataWriter_Discussion_Thread',
			'XenForo_DataWriter_DiscussionMessage_Post',
			'XenForo_DataWriter_DiscussionMessage_ProfilePost',
			'XenForo_DataWriter_User',

			'XenForo_Model_Permission',
			'XenForo_Model_ProfilePost',
			'XenForo_Model_Thread',
			'XenForo_Model_User',

			'XenForo_Route_Prefix_Index',

			'bdCache_Model_Cache',
		);

		if (in_array($class, $classesNeedsExtending))
		{
			$extend[] = 'WidgetFramework_' . $class;
		}
	}

	public static function load_class_view($class, array &$extend)
	{
		static $extended1 = false;
		static $extended2 = false;

		if (defined('WIDGET_FRAMEWORK_LOADED') AND !empty($class))
		{
			// check for empty($class) to avoid a bug with XenForo 1.2.0
			// http://xenforo.com/community/threads/resolvedynamicclass-when-class-is-empty.57064/

			if (empty($extended1))
			{
				$extend[] = 'WidgetFramework_XenForo_View1';
				$extended1 = $class;
			}
			elseif (empty($extended2))
			{
				$extend[] = 'WidgetFramework_XenForo_View2';
				$extended2 = $class;
			}
		}

		if ($class === 'XenForo_ViewAdmin_StyleProperty_List')
		{
			$extend[] = 'WidgetFramework_' . $class;
		}
	}

	public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
	{
		$hashes += WidgetFramework_FileSums::getHashes();
	}

}
