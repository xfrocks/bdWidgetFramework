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
                list($_renderedId, $_renderedHtml) = WidgetFramework_Listener::getLayoutEditorRendered($renderedId);

                if ($_renderedId > 0) {
                    $output['rendered'][$_renderedId] = $_renderedHtml;
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
