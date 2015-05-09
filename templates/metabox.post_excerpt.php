<?php
/**
 * Display the metabox for caption (post_excerpt)
 * 
 * @author terry chay <tychay@php.net>
 */
namespace FML;

if ( $use_template ) :
?>
<label class="screen-reader-text" for="excerpt"><?php _e('Caption') ?></label><textarea rows="1" cols="40" name="post_excerpt_template" id="excerpt"><?php echo esc_textarea( $caption); ?></textarea>
<p><?php _e( 'The caption template generates the default caption for this media and the caption that shows on the the attachment page.', FML::SLUG ); ?></p>
<?php
else :
?>
<label class="screen-reader-text" for="excerpt"><?php _e('Caption') ?></label><textarea rows="1" cols="40" name="excerpt" id="excerpt"><?php $caption; // textarea escaped?? ?></textarea>
<p><?php _e( 'The default caption for this media, and the caption that shows on the the attachment page.', FML::SLUG ); ?></p>
<?php
endif;
?>