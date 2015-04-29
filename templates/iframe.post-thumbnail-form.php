<?php
namespace FML;

media_upload_header();
?>
<div class="media-modal wp-core-ui">
    <a class="media-modal-close" href="#"><span class="media-modal-icon"><span class="screen-reader-text">Close media panel</span></span></a>
    <div class="media-modal-content">
        <div class="media-frame-title"><h1><?php esc_html_e( 'Set featured image' ); ?></h1></div>
        <div class="media-frame-content">
<?php
    include 'form.flickr-upload.php';
?>
        </div>
        <div class="media-frame-toolbar">
            <div class="media-toolbar-primary search-form">
                <a href="#" id="<?php echo FML::SLUG; ?>-media-add-button" class="button media-button button-primary button-large media-button-select" disabled="disabled"><?php _e( 'Set featured image', FML::SLUG ); ?></a>
            </div>
        </div>
    </div>
</div>
<div class="media-modal-backdrop"></div>