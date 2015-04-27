<?php
/**
 * Display the metabox for caption (post_excerpt)
 * 
 * @author terry chay <tychay@php.net>
 */
namespace FML;
?>
<label class="screen-reader-text" for="excerpt"><?php _e('Caption') ?></label><textarea rows="1" cols="40" name="excerpt" id="excerpt"><?php echo $post->post_excerpt; // textarea_escaped ?></textarea>
<p><?php _e( 'The default caption for this media, and the caption that shows on the the attachment page.', FML::SLUG ); ?></p>