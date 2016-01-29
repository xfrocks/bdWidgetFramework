<?php

class WidgetFramework_WidgetRenderer_Template extends WidgetFramework_WidgetRenderer
{
    protected static $_extraContainerDatas = array();

    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])
            && isset(self::$_extraContainerDatas[$widget['widget_id']])
        ) {
            $extraContainerData = self::$_extraContainerDatas[$widget['widget_id']];

            if (isset($extraContainerData['title'])) {
                return $extraContainerData['title'];
            }
        }

        return parent::extraPrepareTitle($widget);
    }

    protected function _getConfiguration()
    {
        return array(
            'name' => '[Advanced] HTML & Template',

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

    protected function _renderOptions(XenForo_Template_Abstract $template)
    {
        $text = null;

        $widget = $template->getParam('widget');
        if (!empty($widget['widget_id'])) {
            $widgetOptions = $template->getParam('options');

            if (!empty($widgetOptions['html'])) {
                // backward compatibility for Html renderers
                // html should be saved as template after the next save
                $text = $widgetOptions['html'];
            } elseif (!empty($widgetOptions['_text'])) {
                $model = $this->_getWidgetRendererTemplateModel();
                $widgetTemplateTitle = $model->getWidgetTemplateTitle($widget['widget_id']);
                $text = $model->getTemplateText($widgetTemplateTitle);
            }
        }

        $template->setParam('text', $text);

        return parent::_renderOptions($template);
    }

    public function parseOptionsInput(XenForo_Input $input, array $widget)
    {
        $options = parent::parseOptionsInput($input, $widget);

        $options['html'] = null;
        $options['_text'] = $input->filterSingle(self::getNamePrefix() . 'text', XenForo_Input::STRING);

        return $options;
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        if (!empty($widget['options']['_text'])) {
            return $this->_getWidgetRendererTemplateModel()->getWidgetTemplateTitle($widget['widget_id']);
        } elseif (isset($widget['options']['template'])) {
            return $widget['options']['template'];
        } else {
            return false;
        }
    }

    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        $templateTitle = $this->_getRenderTemplate($widget, $positionCode, $params);

        if (!empty($widget['options']['controller_name'])
            && !empty($widget['options']['controller_action'])
        ) {
            $controllerResponse = $this->_dispatch(
                $widget['options']['controller_name'], $widget['options']['controller_action']);

            if (!empty($controllerResponse)) {
                if ($controllerResponse instanceof XenForo_ControllerResponse_View
                    && !empty($templateTitle)
                ) {
                    $controllerResponse = $this->_findViewForTemplate($widget, $controllerResponse, $templateTitle);
                }

                $renderedView = $this->_renderView($widget, $controllerResponse);

                return $renderedView;
            }
        } elseif (!empty($templateTitle)) {
            if ($templateTitle == $renderTemplateObject->getTemplateName()) {
                return $renderTemplateObject->render();
            }
        } else {
            if (!empty($widget['options']['html'])) {
                return $widget['options']['html'];
            }
        }

        return '';
    }

    protected function _dispatch($controllerName, $controllerAction)
    {
        if (empty(WidgetFramework_Listener::$fc)) {
            return null;
        }

        $routeMatch = new XenForo_RouteMatch($controllerName, $controllerAction);

        // do not use `html` response type to avoid being redirected by
        // XenForo_Controller::canonicalizeRequestUrl
        $routeMatch->setResponseType(get_class($this));

        try {
            $controllerResponse = WidgetFramework_Listener::$fc->dispatch($routeMatch);

            return $controllerResponse;
        } catch (Exception $e) {
            return null;
        }
    }

    protected function _findViewForTemplate(
        array $widget,
        XenForo_ControllerResponse_View $controllerResponse,
        $templateName,
        $changeTemplateName = true
    ) {
        if ($controllerResponse->templateName == $templateName) {
            return $controllerResponse;
        }

        if (!empty($controllerResponse->subView)) {
            $found = $this->_findViewForTemplate($widget, $controllerResponse->subView, $templateName, false);

            if (!empty($found)) {
                return $found;
            }
        }

        if ($changeTemplateName) {
            $controllerResponse->templateName = $templateName;
        }

        return null;
    }

    protected function _renderView(array $widget, XenForo_ControllerResponse_Abstract $controllerResponse)
    {
        if (empty(WidgetFramework_Listener::$fc)) {
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

    /**
     * @return WidgetFramework_Model_WidgetRenderer_Template
     */
    protected function _getWidgetRendererTemplateModel()
    {
        return WidgetFramework_Core::getInstance()->getModelFromCache('WidgetFramework_Model_WidgetRenderer_Template');
    }

}
