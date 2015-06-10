<?php

class WidgetFramework_ViewAdmin_Widget_Edit extends XenForo_ViewAdmin_Base
{
    public function prepareParams()
    {
        $widget = &$this->_params['widget'];

        if (!empty($this->_params['conditionalParams'])) {
            $conditionalParams = @json_decode($this->_params['conditionalParams'], true);
            $keyValuePairs = array();
            if (!empty($conditionalParams)) {
                $this->_prepareKeyValuePairs($conditionalParams, $keyValuePairs);
            }

            $explains = array();
            foreach ($keyValuePairs as $pairKey => $pairValue) {
                $explains[] = sprintf('<a href="#" title="{%1$s} == \'%2$s\'" class="Tooltip">%1$s</a>', $pairKey, htmlspecialchars($pairValue));
            }

            $this->_params['conditionalParamsExplain'] = implode(' ', $explains);

            if (!empty($keyValuePairs['$contentTemplate'])) {
                if (empty($widget['widget_id']) AND empty($widget['options']['expression']) AND empty($widget['options']['conditional'])) {
                    $widget['options']['conditional']['raw'] = sprintf('{$contentTemplate} == \'%s\'', $keyValuePairs['$contentTemplate']);
                }
            }
        }

        if (!empty($widget['class']) && isset($this->_params['renderers'])) {
            $rendererFound = false;

            foreach ($this->_params['renderers'] as &$rendererRef) {
                if ($rendererRef['value'] === $widget['class']) {
                    $rendererFound = true;
                    $rendererRef['selected'] = true;
                }
            }

            if (!$rendererFound) {
                $this->_params['renderers'][] = array(
                    'value' => $widget['class'],
                    'label' => new XenForo_Phrase('wf_unknown_renderer', array('class' => $widget['class'])),
                );
            }
        }

        if (!empty($widget['widget_id'])
            && $widget['widget_id'] > 0
            && !empty($widget['options'][WidgetFramework_DataWriter_Widget::WIDGET_OPTION_ADDON_VERSION_ID])
        ) {
            $this->_prepareWidgetTitlePhrases($widget);
        }

        if (!empty($this->_params['renderers'])) {
            foreach (array_keys($this->_params['renderers']) as $rendererKey) {
                if (!empty($this->_params['renderers'][$rendererKey]['is_hidden'])
                    && empty($this->_params['renderers'][$rendererKey]['selected'])
                ) {
                    // remove hidden renderer if it's not already selected
                    unset($this->_params['renderers'][$rendererKey]);
                }
            }
        }

        parent::prepareParams();
    }

    public function renderHtml()
    {
        $widget = &$this->_params['widget'];

        if (!empty($widget['class'])) {
            $renderer = WidgetFramework_Core::getRenderer($widget['class'], false);
        } else {
            $renderer = WidgetFramework_Core::getRenderer('WidgetFramework_WidgetRenderer_None', false);
        }

        if ($renderer) {
            $renderer->renderOptions($this->_renderer, $this->_params);
        }
    }

    protected function _prepareKeyValuePairs(array $array, array &$keyValuePairs, $prefix = '')
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $this->_prepareKeyValuePairs($value, $keyValuePairs, sprintf('%s.%s', $prefix, $key));
            } else {
                $variableName = sprintf('%s.%s', $prefix, $key);
                $variableName = substr($variableName, 1);
                $variableName = '$' . $variableName;

                $keyValuePairs[$variableName] = $value;
            }
        }
    }

    protected function _prepareWidgetTitlePhrases(array $widget)
    {
        /** @var WidgetFramework_Model_Widget $widgetModel */
        $widgetModel = XenForo_Model::create('WidgetFramework_Model_Widget');
        /** @var XenForo_Model_Phrase $phraseModel */
        $phraseModel = $widgetModel->getModelFromCache('XenForo_Model_Phrase');
        $widgetTitlePhrase = $widgetModel->getWidgetTitlePhrase($widget['widget_id']);

        $allPhrases = $phraseModel->getEffectivePhraseValuesInAllLanguages(array($widgetTitlePhrase));
        if (!empty($allPhrases[0][$widgetTitlePhrase])) {
            $widget['title'] = $allPhrases[0][$widgetTitlePhrase];

            $widgetTitlePhrases = array();
            $languages = XenForo_Application::get('languages');
            $defaultLanguageId = XenForo_Application::getOptions()->get('defaultLanguageId');
            foreach ($languages as $languageId => $language) {
                if ($languageId == 0
                    || $languageId == $defaultLanguageId
                ) {
                    continue;
                }

                $widgetTitlePhrases[$languageId] = array(
                    'language_id' => $languageId,
                    'language_title' => $language['title'],
                    'phrase_id' => 0,
                    'phrase_title' => $widgetTitlePhrase,
                    'phrase_text' => '',
                );
            }

            foreach ($allPhrases as $languageId => $phrases) {
                if (!isset($widgetTitlePhrases[$languageId])) {
                    continue;
                }

                $widgetTitlePhrases[$languageId]['phrase_text'] = $phrases[$widgetTitlePhrase];
            }

            if (!empty($widgetTitlePhrase)) {
                $phraseIds = $phraseModel->getPhraseIdInLanguagesByTitle($widgetTitlePhrase);
                foreach ($phraseIds as $languageId => $phraseId) {
                    if (!isset($widgetTitlePhrases[$languageId])) {
                        continue;
                    }

                    $widgetTitlePhrases[$languageId]['phrase_id'] = $phraseId;
                }
            }

            $this->_params['widgetTitlePhrases'] = $widgetTitlePhrases;
        }
    }

}
