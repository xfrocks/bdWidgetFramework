<?php

class WidgetFramework_WidgetRenderer_Template extends WidgetFramework_WidgetRenderer
{
	protected static $_extraContainerDatas = array();

	public function extraPrepareTitle(array $widget)
	{
		if (empty($widget['title']) AND isset(self::$_extraContainerDatas[$widget['widget_id']]))
		{
			$extraContainerData = self::$_extraContainerDatas[$widget['widget_id']];

			if (isset($extraContainerData['title']))
			{
				return $extraContainerData['title'];
			}
		}

		return parent::extraPrepareTitle($widget);
	}

	protected function _getConfiguration()
	{
		return array(
			'name' => '[Advanced] Template',
			'options' => array(
				'template' => XenForo_Input::STRING,
				'controller_name' => XenForo_Input::STRING,
				'controller_action' => XeNForo_Input::STRING,
			),
		);
	}

	protected function _getOptionsTemplate()
	{
		return 'wf_widget_options_template';
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return $widget['options']['template'];
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		if (!empty($widget['options']['controller_name']) AND !empty($widget['options']['controller_action']))
		{
			$controllerResponse = $this->_dispatch($widget, $widget['options']['controller_name'], $widget['options']['controller_action']);

			if (!empty($controllerResponse))
			{
				if ($controllerResponse instanceof XenForo_ControllerResponse_View AND !empty($widget['options']['template']))
				{
					$controllerResponse = $this->_findViewForTemplate($widget, $controllerResponse, $widget['options']['template']);
				}

				$renderedView = $this->_renderView($widget, $controllerResponse);

				return $renderedView;
			}
		}
		elseif (!empty($widget['options']['template']) AND $widget['options']['template'] == $renderTemplateObject->getTemplateName())
		{
			return $renderTemplateObject->render();
		}
		else
		{
			return '';
		}
	}

	protected function _dispatch(array $widget, $controllerName, $controllerAction)
	{
		if (empty(WidgetFramework_Listener::$fc))
		{
			return null;
		}

		$routeMatch = new XenForo_RouteMatch($controllerName, $controllerAction);

		// do not use `html` response type to avoid being redirected by
		// XenForo_Controller::canonicalizeRequestUrl
		$routeMatch->setResponseType(get_class($this));

		try
		{
			$controllerResponse = WidgetFramework_Listener::$fc->dispatch($routeMatch);

			return $controllerResponse;
		}
		catch (Exception $e)
		{
			return null;
		}
	}

	protected function _findViewForTemplate(array $widget, XenForo_ControllerResponse_View $controllerResponse, $templateName, $changeTemplateName = true)
	{
		if ($controllerResponse->templateName == $templateName)
		{
			return $controllerResponse;
		}

		if (!empty($controllerResponse->subView))
		{
			$found = $this->_findViewForTemplate($widget, $controllerResponse->subView, $templateName, false);

			if (!empty($found))
			{
				return $found;
			}
		}

		if ($changeTemplateName)
		{
			$controllerResponse->templateName = $templateName;
		}
		else
		{
			return null;
		}
	}

	protected function _renderView(array $widget, XenForo_ControllerResponse_Abstract $controllerResponse)
	{
		if (empty(WidgetFramework_Listener::$fc))
		{
			return null;
		}

		$response = WidgetFramework_Listener::$fc->getResponse();
		$request = WidgetFramework_Listener::$fc->getRequest();
		$viewRenderer = WidgetFramework_Listener::$fc->getDependencies()->getViewRenderer($response, 'html', $request);
		$viewRenderer->setNeedsContainer(false);

		$renderedView = strval(WidgetFramework_Listener::$fc->renderView($controllerResponse, $viewRenderer));

		self::$_extraContainerDatas[$widget['widget_id']] = WidgetFramework_Listener::$fc->getDependencies()->getExtraContainerData();

		return $renderedView;
	}

}
