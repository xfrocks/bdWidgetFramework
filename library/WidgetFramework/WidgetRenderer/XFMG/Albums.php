<?php

class WidgetFramework_WidgetRenderer_XFMG_Albums extends WidgetFramework_WidgetRenderer
{
    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])) {
            return new XenForo_Phrase('xengallery_recent_albums');
        }

        return parent::extraPrepareTitle($widget);
    }

    protected function _getConfiguration()
    {
        return array(
            'name' => 'XFMG: Recent Albums',
            'options' => array(
                'limit' => XenForo_Input::UINT,
            ),
            'useCache' => true,
            'useUserCache' => true,
            'cacheSeconds' => 300,
            'canAjaxLoad' => true,
        );
    }

    protected function _getOptionsTemplate()
    {
        return 'wf_widget_options_xfmg_albums';
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'wf_widget_xfmg_albums';
    }

    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        if (!WidgetFramework_Core::xfmgFound()) {
            return '';
        }

        $limit = XenForo_Application::getOptions()->get('xengalleryShowTopContributors', 'limit');
        if (!empty($widget['options']['limit'])) {
            $limit = $widget['options']['limit'];
        }

        /** @var XenGallery_Model_Album $albumModel */
        $albumModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenGallery_Model_Album');
        /** @var XenGallery_Model_Media $mediaModel */
        $mediaModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenGallery_Model_Media');
        $visitor = XenForo_Visitor::getInstance();

        $conditions = array(
            'deleted' => false,
            'privacyUserId' => $visitor->getUserId(),
            'viewAlbums' => XenForo_Permission::hasPermission($visitor->getPermissions(), 'xengallery', 'viewAlbums'),
            'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($visitor->toArray()),
            'album_media_count' => '> 0',
            'is_banned' => 0
        );
        $fetchOptions = array(
            'order' => 'new',
            'orderDirection' => 'desc',
            'limit' => $limit,
            'join' => XenGallery_Model_Album::FETCH_PRIVACY
                | XenGallery_Model_Album::FETCH_USER
        );

        $albums = $albumModel->getAlbums($conditions, $fetchOptions);
        $albums = $albumModel->prepareAlbums($albums);
        $renderTemplateObject->setParam('albums', $albums);

        return $renderTemplateObject->render();
    }

    protected function _getExtraDataLink(array $widget)
    {
        return XenForo_Link::buildPublicLink('xengallery/albums');
    }

}