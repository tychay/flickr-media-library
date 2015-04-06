( function( window, $, undefined ) {
	'use strict';

	//var $window = $( window ),
	//	$document = $( document );

	/* onReady: Handle Settings page for Flickr Media Library. */
	$( function() {
		var $toggle = $('#flickr_media_library_show_apikey-toggle');

		function on() {
			$('.flickr-media-library-apiform').removeClass('hidden');
			$('.flickr-media-library-apiform input').prop( 'disabled', false);
			// show form elements
		}
		function off() {
			$('.flickr-media-library-apiform').addClass('hidden');
			$('.flickr-media-library-apiform input').prop( 'disabled', true);
		}

		// show the on/off checkbox
		//$toggle.on( 'change.flickr-media-library-customize', function() {
		$toggle.change( function() {
			var to_value;
			if ( $(this).prop( 'checked' ) ) {
				on();
				// mechanism: https://codex.wordpress.org/WordPress_Cookies
				// Unlike other screen_options, full screen editing is stored in the cookie (browser) instead of user_meta. This just clutters the cookie with something worthless.
				//window.setUserSetting( 'flickr_media_library_show_apikey', 'on' );
				to_value = 'on';
			} else {
				off();
				to_value = 'off'
			}
			// Update server via ajax
			$.post(
				window.ajaxurl, // no need for localize_script as this is set in wp-admin header ;-)
				{
					screenoptionnonce: $('#screenoptionnonce').val(),
					action: 'flickr_media_library_show_apikey',
					flickr_media_library_show_apikey: to_value
				}/*, function(the_data) {
					alert(the_data);
				}*/
			);
		});

		// Initialize api form and activate toggle screen_option
		if ( $toggle.is(':checked') ) {
			on();
		}
		$('.flickr_media_library_show_apikey').removeClass('hidden');
	});

} )( window, window.jQuery );