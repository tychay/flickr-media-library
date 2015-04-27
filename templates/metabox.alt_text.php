<?php
/**
 * Display a metabox for alt text (_wp_attachment_image_alt)
 * 
 * @author terry chay <tychay@php.net>
 */
namespace FML;
?>
<label class="screen-reader-text" for="image_alt_text"><?php _e('Alt Text') ?></label><textarea rows="1" cols="40" name="image_alt_text" id="image_alt_text"><?php echo $alt_text; // textarea_escaped ?></textarea>
<p><?php _e( 'The alt text for this media. This is different from the Title because it is designed for accessibility for screen readers or slow browsers. (Try describing the image to someone who can’t see it.)', FML::SLUG ); ?></p>