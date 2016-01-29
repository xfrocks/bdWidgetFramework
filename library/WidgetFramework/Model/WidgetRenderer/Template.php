<?php

class WidgetFramework_Model_WidgetRenderer_Template extends XenForo_Model
{
    public function dwPostSave(array $widget, array $widgetOptions)
    {
        if (!empty($widget['widget_id'])) {
            $widgetTemplateTitle = $this->getWidgetTemplateTitle($widget['widget_id']);

            if (!empty($widgetOptions['_text'])) {
                $this->saveTemplate($widgetTemplateTitle, $widgetOptions['_text']);
            } else {
                $this->deleteTemplate($widgetTemplateTitle);
            }
        }
    }

    public function dwPostDelete(array $widget)
    {
        if (!empty($widget['widget_id'])) {
            $widgetTemplateTitle = $this->getWidgetTemplateTitle($widget['widget_id']);
            $this->deleteTemplate($widgetTemplateTitle);
        }
    }

    public function getWidgetTemplateTitle($widgetId)
    {
        if ($widgetId > 0) {
            return '_widget_renderer_template_' . $widgetId;
        } else {
            throw new XenForo_Exception('Cannot get template title for widget without ID.');
        }
    }

    public function getTemplateText($title)
    {
        $template = $this->_getTemplateModel()->getTemplateInStyleByTitle($title, 0);
        if (empty($template)) {
            return '';
        }

        return $template['template'];
    }

    public function saveTemplate($title, $text)
    {
        $existingTemplate = $this->_getTemplateModel()->getTemplateInStyleByTitle($title, 0);

        /** @var XenForo_DataWriter_Template $dw */
        $dw = XenForo_DataWriter::create('XenForo_DataWriter_Template');
        if (!empty($existingTemplate)) {
            $dw->setExistingData($existingTemplate);
        }

        $dw->set('title', $title);
        $dw->set('style_id', 0);
        $dw->set('template', $text);
        $dw->set('disable_modifications', 1, '', array('ignoreInvalidFields' => true));

        return $dw->save();
    }

    public function deleteTemplate($title)
    {
        $existingTemplate = $this->_getTemplateModel()->getTemplateInStyleByTitle($title, 0);
        if (empty($existingTemplate)) {
            return false;
        }

        /** @var XenForo_DataWriter_Template $dw */
        $dw = XenForo_DataWriter::create('XenForo_DataWriter_Template');
        $dw->setExistingData($existingTemplate);
        return $dw->delete();
    }

    /**
     * @return XenForo_Model_Template
     */
    protected function _getTemplateModel()
    {
        return $this->getModelFromCache('XenForo_Model_Template');
    }
}