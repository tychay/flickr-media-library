<?php
/**
 * Not really a real metabox, this is directly injected where the rich editor would normally be
 *
 * Display the box with a refresh button and the post content
 * 
 * @author terry chay <tychay@php.net>
 */
namespace FML;
?>
<div id="postdivrich" class="postarea wp-editor-expand">
<?php
// inject the save action
?>
	<div id="refresh-action"><a class="refresh button" href="" id="post-refresh"><?php _e( 'Refresh from Flickr', FML::SLUG ); ?></a></div>
<?php
/*
// Because the_content filters (like prepend_media) may need access to
// get_post(), we need to be a bit clever else we depend on the global
// being set properly by accident (which it is in this case, but who
// knows).
$temp_post = $GLOBALS['post'];
$GLOBALS['post'] = $post;
the_content();
$GLOBALS['post'] = $temp_post;
*/
echo $content;
?>
</div>