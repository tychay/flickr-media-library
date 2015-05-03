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

		// handle showing warnings
		$('#flickr-media-library-cpt_options-post_date_map').change(function(ev) {
			var $this = $(this);
			if ( $this.val() == 'lastupdate' ) {
				$('#post_date_map_description').removeClass('hidden');
			} else {
				$('#post_date_map_description').addClass('hidden');
			}
		}).change();
		$('#flickr-media-library-output_options-media_default_link').change(function(ev) {
			var $this = $(this);
			if ( $this.val() != 'flickr' && $this.val() != 'post' ) {
				$('#media_default_link_description').removeClass('hidden');
			} else {
				$('#media_default_link_description').addClass('hidden');
			}
		}).change();
	});

} )( window, window.jQuery );