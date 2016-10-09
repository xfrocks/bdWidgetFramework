!function ($, window, document, _undefined) {
    XenForo.WidgetFramework_Tabs = function ($container) {
        var $firstPane = $container.find('.widget-panes > ul > li:first-child');
        var height = $firstPane.height();

        $container.find('.widget-tab > .loading-indicator').css('min-height', height + 'px');
    };

    // *********************************************************************

    XenForo.WidgetFramework_WidgetRenderer_Threads_ListCompactMore = function ($container) {
        var $link = $container.children('a');
        var url = $link.data('url');
        if (!url) {
            return;
        }

        var olContainerSelector = $container.data('selector');
        if (!olContainerSelector) {
            return;
        }
        var $olContainer = $container.parents(olContainerSelector);
        if ($olContainer.length != 1) {
            return;
        }

        $container.show();

        var selfDeactivate = function () {
            $container.hide();
        };

        $link.click(function (e) {
            e.preventDefault();

            var currentUrl = url;
            url = '';

            XenForo.ajax(currentUrl, {}, function (ajaxData) {
                if (XenForo.hasResponseError(ajaxData) || !XenForo.hasTemplateHtml(ajaxData)) {
                    selfDeactivate();
                    return false;
                }

                new XenForo.ExtLoader(ajaxData, function (data) {
                    var $templateHtml = $(data.templateHtml);
                    if (!$templateHtml.is(olContainerSelector)) {
                        selfDeactivate();
                        return;
                    }

                    var $targetOl = $olContainer.children('ol');
                    $templateHtml.children('ol').children('li').each(function () {
                        var $li = $(this);
                        $li.xfInsert('appendTo', $targetOl);
                    });

                    var newUrl = $templateHtml.find('.WidgetFramework_WidgetRenderer_Threads_ListCompactMore > a')
                        .data('url');
                    if (newUrl) {
                        url = newUrl;
                    } else {
                        selfDeactivate();
                    }
                });
            });
        });
    };

    // *********************************************************************

    XenForo.register('.WidgetFramework_WidgetRenderer_ProfilePosts form.statusPoster', 'XenForo.ProfilePoster');
    XenForo.register('.WidgetFramework_WidgetRenderer_RecentStatus form.statusPoster', 'XenForo.ProfilePoster');
    XenForo.register('.widget-tabs', 'XenForo.WidgetFramework_Tabs');
    XenForo.register('.WidgetFramework_WidgetRenderer_Threads_ListCompactMore', 'XenForo.WidgetFramework_WidgetRenderer_Threads_ListCompactMore');

}(jQuery, this, document); 