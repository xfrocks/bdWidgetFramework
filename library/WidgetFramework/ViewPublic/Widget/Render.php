<?php

class WidgetFramework_ViewPublic_Widget_Render extends XenForo_ViewPublic_Base
{
    public function renderJson()
    {
        /** @var XenForo_ViewRenderer_Json $jsonRenderer */
        $jsonRenderer = $this->_renderer;
        $output = $jsonRenderer->getDefaultOutputArray(__CLASS__, $this->_params, $this->_templateName);

        $output = array_intersect_key($output, array_flip(array(
            'css',
            'js'
        )));

        if (!empty($this->_params['_renderedIds'])) {
            foreach (explode(',', $this->_params['_renderedIds']) as $renderedId) {
                $rendered = WidgetFramework_Listener::getLayoutEditorRendered($renderedId);

                if (is_string($rendered)) {
                    $output['rendered'][$renderedId] = $rendered;
                } elseif (is_array($rendered)) {
                    if (!empty($rendered['normalizedGroupId'])) {
                        $groupRendered = WidgetFramework_Listener::getLayoutEditorRendered($rendered['normalizedGroupId']);
                        if (is_string($groupRendered)) {
                            $output['rendered'][$rendered['normalizedGroupId']] = $groupRendered;
                        }
                    }
                }
            }
        }

        if (!empty($this->_params['_getRenderAsTemplateHtml'])) {
            $output['templateHtml'] = '';

            if (!empty($output['rendered'])) {
                $output['templateHtml'] = reset($output['rendered']);
                unset($output['rendered']);
            }
        }

        return $output;
    }

}
