/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined) {
	
	XenForo.WidgetFramework_WidgetPage_LayoutEditor = function($container) { this.__construct($container); };
	XenForo.WidgetFramework_WidgetPage_LayoutEditor.prototype = {
		__construct: function($container) {
			this.$container = $container;
			
			// TODO
		}
	};

	// *********************************************************************

	XenForo.register('.WidgetFramework_WidgetPage_LayoutEditor', 'XenForo.WidgetFramework_WidgetPage_LayoutEditor');

}
(jQuery, this, document);