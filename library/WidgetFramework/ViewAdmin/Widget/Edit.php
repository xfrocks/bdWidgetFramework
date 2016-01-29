<?php

class WidgetFramework_ViewAdmin_Widget_Edit extends XenForo_ViewAdmin_Base
{
    public function prepareParams()
    {
        parent::prepareParams();

        if (isset($this->_params[__METHOD__])) {
            return;
        }
        $this->_params[__METHOD__] = true;

        if (empty($this->_params['widget'])) {
            return;
        }
        $widgetRef = &$this->_params['widget'];

        if (!empty($this->_params['conditionalParams'])) {
            $conditionalParams = @json_decode($this->_params['conditionalParams'], true);
            $keyValuePairs = array();
            if (!empty($conditionalParams)) {
                $this->_prepareKeyValuePairs($conditionalParams, $keyValuePairs);
            }

            $explains = array();
            foreach ($keyValuePairs as $pairKey => $pairValue) {
                $explains[] = sprintf('<a href="#" title="{%1$s} == \'%2$s\'" '
                    . 'class="Tooltip">%1$s</a>',
                    $pairKey, htmlspecialchars($pairValue));
            }

            $this->_params['conditionalParamsExplain'] = implode(' ', $explains);

            if (!empty($keyValuePairs['$contentTemplate'])) {
                if (empty($widgetRef['widget_id'])
                    && empty($widgetRef['options']['expression'])
                    && empty($widgetRef['options']['conditional'])
                ) {
                    $widgetRef['options']['conditional']['raw'] = sprintf('{$contentTemplate} == \'%s\'',
                        $keyValuePairs['$contentTemplate']);
                }
            }
        }

        if (!empty($widgetRef['class'])) {
            $renderer = WidgetFramework_Core::getRenderer($widgetRef['class'], false);
        }
        if (empty($renderer)) {
            $renderer = WidgetFramework_Core::getRenderer('WidgetFramework_WidgetRenderer_None', false);
        }
        $widgetRef['_runtime']['configuration'] = $renderer->getConfiguration();
        $renderer->renderOptions($this->_renderer, $this->_params);

        if (!empty($widgetRef['class'])
            && isset($this->_params['renderers'])
        ) {
            $rendererFound = false;

            foreach ($this->_params['renderers'] as &$rendererRef) {
                if ($rendererRef['value'] === $widgetRef['class']) {
                    $rendererFound = true;
                    $rendererRef['selected'] = true;
                }
            }

            if (!$rendererFound) {
                $this->_params['renderers'][] = array(
                    'value' => $widgetRef['class'],
                    'label' => new XenForo_Phrase('wf_unknown_renderer', array('class' => $widgetRef['class'])),
                );
            }
        }

        if (!empty($widgetRef['widget_id'])
            && $widgetRef['widget_id'] > 0
            && !empty($widgetRef['options'][WidgetFramework_DataWriter_Widget::WIDGET_OPTION_ADDON_VERSION_ID])
        ) {
            $this->_prepareWidgetTitlePhrases($widgetRef);
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

        if (!empty($this->_params['siblingWidgets'])) {
            foreach ($this->_params['siblingWidgets'] as &$siblingWidgetRef) {
                $siblingWidgetRenderer = WidgetFramework_Core::getRenderer($siblingWidgetRef['class'], false);
                if (!empty($siblingWidgetRenderer)) {
                    $siblingWidgetRef['_runtime']['title'] = WidgetFramework_Helper_String::createWidgetTitleDelayed(
                        $siblingWidgetRenderer, $siblingWidgetRef);
                }
            }
        }

        if (!empty($this->_params['groups'])) {
            foreach ($this->_params['groups'] as &$groupRef) {
                $groupRenderer = WidgetFramework_Core::getRenderer($groupRef['class'], false);
                if (!empty($groupRenderer)) {
                    $groupRef['_runtime']['title']
                        = WidgetFramework_Helper_String::createWidgetTitleDelayed($groupRenderer, $groupRef);
                }
            }
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
