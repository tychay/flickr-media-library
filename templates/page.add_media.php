<?php
namespace FML;

?>
<div class="media-modal wp-core-ui">
    <a class="media-modal-close" href="#"><span class="media-modal-icon"><span class="screen-reader-text">Close media panel</span></span></a>
    <div class="media-modal-content">
    <h2><?php esc_html_e('Insert from Flickr', FML::SLUG); ?></h2>
    <div class="fullheight">
        <div class="media-content">
<?php
    include 'form.flickr-upload.php';
?>
        </div>
        <div class="media-toolbar">
            <div class="media-toolbar-primary search-form">
                <a href="#" id="<?php echo FML::SLUG; ?>-media-add-button" class="button media-button button-primary button-large media-button-select" disabled="disabled"><?php _e( 'Insert into post' ); ?></a>
            </div>
        </div>
    </div>
</div>
</div>
<div class="media-modal-backdrop"></div>
