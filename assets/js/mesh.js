var mesh = mesh || {};

mesh.frontend = function ( $ ) {

	var $body     = $('body'),
		$window   = $(window),
		$equalize = $('.mesh_section [data-equalizer]'),

		self;

	$.fn.removeInlineStyle = function( style ) {
		var search = new RegExp( style + '[^;]+;?', 'g' ),
			styles = this.attr('style');

        if ( styles !== undefined ) {
	        this.attr('style', styles.replace( search, '' ) );
        }

        return this;
	};

	return {

		/**
		 * Initialize our script
		 */
		init : function() {
			self = mesh.frontend;

			if ( 'object' != typeof( Foundation ) ) {
				$window
					.load( self.mesh_equalize_init )
					.resize( self.mesh_equalize_init );
			} else if ( 'object' == typeof( Foundation ) ) {
				if ( 'function' != typeof Foundation.libs.equalizer.equalize && 'function' != typeof Foundation.Equalizer ) {
					$window
						.load( self.mesh_equalize_init )
						.resize( self.mesh_equalize_init );
				}
			}

		},

		mesh_equalize_init : function() {
			$equalize.each( self.mesh_equalize );
		},

		mesh_equalize : function() {

			var $this     = $(this),
				$childs   = $('[data-equalizer-watch]', $this),
				eq_height = 0;

			$childs.each(function() {
				$(this).removeInlineStyle( 'height' );
			});

			$childs.each( function() {
				var this_height = $(this).height();

				eq_height = this_height > eq_height ? this_height : eq_height;
			}).height(eq_height);

		}
	};
} ( jQuery );

jQuery(function( $ ) {
    mesh.frontend.init();
});
//# sourceMappingURL=mesh.js.map