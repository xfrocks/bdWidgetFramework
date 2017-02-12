//noinspection JSUnusedLocalSymbols,ThisExpressionReferencesGlobalObjectJS
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
                    //noinspection JSValidateTypes
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

    XenForo.WidgetFramework_WidgetRenderer_Threads_LoadMore = function ($container) {
        this.__construct($container);
    };

    XenForo.WidgetFramework_WidgetRenderer_Threads_LoadMore.prototype = {

        __construct: function ($container) {
            this.$container = $container;

            this.loadMoreUrl = $container.data('loadMoreUrl');
            if (!this.loadMoreUrl) {
                return;
            }

            this.contentSeletor = $container.data('contentSelector');
            if (!this.contentSeletor) {
                return;
            }

            this.times = parseInt($container.data('times'));
            this.pageNavSelector = $container.data('pageNavSelector');
            if (!this.pageNavSelector) {
                this.pageNavSelector = '.PageNav';
            }
            this.$detachedPageNav = null;
            this.$window = $(window);
            this.xhr = null;

            //noinspection JSUnresolvedFunction
            this.onScrollRef = $.context(this, 'onScroll');
            this.$window.on('scroll', this.onScrollRef);
            this.onResize();

            //noinspection JSUnresolvedFunction
            this.onResizeRef = $.context(this, 'onResize');
            this.$window.on('resize', this.onResizeRef);
            this.onScroll();
        },

        onResize: function () {
            this._calculateThreshold();
        },

        onScroll: function () {
            //noinspection JSValidateTypes
            var scrollTop = this.$window.scrollTop();
            if (scrollTop > 0 && scrollTop > this.threshold) {
                this.triggerAjaxLoad();
            }
        },

        triggerAjaxLoad: function () {
            if (this.xhr !== null) {
                return;
            }

            if (!this.loadMoreUrl || this.times === 0) {
                return this._deactivate();
            }

            this.$detachedPageNav = this.$container.find(this.pageNavSelector).detach();

            //noinspection JSUnresolvedFunction
            this.xhr = XenForo.ajax(this.loadMoreUrl, {}, $.context(this, 'onAjaxLoadSuccess'));
        },

        onAjaxLoadSuccess: function (ajaxData) {
            var that = this;
            var done = function () {
                that.xhr = null;
            };
            var deactivateThenDone = function () {
                that._deactivate();
                done();
            };
            var calculateThresholdThenDone = function () {
                that._calculateThreshold();
                done();
            };
            //noinspection JSUnresolvedFunction
            var calculateThresholdRef = $.context(that, '_calculateThreshold');

            this.loadMoreUrl = '';
            if (this.times > 0) {
                this.times--;
            }

            if (XenForo.hasResponseError(ajaxData) || !XenForo.hasTemplateHtml(ajaxData)) {
                if (this.$detachedPageNav && this.$detachedPageNav.length > 0) {
                    // restore the recently detached page nav due to error
                    $(this.$detachedPageNav.get(0)).xfInsert('appendTo', this.$container, 'show');
                    this.$detachedPageNav = null;
                }
                return deactivateThenDone();
            }


            new XenForo.ExtLoader(ajaxData, function (data) {
                var $newHtml = $('<div />').html(data.templateHtml);
                var $newContent = $newHtml.find(that.contentSeletor);
                if ($newContent.length == 0) {
                    return deactivateThenDone();
                }

                that.loadMoreUrl = $newContent.data('loadMoreUrl');

                $newContent.children().each(function () {
                    var $child = $(this);

                    $child.find('img').one('load', calculateThresholdRef);

                    $child.appendTo(that.$container).xfActivate();
                });

                return calculateThresholdThenDone();
            });
        },

        _calculateThreshold: function () {
            if (!this.loadMoreUrl || this.times === 0) {
                return this._deactivate();
            }

            var containerTop = this.$container.offset().top;
            var containerHeight = this.$container.height();
            var windowHeight = this.$window.height();
            this.threshold = containerTop + containerHeight - windowHeight * 2;
        },

        _deactivate: function () {
            this.loadMoreUrl = '';
            this.times = 0;

            this.$window.off('scroll', this.onScrollRef);
            this.$window.off('resize', this.onResizeRef);
        }
    };

    // *********************************************************************

    XenForo.register('.WidgetFramework_WidgetRenderer_ProfilePosts form.statusPoster', 'XenForo.ProfilePoster');
    XenForo.register('.WidgetFramework_WidgetRenderer_RecentStatus form.statusPoster', 'XenForo.ProfilePoster');
    XenForo.register('.widget-tabs', 'XenForo.WidgetFramework_Tabs');
    XenForo.register('.WidgetFramework_WidgetRenderer_Threads_ListCompactMore', 'XenForo.WidgetFramework_WidgetRenderer_Threads_ListCompactMore');
    XenForo.register('.WidgetFramework_WidgetRenderer_Threads[data-load-more-url]', 'XenForo.WidgetFramework_WidgetRenderer_Threads_LoadMore');

}(jQuery, this, document); 