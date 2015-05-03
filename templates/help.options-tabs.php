<?php
/**
 * All the help tabs for the Settings Page
 *
 * Local Variables
 * - $tab_switch (string): The specific help tab to render
 * - $tabs (array): List of PAGE tabs indexed by tab id
 * - $hidden_cols (array): List of screen options indexed by column id
 * - $is_auth_with_flickr (boolean): if we've authenticated the flickr API
 */
namespace FML;

//var_dump($tab_switch);
switch ( $tab_switch ):
	case 'default-help-default':
?><p><?php
		_e( 'These pages manage settings for Flickr Media Library and are organized into various tabs:', FML::SLUG );
?></p>
	<ul>
		<li><?php
		printf(
			__( '<b>%s</b>: Authentication with the flickr and behavior when accessing flickr throught the Flickr API.', FML::SLUG ),
			$tabs['flickr_options']
		)
?></li>
		<li><?php
		printf(
			__( '<b>%s</b>: Flickr Media Library stores metadata for Flickr images in WordPress in a Custom Post Type semi-compatible with the WordPress’s attachment system (Media &gt; Library). This controls the how that information is stored.', FML::SLUG ),
			$tabs['cpt_options']
		)
?></li>
		<li><?php
		printf(
			__( '<b>%s</b>: Controls how flickr media content is output as an image, to the editor, and in shortcode expansions.', FML::SLUG ),
			$tabs['output_options']
		)
?></li>
	</ul>
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
			$hidden_cols['fml_show_apikey']
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
	case 'cpt_options-help-cptoptions':
?><p><?php
		_e( 'Flickr data is cached as a custom post type. These options control how those posts are stored. The following options are available:', FML::SLUG );
?></p>
	<ul>
		<li><?php
		printf(
			__( '<b>%s</b>: Allows flickr data to set the post’s post_date. For instance, you can set the date to be the date the photo was taken, when it was uploaded to flickr, when it was last modified on flickr, or leave it unlinked from flickr entirely. Note that the last modified linking is not recommended.', FML::SLUG ),
			__( 'Post date', FML::SLUG )
		);
?></li>
	</ul>
<?php
		break;
endswitch;
?>