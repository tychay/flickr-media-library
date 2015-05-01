<?php
/**
 * All the help tabs for the Settings Page
 *
 * Local Variables
 * - $tab_switch: The specific help tab to render
 * - $is_auth_with_flickr (boolean): if we've authenticated the flickr API
 */
namespace FML;

//var_dump($tab_switch);
switch ( $tab_switch ):
	case 'default-help-default':
?>
<p> DEFAULT TAB INFO GOES HERE </p>
<?php
		break;
	case 'flickr_options-help-flickrauth':
?><p><?php
		_e( 'The fields in this section are responsible for authenticating/deauthenticating Flickr.', FML::SLUG );
		if ( !$is_auth_with_flickr ) {
			_e( ' You will need to authorize access to your Flickr account if you want to be able to insert your private photos.', FML::SLUG);
		}
?></p>
<p><?php
		printf(
			__( 'Flickr Media Library already has it’s own API key and secret installed — there is no need to generate one. However, you can use your own instead of the one provided. To do so, simply click on “%s” for this page and check the box labeled: “%s.”', FML::SLUG),
			__( 'Screen Options' ),
			$this->_option_checkboxes['fml_show_apikey']
		);
?></p>
<?php
		if ( $is_auth_with_flickr ) :
?><p><?php
			printf(
				__( ' Note: You must first %s with flickr if you if you want to change this.', FML::SLUG ),
				__( 'Remove authorization', FML::SLUG )
			);
?></p><?php
		endif;
?><p><?php
 		printf(
			__(' (To generate your own API key and secret, <a href="%s" target="_blank">go to Flickr’s App Garden</a>.)', FML::SLUG),
			'https://www.flickr.com/services/apps/create/apply/?'
		);
?></p><?php
		break;
endswitch;
?>