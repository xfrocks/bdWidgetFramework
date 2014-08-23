! function($, window, document, _undefined)
{

	XenForo.WidgetFramework_LayoutEditor_WidgetLink = function($link)
	{
		this.__construct($link);
	};

	XenForo.WidgetFramework_LayoutEditor_WidgetLink.prototype =
	{
		__construct: function($link)
		{
			this.$link = $link;
			this.$controlsParent = $link.parents('.controls').parent();

			this.layoutEditorGroup = '';
			if (this.$controlsParent.is('.WidgetFramework_LayoutEditor_GroupPlaceholder'))
			{
				this.$insertBefore = this.$controlsParent.parents('.WidgetFramework_LayoutEditor_Group');
				this.layoutEditorGroup = this.$insertBefore.attr('id');
			}
			else
			if (this.$controlsParent.is('.WidgetFramework_LayoutEditor_Widget'))
			{
				var $parentParent = this.$controlsParent.parent().parent();

				if ($parentParent.is('.WidgetFramework_LayoutEditor_Group'))
				{
					this.$insertBefore = $parentParent;
					this.layoutEditorGroup = this.$insertBefore.attr('id');
				}
				else
				{
					this.$insertBefore = this.$controlsParent;
				}
			}
			else
			{
				this.$insertBefore = this.$controlsParent;
			}

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

				e.preventDefault();
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
					if (e.ajaxData.widgetId > 0)
					{
						e.preventDefault();

						XenForo.ajax(window.location.href,
						{
							_layoutEditor: 1,
							_widgetFrameworkRenderWidget: 1,
							_widgetId: e.ajaxData.widgetId,
							_layoutEditorGroup: self.layoutEditorGroup,
							_xfResponseType: 'html'
						}, $.context(self, 'renderSuccess'),
						{
							type: 'GET'
						});

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
		},

		renderSuccess: function(ajaxData)
		{
			if (XenForo.hasResponseError(ajaxData))
			{
				return false;
			}

			var self = this;

			new XenForo.ExtLoader(ajaxData, function()
			{
				var $widget = $(ajaxData.templateHtml);
				var $duplicatedGroup = null;
				if ($widget.attr('id'))
				{
					// find and replace id of the duplicated element
					$duplicatedGroup = $('#' + $widget.attr('id')).attr('id', '');
				}
				$widget.xfInsert('insertBefore', self.$insertBefore);

				if ($duplicatedGroup)
				{
					$duplicatedGroup.empty().xfRemove();
				}

				if (ajaxData.groupId)
				{
					var $group = $('#' + ajaxData.groupId);
					var $ajaxDataGroup = $(ajaxData.groupHtml);

					if (ajaxData.groupHtml)
					{
						if ($group.length > 0)
						{
							$ajaxDataGroup.xfInsert('insertBefore', $group);
						}
						else
						{
							$ajaxDataGroup.xfInsert('insertBefore', self.$insertBefore);
						}
					}

					$group.empty().xfRemove();
				}
			});
		}
	};

	// *********************************************************************

	XenForo.register('.WidgetFramework_LayoutEditor_Placeholder .controls > a', 'XenForo.WidgetFramework_LayoutEditor_WidgetLink');
	XenForo.register('.WidgetFramework_LayoutEditor_GroupPlaceholder .controls > a', 'XenForo.WidgetFramework_LayoutEditor_WidgetLink');
	XenForo.register('.WidgetFramework_LayoutEditor_Widget .controls > a', 'XenForo.WidgetFramework_LayoutEditor_WidgetLink');

}(jQuery, this, document);
