( function( window, $, undefined ) {
	'use strict';

	//var $window = $( window ),
	//	$document = $( document );

	/* onReady: Handle Settings page for Flickr Media Library. */
	$( function() {

		// bind the checkboxes to hide/show display_<cbox_id> classes
		$('.hide-column-tog', '#adv-settings').change( function() {
			var $this = $(this), id = $this.val();
			if ( $this.prop('checked') ) {
				$('.display_'+id).removeClass('hidden')
					.find('input').prop( 'disabled', false );
			} else {
				$('.display_'+id).addClass('hidden')
					.find('input').prop( 'disabled', true );
			}
		});

	});

} )( window, window.jQuery );