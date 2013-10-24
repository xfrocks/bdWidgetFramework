<?php

class WidgetFramework_Listener
{
	public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
	{
		if ($dependencies instanceof XenForo_Dependencies_Public)
		{
			// we only boot up if we are in the front end
			if (!empty($_SERVER['SCRIPT_NAME']) AND strpos($_SERVER['SCRIPT_NAME'], 'css.php') !== false)
			{
				// it looks like this is a CSS request...
				return;
			}

			WidgetFramework_Core::getInstance()->bootstrap();

			XenForo_Template_Helper_Core::$helperCallbacks['widgetframework_snippet'] = array(
				'WidgetFramework_Template_Helper_Core',
				'snippet'
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

			XenForo_Template_Helper_Core::$helperCallbacks['widgetframework_getoption'] = array(
				'WidgetFramework_Option',
				'get'
			);
		}

		$indexNodeId = WidgetFramework_Option::get('indexNodeId');
		if ($indexNodeId > 0)
		{
			WidgetFramework_Helper_Index::setup();
		}
	}

	public static function navigation_tabs(array &$extraTabs, $selectedTabId)
	{
		$nodeId = WidgetFramework_Option::get('indexNodeId');

		if ($nodeId > 0)
		{
			$tabId = WidgetFramework_Option::get('indexTabId');

			$extraTabs[$tabId] = array(
				'title' => new XenForo_Phrase('home'),
				'href' => XenForo_Link::buildPublicLink('full:widget-page-index'),
				'position' => 'home',
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

				WidgetFramework_Template_Extended::WidgetFramework_setPageContainer($template);
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

	public static function front_controller_pre_view(XenForo_FrontController $fc, XenForo_ControllerResponse_Abstract &$controllerResponse, XenForo_ViewRenderer_Abstract &$viewRenderer, array &$containerParams)
	{
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
			'XenForo_ControllerPublic_Forum',
			'XenForo_ControllerPublic_Index',
			'XenForo_ControllerPublic_Misc',
			'XenForo_ControllerPublic_Thread',

			'XenForo_DataWriter_Discussion_Thread',
			'XenForo_DataWriter_DiscussionMessage_Post',
			'XenForo_DataWriter_DiscussionMessage_ProfilePost',
			'XenForo_DataWriter_User',

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

		if (defined('WIDGET_FRAMEWORK_LOADED'))
		{
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
			else
			{
				// load_class_view got called again!?
				if (WidgetFramework_Core::debugMode())
				{
					// only throw exception in debug mode because I'm not quite sure
					throw new XenForo_Exception(sprintf('[bd] Widget Framework: load_class_view is being called thrice (%s, %s, %s)', $extended1, $extended2, $class));
				}
			}
		}
	}

	public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
	{
		$hashes += WidgetFramework_FileSums::getHashes();
	}

}
