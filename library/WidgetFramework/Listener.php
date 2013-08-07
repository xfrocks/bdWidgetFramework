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
	}

	public static function template_create(&$templateName, array &$params, XenForo_Template_Abstract $template)
	{
		if (defined('WIDGET_FRAMEWORK_LOADED'))
		{
			WidgetFramework_Core::getInstance()->prepareWidgetsFor($templateName, $params, $template);

			WidgetFramework_Core::getInstance()->prepareWidgetsForHooksIn($templateName, $params, $template);

			if ($templateName == 'PAGE_CONTAINER')
			{
				$template->preloadTemplate('wf_hook_moderator_bar');
				$template->preloadTemplate('wf_revealer');
			}
		}
	}

	public static function template_post_render($templateName, &$output, array &$containerData, XenForo_Template_Abstract $template)
	{
		if (defined('WIDGET_FRAMEWORK_LOADED'))
		{
			WidgetFramework_Core::getInstance()->renderWidgetsFor($templateName, $template->getParams(), $template, $containerData);

			// get a copy of container data for widget rendered
			$positionCode = $template->getParam('_WidgetFramework_positionCode');
			if ($positionCode !== null)
			{
				WidgetFramework_WidgetRenderer::setContainerData($template->getParam('widget'), $containerData);
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
			'XenForo_ControllerPublic_Misc',
			'XenForo_ControllerPublic_Thread',

			'XenForo_DataWriter_Discussion_Thread',
			'XenForo_DataWriter_DiscussionMessage_Post',
			'XenForo_DataWriter_DiscussionMessage_ProfilePost',
			'XenForo_DataWriter_User',

			'XenForo_Model_Thread',
			'XenForo_Model_User',

			'bdCache_Model_Cache',
		);

		if (in_array($class, $classesNeedsExtending))
		{
			if (strpos($class, 'XenForo_') === 0)
			{
				$extend[] = str_replace('XenForo_', 'WidgetFramework_Extend_', $class);
			}
			else
			{
				$extend[] = 'WidgetFramework_Extend_' . $class;
			}
		}
	}

	public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
	{
		$hashes += WidgetFramework_FileSums::getHashes();
	}

}
