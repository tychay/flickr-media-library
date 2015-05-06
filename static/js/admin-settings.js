( function( window, $, undefined ) {
	'use strict';

	//var $window = $( window ),
	//	$document = $( document );

	/* onReady: Handle Settings page for Flickr Media Library. */
	$( function() {
		// Full screen thickboxes
		// https://codex.wordpress.org/Javascript_Reference/ThickBox
		$('a.TB_fullscreen').click( function(e) {
			var $this = $(this), $window = $(window);
			// save true href the first time
			if ( !$this._href ) { $this._href = $this.attr('href'); }
			$this.attr('href', $this._href+'?TB_iframe=true&width='+($window.width()-100)+'&height='+($window.height()-100) ).addClass('thickbox');
			// do not prevent default
		});
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

		// bind form checkboxes to hidden inputs
		$('.bound_checkbox').change( function(ev) {
			var $this = $(this),
			       id = $this.attr('id'),
			  $target = $('#hidden-'+id);
			if ( $this.prop('checked') ) {
				$target.val('on');
			} else {
				$target.val('off');
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