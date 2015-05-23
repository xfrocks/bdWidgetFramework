! function($, window, document, _undefined)
{
	var BasePrototype =
	{
		renderStart: function(ajaxData)
		{
			if (!ajaxData.hasRenderData)
			{
				return false;
			}

            var data = {};
            for (var i in ajaxData) {
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

		renderSuccess: function(ajaxData)
		{
			if (XenForo.hasResponseError(ajaxData))
			{
				return false;
			}

			if (!ajaxData.rendered)
			{
				return false;
			}

			var self = this;

			new XenForo.ExtLoader(ajaxData, function()
			{
				for (var renderedId in ajaxData.rendered)
				{
					self.renderSuccess_deleteById(renderedId);
				}

				for (var renderedId in ajaxData.rendered)
				{
					if (ajaxData.rendered[renderedId])
					{
						var $rendered = $(ajaxData.rendered[renderedId]);

						self.renderSuccess_insertRendered($rendered);
					}
				}
			});
		},

		renderSuccess_insertRendered: function($rendered)
		{
			var self = this;

			if ($rendered.is('.widget-container'))
			{
				$rendered.find('.WidgetFramework_LayoutEditor_Widget').each(function()
				{
					self.renderSuccess_deleteById($(this).attr('id'));
				});
			}

			var position = $rendered.data('position');
			var $widgets = null;
			$('.WidgetFramework_LayoutEditor_Area').each(function()
			{
				var $area = $(this);

				if ($area.data('position') == position)
				{
					$widgets = $area.children('.widgets');
					return false;
				}
			});
			if ($widgets === null)
			{
				return false;
			}

			var parentGroup = $rendered.data('parentGroup');
			if (parentGroup)
			{
				var $parentGroup = $widgets.find('.WidgetFramework_LayoutEditor_Group#layout-editor-' + parentGroup);
				if ($parentGroup.length == 1)
				{
					$widgets = $parentGroup.children('.stretcher').children('.widgets');
				}
			}

			var displayOrder = $rendered.data('displayOrder');
			var inserted = false;
			$widgets.children().each(function()
			{
				var $widget = $(this);

				var widgetDisplayOrder = $widget.data('displayOrder');
				if (!widgetDisplayOrder)
				{
					widgetDisplayOrder = 0;
				}

				if (widgetDisplayOrder > displayOrder)
				{
					// insert rendered element before the widget (use display order)
					$rendered.xfInsert('insertBefore', $widget, 'show');
					inserted = true;
					return false;
				}
			});

			if (!inserted)
			{
				// haven't inserted, append to the end of widgets list
				$rendered.xfInsert('appendTo', $widgets, 'show');
			}

			return true;
		},

		renderSuccess_deleteById: function(renderedId)
		{
			if (!renderedId)
			{
				return false;
			}
			if (renderedId.indexOf('layout-editor-') !== 0)
			{
				renderedId = 'layout-editor-' + renderedId;
			}
			var $e = $('#' + renderedId);
			if ($e.length == 0)
			{
				return false;
			}

			var $widgets = $e.closest('.widgets');
			$e.attr('id', '');

			// remove the widget/group
			$e.empty().xfRemove('', function()
			{
				if ($widgets.children().length == 0)
				{
					var $controlsParent = $widgets.closest('.controls-parent');
					if ($controlsParent.is('.WidgetFramework_LayoutEditor_Group'))
					{
						// also remove the parent group because it is empty
						$controlsParent.empty().xfRemove();
					}
				}
			});

			return true;
		}
	};

	XenForo.WidgetFramework_LayoutEditor_WidgetLink = function($link)
	{
		this.__construct($link);
	};

	XenForo.WidgetFramework_LayoutEditor_WidgetLink.prototype = $.extend(
	{
	}, BasePrototype,
	{
		__construct: function($link)
		{
			this.$link = $link;
			$link.click($.context(this, 'click'));
		},

		click: function(e)
		{
			// abort if the event has a modifier key
			if (e.ctrlKey || e.shiftKey || e.altKey)
			{
				return true;
			}

			// abort if the event is a middle or right-button click
			if (e.which > 1)
			{
				return true;
			}

			e.preventDefault();

			if (!this.$widgets)
			{
				var $controlsParent = this.$link.closest('.controls-parent');
				var $widgetsParent = null;
				if ($controlsParent.is('.WidgetFramework_LayoutEditor_Widget'))
				{
					this.$widget = $controlsParent;
					$widgetsParent = $controlsParent.closest('.widget-container');
				}
				else
				{
					$widgetsParent = $controlsParent;
				}
				if ($widgetsParent.is('.WidgetFramework_LayoutEditor_Group'))
				{
					this.$group = $widgetsParent;
					this.$area = $widgetsParent.closest('.WidgetFramework_LayoutEditor_Area');
					this.$widgets = $widgetsParent.children().children('.widgets');
				}
				else
				if ($widgetsParent.is('.WidgetFramework_LayoutEditor_Area'))
				{
					this.$area = $widgetsParent;
					this.$widgets = $widgetsParent.children('.widgets');
				}
			}

			if (this.$link.is('.NoOverlay'))
			{
				XenForo.ajax(this.$link.attr('href'),
				{
				}, $.context(this, 'renderStart'));

				return true;
			}

			if (!this.OverlayLoader)
			{
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

		overlaySuccess: function()
		{
			if (!this.OverlayLoader || !this.OverlayLoader.overlay)
			{
				return true;
			}

			var self = this;
			var overlayApi = this.OverlayLoader.overlay;
			var $overlay = overlayApi.getOverlay();
			var $form = $overlay.find('form.AutoValidator');

			$form.bind(
			{
				AutoValidationComplete: function(e)
				{
					if (e.ajaxData.hasRenderData)
					{
						e.preventDefault();

						self.renderStart(e.ajaxData);

						overlayApi.close();
					}
				}
			});
		},

		deCache: function()
		{
			if (this.OverlayLoader && this.OverlayLoader.overlay)
			{
				this.OverlayLoader.overlay.getTrigger().removeData('overlay');
				this.OverlayLoader.overlay.getOverlay().empty().remove();
			}
			delete (this.OverlayLoader);
		}
	});

	// *********************************************************************

	XenForo.WidgetFramework_LayoutEditor_Widgets = function($target)
	{
        var $dndHandle = null;
        var $widgets = null;

        if ($target.is('.widgets'))
        {
            $widgets = $target;
        }
        else
        {
            $dndHandle = $target;
            $widgets = $dndHandle.closest('.widgets');
            if ($widgets.length == 0) {
                return;
            }
        }

		var isOkie = false;
		var $parent = $widgets.parent();

		if ($parent.is('.WidgetFramework_LayoutEditor_Area'))
		{
			isOkie = true;
		}
		else
		{
			$parent = $parent.parent();
			if ($parent.is('.WidgetFramework_LayoutEditor_Group'))
			{
				isOkie = true;
			}
		}

		if (isOkie)
		{
            if ($dndHandle) {
                $dndHandle.show();
            }

			var existing = $widgets.data('WidgetFramework_LayoutEditor_Widgets');
			if (!existing)
			{
				return this.__construct($widgets, $parent);
			}
		}
	};

	XenForo.WidgetFramework_LayoutEditor_Widgets.prototype = $.extend(
	{
	}, BasePrototype,
	{
		__construct: function($widgets, $parent)
		{
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

		onOut: function(e, ui)
		{
			this.$widgets.removeClass('dnd-over');
		},

		onOver: function(e, ui)
		{
			var $area = null;
			if (!this.parentIsArea)
			{
				$area = this.$parent.closest('.WidgetFramework_LayoutEditor_Area');
			}
			else
			{
				$area = this.$parent;
			}

			$area.find('.dnd-over').removeClass('dnd-over');
			this.$widgets.addClass('dnd-over');
		},

		onUpdate: function(e, ui)
		{
			var $item = ui.item;
			var found = false;
			this.$widgets.children().each(function()
			{
				if ($item.is(this))
				{
					found = true;
					return false;
				}
			});
			if (!found)
			{
                if (this.$widgets.children().length == 0)
                {
                    var $controlsParent = this.$widgets.closest('.controls-parent');
                    if ($controlsParent.is('.WidgetFramework_LayoutEditor_Group'))
                    {
                        // also remove the parent group because it is empty
                        $controlsParent.empty().xfRemove();
                    }
                }

				return;
			}

			var widgetId = 0;
			var moveGroup = '';
			if ($item.is('.WidgetFramework_LayoutEditor_Group'))
			{
				widgetId = parseInt($item.data('firstId'));
				moveGroup = $item.data('group');
			}
			else
			{
				widgetId = parseInt($item.data('id'));
			}
			if (!widgetId)
			{
				return false;
			}

			var negativeDisplayOrderCount = 0;
			var nonControlsParentCount = 0;
			this.$widgets.children().each(function()
			{
				if ($item.is(this))
				{
					return;
				}

				var $this = $(this);
				var displayOrder = $this.data('displayOrder') ? parseInt($this.data('displayOrder')) : 0;
				if (displayOrder < 0)
				{
					negativeDisplayOrderCount++;
				}
				else
				if (!$this.is('.controls-parent'))
				{
					nonControlsParentCount++;
				}
			});

			var relativeDisplayOrder = 0;
			if (negativeDisplayOrderCount > 0 || nonControlsParentCount > 0)
			{
				relativeDisplayOrder = (-1 * negativeDisplayOrderCount) - 1;
			}

			this.$widgets.children().each(function()
			{
				var $this = $(this);

				if ($item.is(this))
				{
					return false;
				}

				if ($this.is('.controls-parent'))
				{
					relativeDisplayOrder++;
				}
				else
				{
					relativeDisplayOrder = Math.max(0, relativeDisplayOrder + 1);
				}
			});

			XenForo.ajax(this.$parent.data('save'),
			{
				widget_id: widgetId,
				relative_display_order: relativeDisplayOrder,
				move_group: moveGroup,
                _layoutEditor: 1
			}, $.context(this, 'saveSuccess'));
		},

		saveSuccess: function(ajaxData)
		{
			if (ajaxData.hasRenderData)
			{
				this.renderStart(ajaxData);
			}
		}
	});

	// *********************************************************************

	XenForo.register('a.wf-le-widget-link', 'XenForo.WidgetFramework_LayoutEditor_WidgetLink');
	XenForo.register('a.dnd-handle', 'XenForo.WidgetFramework_LayoutEditor_Widgets');
    XenForo.register('.widgets', 'XenForo.WidgetFramework_LayoutEditor_Widgets');

}(jQuery, this, document);
