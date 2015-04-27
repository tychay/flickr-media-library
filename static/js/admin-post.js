( function( window, $, undefined ) {
	'use strict';

	/* onReady: Handle Settings page for Flickr Media Library. */
	$( function() {
		// Handle title stuff
		var $titlediv = $('#titlediv');
		// make title field not-editable
		$('#title',$titlediv).prop('readonly',true);
		// make slug field not-editable. Note that this is dynamically written in post.js so
		// we'll just hide the slug buttons so the form cannot be submitted.
		//$('#new-post-slug',$titlediv).prop('readonly',true);
		$('#edit-slug-buttons', $titlediv).hide();

		// Clicking on refresh should submit a form that forces a refresh
		$('#post-refresh').click(function(e) {
			// change action to refresh and submit
			$('#hiddenaction').val('refreshpost');
			$('#post').submit();
			e.preventDefault();
		});
	});

} )( window, window.jQuery );