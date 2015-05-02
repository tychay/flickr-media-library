( function( window, $, undefined ) {
	'use strict';

	function CSSCropObject($img) {
		//this.self         = this;
		this.targetWidth  = parseInt($img.attr('data-csscrop_width'));
		this.targetHeight = parseInt($img.attr('data-csscrop_height'));
		this.targetMethod = $img.attr('data-csscrop_method');
		this.imgWidth     = $img.width();
		this.imgHeight    = $img.height();
		this.$img         = $img;
		this.$cropDiv     = null;
		this.$origParent  = $img.parent();
		console.log(this);

		this._computeImgCss = function() {
			var css = {'position': 'absolute'};
			// TODO add different methods here
			if ( this.imgWidth > this.targetWidth ) {
				css.left = '-'+parseInt((this.imgWidth-this.targetWidth)/2)+'px';
			}
			if ( this.imgHeight > this.targetHeight ) {
				css.top = '-'+parseInt((this.imgHeight-this.targetHeight)/2)+'px';
			}
			return css;
		};
		this.crop = function() {
			this.$cropDiv = $('<div>').css({
				'position': 'relative',
				'overflow': 'hidden',
				'width'   : this.targetWidth+'px',
				'height'  : this.targetHeight+'px'
			});
			// insert the div between the image and it's direct parent
			this.$img.css( this._computeImgCss() ).detach();
			this.$cropDiv.append(this.$img);
			this.$origParent.append(this.$cropDiv);
		};
	}

	function csscrop() {
		$('img.csscrop').each(function() {
			var $this = $(this);
			// check to see if already run, if so do nothing (TODO: call refresh)
			if ( $this.csscrop ) { return; }
			$this.csscrop = new CSSCropObject( $this );
			$this.csscrop.crop();
		});
	}

	/* onReady: Handle Settings page for Flickr Media Library. */
	$( function() {
		csscrop();
	});

} )( window, window.jQuery );