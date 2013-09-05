/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined) {
	XenForo.WidgetRendererOptions = function($element) { this.__construct($element); };
	XenForo.WidgetRendererOptions.prototype = {
		__construct: function($select) {
			this.$select = $select;
			this.url = $select.data('optionsurl');
			this.$target = $($select.data('optionstarget'));
			if (!this.url || !this.$target.length ) return;

			$select.bind({
				keyup: $.context(this, 'fetchDelayed'),
				change: $.context(this, 'fetch')
			});
		},

		fetchDelayed: function() {
			if (this.delayTimer) {
				clearTimeout(this.delayTimer);
			}

			this.delayTimer = setTimeout($.context(this, 'fetch'), 250);
		},

		fetch: function() {
			if (!this.$select.val().length) {
				this.$target.html('');
				return;
			}

			if (this.xhr) {
				this.xhr.abort();
			}

			this.xhr = XenForo.ajax(
				this.url,
				{
					'class': this.$select.val(),
					'widget_id': $('#widgetId').val(),
					'widget_page_id': $('#widgetPageId').val(),
				},
				$.context(this, 'ajaxSuccess'),
				{ error: false }
			);
		},

		ajaxSuccess: function(ajaxData) {
			var $target = this.$target;
			
			$target.children().empty().remove();
			
			if (XenForo.hasTemplateHtml(ajaxData)) {
				new XenForo.ExtLoader(ajaxData, function() {
					$('<div />').html(ajaxData.templateHtml).children().xfInsert('appendTo', $target, 'show');
				});
			}
		}
	};

	// *********************************************************************

	XenForo.register('select.WidgetRendererOptions', 'XenForo.WidgetRendererOptions');

}
(jQuery, this, document);