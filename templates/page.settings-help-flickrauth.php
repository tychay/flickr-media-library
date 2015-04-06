<?php
namespace FML;
?>
<p><?php echo
	__('You will need to authorize access to your Flickr account if you want to be able to insert your private photos.',FML::SLUG), ' ',
	__('The fields on this screen are responsible for authenticating Flickr.',FML::SLUG);
	?></p>
<p><?php printf(
	__('Flickr Media Library already has it’s own API key and secret installed — there is no need to generate one. However, you can use your own instead of the one provided. To do so, simply click on “%s” for this page and check the box labeled: “%s” Note: You can only change this if the plugin is not already authorized with Flickr.', FML::SLUG),
	__('Screen Options'),
	__('Show Flickr API Key and Secret.', FML::SLUG)
); ?></p>
<p><?php printf(
	__('(To generate your own API key and secret, <a href="%s" target="_blank">go to Flickr’s App Garden</a>.)', FML::SLUG),
	'https://www.flickr.com/services/apps/create/apply/?'
); ?></p>
