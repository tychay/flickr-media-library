<?php
namespace FML;

media_upload_header();

if ( empty( $_GET['for'] ) ) { $_GET['for'] = 'media_button'; }
$list_url = '#';
switch ( $_GET['for'] ) {
    case 'post_thumbnail':
        $title       = __('Set featured image');
        $button_text = __('Set featured image');
        $is_modal    = true;
        break;
    case 'admin_menu':
        $title       = __('Insert from Flickr',FML::SLUG);
        $button_text = __('Add to Media Library',FML::SLUG);
        $list_url    = admin_url( 'edit.php?post_type='.FML::POST_TYPE );
        $is_modal    = true;
        break;
    case 'media_button':
    default:
        $title       = __('Insert from Flickr',FML::SLUG);
        $button_text = __('Insert into post');
        $is_modal    = false;
}
if ( $is_modal ):
?>
<div class="media-modal wp-core-ui">
    <a class="media-modal-close" href="<?php echo $list_url; ?>"><span class="media-modal-icon"><span class="screen-reader-text">Close media panel</span></span></a>
    
    <div class="media-modal-content">
        <div class="media-frame-title"><h1><?php echo $title; ?></h1></div>
<?php
endif;
?>
<div class="media-frame-content">
<?php
    include 'form.flickr-upload.php';
?>
</div>
<div class="media-frame-toolbar">
    <div class="media-toolbar-primary search-form">
        <a href="#" id="<?php echo FML::SLUG; ?>-media-add-button" class="button media-button button-primary button-large media-button-select" disabled="disabled"><?php echo $button_text ?></a>
    </div>
</div>
<?php
if ( $is_modal ):
?>
    </div>
</div>
<div class="media-modal-backdrop"></div>
<?php
endif;