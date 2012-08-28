/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined) {
	XenForo.WidgetRendererOptions = function($element) { this.__construct($element); };
	XenForo.WidgetRendererOptions.prototype = {
		__construct: function($select) {
			this.$select = $select;
			this.url = $select.data('optionsUrl');
			this.$target = $($select.data('optionsTarget'));
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
				{ 'class': this.$select.val(), 'widget_id': $('#widgetId').val() },
				$.context(this, 'ajaxSuccess'),
				{ error: false }
			);
		},

		ajaxSuccess: function(ajaxData) {
			if (ajaxData) {
				this.$target.html(ajaxData.templateHtml);
			} else {
				this.$target.html('');
			}
		}
	};
	
	XenForo.WidgetEditor = function($form) { this.__construct($form); };
	XenForo.WidgetEditor.prototype = {
		__construct: function($form) {
			this.useAjaxSave = true;
			this.$form = $form;
			this.$saveReloadButton = $('#saveReloadButton');
			this.$saveExitButton = $('#saveExitButton');
			this.$widgetId    = $('#widgetId');

			if (this.useAjaxSave && this.getSaveUrl('json')) {
				this.$saveReloadButton
					.val(this.$saveReloadButton.data('ajaxvalue'))
					.click($.context(this, 'saveAjax'));

				this.$saveExitButton
					.click($.context(this, 'saveExit'));
			}
		},

		saveAjax: function(e) {
			var postParams, i, includeTitles;

			if (e) e.preventDefault();

			postParams = this.$form.serializeArray();

			XenForo.ajax(
				this.getSaveUrl('json'),
				postParams,
				$.context(this, 'ajaxSaveSuccess')
			);

			return true;
		},

		saveExit: function(e) {
			return true;
		},

		ajaxSaveSuccess: function(ajaxData, textStatus) {
			if (XenForo.hasResponseError(ajaxData)) return false;
			
			if (ajaxData.saveMessage) {
				XenForo.alert(ajaxData.saveMessage, '', 1000);
			}
			
			this.$widgetId.val(ajaxData.widgetId);
		},

		getSaveUrl: function(reqType) {
			return this.$form.attr('action') + (reqType ? ('.' + reqType) : '');
		}
	};

	XenForo.register('select.WidgetRendererOptions', 'XenForo.WidgetRendererOptions');
	XenForo.register('form#widgetEditor', 'XenForo.WidgetEditor');

}
(jQuery, this, document);