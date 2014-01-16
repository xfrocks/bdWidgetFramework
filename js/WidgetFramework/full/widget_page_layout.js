/** @param {jQuery} $ jQuery Object */! function($, window, document, _undefined)
{

	XenForo.WidgetFramework_WidgetPage_LayoutEditor = function($container)
	{
		this.__construct($container);
	};
	XenForo.WidgetFramework_WidgetPage_LayoutEditor.prototype =
	{
		__construct: function($container)
		{
			this.$container = $container;
			this.$widgets = $container.children('li');

			if (jQuery.fn.prop)
			{
				// Gridster needs jQuery.prop
				this.setupGridster();
			}
		},

		setupGridster: function()
		{
			/*
			 * changes to gridster
			 * - coords renamed to gcoords
			 * - added `$(event.currentTarget).is('.sidebar')` to fn.ignore_drag
			 * - fixed add_faux_cols
			 * - added move_widget_sidebar
			 */
			this.$container.css('width', '').css('height', '');
			this.$widgets.css('top', '').css('left', '').css('width', '').css('height', '');

			var $sizeRow = this.$widgets.find('input.WidgetFramework_Layout_Input.sizeRow');
			$sizeRow.change($.context(this, 'onInputSizeChange'));

			var $sizeCol = this.$widgets.find('input.WidgetFramework_Layout_Input.sizeCol');
			$sizeCol.change($.context(this, 'onInputSizeChange')).end();

			var $positionInputs = this.$widgets.find('.WidgetFramework_WidgetPage_WidgetBlock_PositionInputs');
			$positionInputs.hide();

			var $sizeInputsLabel = this.$widgets.find('.WidgetFramework_WidgetPage_WidgetBlock_SizeInputs label');
			$sizeInputsLabel.hide();

			var margin = 10;
			var blockWidth = 200;
			var blockHeight = blockWidth;
			// calculate block size for best display
			var usableWidth = this.$container.parents('.xenForm').width();
			var currentCols = 0;
			this.$widgets.each(function()
			{
				var $widget = $(this);

				var widgetCol = parseInt($widget.data('col'));
				var widgetSizeCol = parseInt($widget.data('sizex'));

				if (!isNaN(widgetCol) && !isNaN(widgetSizeCol))
				{
					currentCols = Math.max(currentCols, widgetCol + widgetSizeCol);
				}
			});

			var idealBlockWidth = blockWidth;
			while (idealBlockWidth > 10 && ((idealBlockWidth + margin) * currentCols - margin) > usableWidth)
			{
				idealBlockWidth -= 10;
			}
			if (idealBlockWidth < blockWidth)
			{
				blockWidth = idealBlockWidth;
			}

			this.gridster = this.$container.gridster(
			{
				widget_margins: [margin, margin],
				widget_base_dimensions: [blockWidth, blockHeight],
				draggable:
				{
					stop: $.context(this, 'onDragStop')
				}
			}).data('gridster');

			this.$container.addClass('gridsterized');
		},

		onInputSizeChange: function()
		{
			var gridster = this.gridster;
			var resized = false;

			this.$widgets.each(function()
			{
				var $widget = $(this);

				var sizex = parseInt($widget.find('input.WidgetFramework_Layout_Input.sizeCol').val());
				var sizey = parseInt($widget.find('input.WidgetFramework_Layout_Input.sizeRow').val());

				if (isNaN(sizex) || isNaN(sizey))
				{
					return;
				}

				if (sizex != $widget.attr('data-sizex') || sizey != $widget.attr('data-sizey'))
				{
					gridster.resize_widget($widget, sizex, sizey, false);
					resized = true;
				}
			});

			if (resized)
			{
				// because resizing may change other things...
				this.syncFromGridster();
			}
		},

		onDragStop: function()
		{
			this.syncFromGridster();
		},

		syncFromGridster: function()
		{
			this.$widgets.each(function()
			{
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
