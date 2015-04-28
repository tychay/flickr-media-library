<?php
namespace FML;

media_upload_header();
?>
<div class="media-iframe-content">
<?php
/*
    <div id="loader">
        <span class="helper"></span>
        <img src="<?php echo $admin_img_dir_url; ?>wpspin_light.gif" srcset="<?php echo $admin_img_dir_url; ?>wpspin_light.gif 1x, <?php echo $admin_img_dir_url; ?>wpspin_light-2x.gif 2x" />
    </div>
*/
    include 'form.flickr-upload.php';
?>
</div>
<div class="media-iframe-toolbar">
    <div class="media-toolbar-primary search-form">
        <a href="#" id="<?php echo FML::SLUG; ?>-media-add-button" class="button media-button button-primary button-large media-button-select" disabled="disabled"><?php _e( 'Insert into post' ); ?></a>
    </div>
</div>