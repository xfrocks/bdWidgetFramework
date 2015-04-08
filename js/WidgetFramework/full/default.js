! function($, window, document, _undefined)
{
    XenForo.WidgetFramework_Tabs = function($container) {
        var $firstPane = $container.find('.widget-panes > ul > li:first-child');
        var height = $firstPane.height();

        $container.find('.widget-panes > ul > li').css('min-height', height + 'px');
    };

	// *********************************************************************

	XenForo.register('.WidgetFramework_WidgetRenderer_ProfilePosts form.statusPoster', 'XenForo.ProfilePoster');
	XenForo.register('.WidgetFramework_WidgetRenderer_RecentStatus form.statusPoster', 'XenForo.ProfilePoster');
    XenForo.register('.widget-tabs', 'XenForo.WidgetFramework_Tabs');

}(jQuery, this, document); 