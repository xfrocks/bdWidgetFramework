/** @param {jQuery} $ jQuery Object */! function($, window, document, _undefined) {

	XenForo.WidgetFramework_WidgetPage_LayoutEditor = function($container) {
		this.__construct($container);
	};
	XenForo.WidgetFramework_WidgetPage_LayoutEditor.prototype = {
		__construct : function($container) {
			this.$container = $container;
			this.$widgets = $container.children('li');

			if (jQuery.fn.prop) {
				// Gridster needs jQuery.prop
				this.setupGridster();
			}
		},

		setupGridster : function() {
			var onDragStop = $.context(this, 'onDragStop');

			this.$widgets.css('top', '').css('left', '').css('width', '').css('height', '').find('input.WidgetFramework_Layout_Input.sizeRow').change($.context(this, 'onInputSizeChange')).end().find('input.WidgetFramework_Layout_Input.sizeCol').change($.context(this, 'onInputSizeChange')).end();

			this.gridster = this.$container.gridster({
				widget_margins : [10, 10],
				widget_base_dimensions : [200, 200],
				draggable : {
					stop : onDragStop
				}
			}).data('gridster');
		},

		onInputSizeChange : function() {
			var gridster = this.gridster;
			var resized = false;

			this.$widgets.each(function() {
				var $widget = $(this);

				var sizex = $widget.find('input.WidgetFramework_Layout_Input.sizeCol').val();
				var sizey = $widget.find('input.WidgetFramework_Layout_Input.sizeRow').val();

				if (sizex != $widget.attr('data-sizex') || sizey != $widget.attr('data-sizey')) {
					gridster.resize_widget($widget, sizex, sizey);
					resized = true;
				}
			});

			if (resized) {
				// because resizing may change other things...
				this.syncFromGridster();
			}
		},

		onDragStop : function() {
			this.syncFromGridster();
		},

		syncFromGridster : function() {
			this.$widgets.each(function() {
				var $widget = $(this);

				$widget.find('input.WidgetFramework_Layout_Input.row').val($widget.attr('data-row') - 1);
				$widget.find('input.WidgetFramework_Layout_Input.col').val($widget.attr('data-col') - 1);
				$widget.find('input.WidgetFramework_Layout_Input.sizeRow').val($widget.attr('data-sizey'));
				$widget.find('input.WidgetFramework_Layout_Input.sizeCol').val($widget.attr('data-sizex'));
			});
		}
	};

	// *********************************************************************

	XenForo.register('.WidgetFramework_WidgetPage_LayoutEditor', 'XenForo.WidgetFramework_WidgetPage_LayoutEditor');

}(jQuery, this, document);
