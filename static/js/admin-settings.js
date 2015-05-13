( function( window, $, constants ) {
	'use strict';

	//console.log(constants);
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
			// bind some checkboxes to others
			if ( $this.hasClass('ctrl') ) {
				var key = $this.prop('name').substring(3);
				var $ctrl_div = $('.ctrl-'+key);
				if ( $this.prop('checked') ) {
					$ctrl_div.removeClass('hidden');
					$('input', $ctrl_div).prop('disabled',false);
					$('select', $ctrl_div).prop('disabled',false);
				} else {
					$ctrl_div.addClass('hidden');
					$('input', $ctrl_div).prop('disabled','disabled');
					$('select', $ctrl_div).prop('disabled','disabled');
				}
			}

		}).change();

		// add confirm to reset forms
		$('#submit_reset').click( function(e) {
			if ( !window.confirm( constants.confirm ) ) {
				e.preventDefault();
			}
		});

		// handle template editing select box
		$('#flickr-media-library-template_options-template').change( function() {
			var $this = $(this);
			var content = constants.templates[$this.val()];
			$('#content').val( content );
			if ( $this.val() == '__new__' ) {
				$('#submit_update').addClass('hidden');
				$('#submit_delete').addClass('hidden');
				$('#submit_add').removeClass('hidden');
				$('#new_template_name').removeClass('hidden');
			} else {
				$('#submit_update').removeClass('hidden');
				$('#submit_delete').removeClass('hidden');
				$('#submit_add').addClass('hidden');
				$('#new_template_name').addClass('hidden');
			}

		}).change(); //ping the change() in case the user hit the back button


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

} )( window, window.jQuery, FMLOptionsConst );