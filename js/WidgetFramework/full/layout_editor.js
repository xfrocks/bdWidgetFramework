//noinspection ThisExpressionReferencesGlobalObjectJS,JSUnusedLocalSymbols
!function ($, window, document, _undefined) {
    var BasePrototype =
    {
        renderStart: function (ajaxData) {
            if (!ajaxData['_hasRenderData']) {
                return false;
            }

            var data = {};
            for (var i in ajaxData) {
                if (!ajaxData.hasOwnProperty(i)) {
                    continue;
                }

                var typeOf = typeof ajaxData[i];
                switch (typeOf) {
                    case 'object':
                    case 'array':
                        break;
                    default:
                        data[i] = ajaxData[i];
                }
            }

            XenForo.ajax(window.location.href, $.extend(data,
                {
                    _layoutEditor: 1,
                    _xfResponseType: 'html'
                }), $.context(this, 'renderSuccess'),
                {
                    type: 'GET'
                });
        },

        renderSuccess: function (ajaxData) {
            if (XenForo.hasResponseError(ajaxData)) {
                return false;
            }

            if (!ajaxData['rendered']) {
                return false;
            }

            var self = this;

            new XenForo.ExtLoader(ajaxData, function () {
                for (var tmp1 in ajaxData['rendered']) {
                    if (!ajaxData['rendered'].hasOwnProperty(tmp1)) {
                        continue;
                    }

                    self.renderSuccess_deleteById(tmp1);
                }

                for (var tmp2 in ajaxData['rendered']) {
                    if (!ajaxData['rendered'].hasOwnProperty(tmp2)) {
                        continue;
                    }

                    if (!!ajaxData['rendered'][tmp2]) {
                        self.renderSuccess_insertRendered($(ajaxData['rendered'][tmp2]));
                    }
                }
            });
        },

        renderSuccess_insertRendered: function ($rendered) {
            var self = this;

            var position = $rendered.data('position');
            var $widgets = null;
            $('.WidgetFramework_LayoutEditor_Area').each(function () {
                var $area = $(this);

                if ($area.data('position') == position) {
                    //noinspection JSValidateTypes
                    $widgets = $area.children('.widgets');
                    return false;
                }
            });
            if ($widgets === null) {
                return false;
            }

            var parentGroupId = $rendered.data('parentGroupId');
            if (parentGroupId) {
                var $parentGroup = $widgets.find('#layout-editor-' + parentGroupId);
                if ($parentGroup.length == 1) {
                    $widgets = $parentGroup.children('.widgets');
                }
            }

            var thisId = parseInt($rendered.data('widgetId'));
            var thisDisplayOrder = parseInt($rendered.data('displayOrder'));
            var inserted = false;
            $widgets.children().each(function () {
                var $widget = $(this);

                var widgetId = parseInt($widget.data('widgetId'));
                var widgetDisplayOrder = parseInt($widget.data('displayOrder'));

                if (widgetDisplayOrder > thisDisplayOrder
                    || (widgetDisplayOrder == thisDisplayOrder
                        && widgetId > thisId
                    )
                ) {
                    // insert rendered element before the widget
                    $rendered.xfInsert('insertBefore', $widget, 'show');
                    inserted = true;
                    return false;
                }
            });

            if (!inserted) {
                // haven't inserted, append to the end of widgets list
                $rendered.xfInsert('appendTo', $widgets, 'show');
            }

            return true;
        },

        renderSuccess_deleteById: function (renderedId) {
            if (!renderedId) {
                return false;
            }
            var $e = $('#layout-editor-' + renderedId);
            if ($e.length == 0) {
                return false;
            }

            var $widgets = $e.closest('.widgets');
            $e.attr('id', '');

            // remove the widget/group
            $e.empty().xfRemove('', function () {
                if ($widgets.children('[id]').length == 0) {
                    var $controlsParent = $widgets.closest('.controls-parent');
                    if ($controlsParent.is('.WidgetFramework_LayoutEditor_Group')) {
                        // also remove the parent group because it is empty
                        $controlsParent.empty().xfRemove();
                    }
                }
            });

            return true;
        }
    };

    // *********************************************************************

    XenForo.WidgetFramework_LayoutEditor_WidgetLink = function ($link) {
        this.__construct($link);
    };

    XenForo.WidgetFramework_LayoutEditor_WidgetLink.prototype = $.extend(
        {}, BasePrototype,
        {
            __construct: function ($link) {
                this.$link = $link;
                $link.click($.context(this, 'click'));
            },

            click: function (e) {
                // abort if the event has a modifier key
                if (e.ctrlKey || e.shiftKey || e.altKey) {
                    return true;
                }

                // abort if the event is a middle or right-button click
                if (e.which > 1) {
                    return true;
                }

                e.preventDefault();

                if (!this.$widgets) {
                    var $controlsParent = this.$link.closest('.controls-parent');
                    var $widgetsParent = null;

                    if ($controlsParent.is('.WidgetFramework_LayoutEditor_Widget')) {
                        $widgetsParent = $controlsParent.closest('.widget-container');
                    } else {
                        $widgetsParent = $controlsParent;
                    }

                    if ($widgetsParent.is('.WidgetFramework_LayoutEditor_Group')) {
                        this.$widgets = $widgetsParent.children().children('.widgets');
                    } else if ($widgetsParent.is('.WidgetFramework_LayoutEditor_Area')) {
                        this.$widgets = $widgetsParent.children('.widgets');
                    }
                }

                if (this.$link.is('.NoOverlay')) {
                    XenForo.ajax(this.$link.attr('href'),
                        {}, $.context(this, 'renderStart'));

                    return true;
                }

                if (!this.OverlayLoader) {
                    var href = this.$link.attr('href');
                    href += '&_layoutEditor=1';
                    this.$link.data('href', href);

                    var options =
                    {
                        onClose: $.context(this, 'deCache'),
                        speed: XenForo.speed.fast
                    };

                    this.OverlayLoader = new XenForo.OverlayLoader(this.$link, false, options);
                    this.OverlayLoader.load($.context(this, 'overlaySuccess'));

                    return true;
                }

                this.OverlayLoader.show();
            },

            overlaySuccess: function () {
                if (!this.OverlayLoader || !this.OverlayLoader.overlay) {
                    return true;
                }

                var self = this;
                var overlayApi = this.OverlayLoader.overlay;
                var $overlay = overlayApi.getOverlay();
                var $form = $overlay.find('form.AutoValidator');

                $form.bind(
                    {
                        AutoValidationComplete: function (e) {
                            if (e.ajaxData['_hasRenderData']) {
                                e.preventDefault();

                                overlayApi.close();

                                if (self.$link.data('simple')) {
                                    // adding widget to a simple area
                                    // do not render, just refresh the page
                                    window.location.reload();
                                } else {
                                    self.renderStart(e.ajaxData);
                                }
                            }
                        }
                    });
            },

            deCache: function () {
                if (this.OverlayLoader && this.OverlayLoader.overlay) {
                    this.OverlayLoader.overlay.getTrigger().removeData('overlay');
                    this.OverlayLoader.overlay.getOverlay().empty().remove();
                }
                delete (this.OverlayLoader);
            }
        });

    // *********************************************************************

    XenForo.WidgetFramework_LayoutEditor_ButtonLink = function ($link) {
        this.__construct($link);
    };

    XenForo.WidgetFramework_LayoutEditor_ButtonLink.prototype = {
        __construct: function ($link) {
            this.$link = $link;

            $link.click($.context(this, 'click'));
        },

        click: function (e) {
            var $overlay = $('<div class="xenForm formOverlay" />');
            var $ul = $('<ul />').appendTo($overlay);

            $('.WidgetFramework_LayoutEditor_AreaSimple').each(function () {
                var $simpleArea = $(this);

                //noinspection JSValidateTypes
                $('<a />')
                    .addClass('wf-le-widget-link')
                    .attr('href', $simpleArea.data('href'))
                    .text($simpleArea.data('position'))
                    .data('simple', true)
                    .appendTo($('<li />'))
                    .parent().appendTo($ul);
            });

            var overlay = XenForo.createOverlay(this.$link, $overlay);
            overlay.load();

            e.preventDefault();
        }
    };

    // *********************************************************************

    XenForo.WidgetFramework_LayoutEditor_Widgets = function ($target) {
        var $dndHandle = null;
        var $widgets = null;

        if ($target.is('.widgets')) {
            $widgets = $target;
        }
        else {
            $dndHandle = $target;
            $widgets = $dndHandle.closest('.widgets');
            if ($widgets.length == 0) {
                return;
            }
        }

        var isOkie = false;
        var $parent = $widgets.parent();

        if ($parent.is('.WidgetFramework_LayoutEditor_Area')) {
            isOkie = true;
        }
        else {
            if ($parent.is('.WidgetFramework_LayoutEditor_Group')) {
                isOkie = true;
            }
        }

        if (isOkie) {
            if ($dndHandle) {
                $dndHandle.css('display', 'block');
            }

            var existing = $widgets.data('WidgetFramework_LayoutEditor_Widgets');
            if (!existing) {
                return this.__construct($widgets, $parent);
            }
        }
    };

    XenForo.WidgetFramework_LayoutEditor_Widgets.prototype = $.extend(
        {}, BasePrototype,
        {
            __construct: function ($widgets, $parent) {
                $widgets.addClass('dnd-widgets');

                this.$widgets = $widgets;
                this.$parent = $parent;
                this.parentIsArea = $parent.is('.WidgetFramework_LayoutEditor_Area');
                $widgets.data('WidgetFramework_LayoutEditor_Widgets', this);

                $widgets.sortable(
                    {
                        connectWith: '.dnd-widgets',
                        cursor: 'move',
                        handle: '.dnd-handle',
                        tolerance: 'pointer',

                        out: $.context(this, 'onOut'),
                        over: $.context(this, 'onOver'),
                        update: $.context(this, 'onUpdate')
                    });

                return true;
            },

            onOut: function () {
                this.$widgets.removeClass('dnd-over');
            },

            onOver: function () {
                var $area = null;
                if (!this.parentIsArea) {
                    $area = this.$parent.closest('.WidgetFramework_LayoutEditor_Area');
                }
                else {
                    $area = this.$parent;
                }

                $area.find('.dnd-over').removeClass('dnd-over');
                this.$widgets.addClass('dnd-over');
            },

            onUpdate: function (e, ui) {
                var $item = ui.item;
                var found = false;
                this.$widgets.children().each(function () {
                    if ($item.is(this)) {
                        found = true;
                        return false;
                    }
                });
                if (!found) {
                    if (this.$widgets.children().length == 0) {
                        var $controlsParent = this.$widgets.closest('.controls-parent');
                        if ($controlsParent.is('.WidgetFramework_LayoutEditor_Group')) {
                            // also remove the parent group because it is empty
                            $controlsParent.empty().xfRemove();
                        }
                    }

                    return;
                }

                var widgetId = parseInt($item.data('widgetId'));
                if (!widgetId) {
                    return false;
                }

                var negativeDisplayOrderCount = 0;
                this.$widgets.children().each(function () {
                    if ($item.is(this)) {
                        return;
                    }

                    var $this = $(this);
                    var displayOrder = parseInt($this.data('displayOrder'));
                    if (displayOrder < 0) {
                        negativeDisplayOrderCount++;
                    }
                });

                var relativeDisplayOrder = 0;
                if (negativeDisplayOrderCount > 0) {
                    relativeDisplayOrder = (-1 * negativeDisplayOrderCount) - 1;
                }

                this.$widgets.children().each(function () {
                    if ($item.is(this)) {
                        return false;
                    }

                    relativeDisplayOrder = Math.max(0, relativeDisplayOrder + 1);
                });

                var saveSuccess = $.context(this, 'saveSuccess');
                XenForo.ajax(
                    this.$parent.data('save'),
                    {
                        widget_id: widgetId,
                        relative_display_order: relativeDisplayOrder,
                        _layoutEditor: 1
                    },
                    function(ajaxData) {
                        // $item.empty().xfRemove();
                        saveSuccess(ajaxData);
                    }
                );
            },

            saveSuccess: function (ajaxData) {
                this.renderStart(ajaxData);
            }
        });

    // *********************************************************************

    XenForo.register('a.wf-le-widget-link', 'XenForo.WidgetFramework_LayoutEditor_WidgetLink');
    XenForo.register('a.wf-le-button-link', 'XenForo.WidgetFramework_LayoutEditor_ButtonLink');
    XenForo.register('a.dnd-handle', 'XenForo.WidgetFramework_LayoutEditor_Widgets');
    XenForo.register('.widgets', 'XenForo.WidgetFramework_LayoutEditor_Widgets');

}(jQuery, this, document);
