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
?>
    <form id="<?php echo FML::SLUG; ?>-flickr-search" method="get" class="media-upload-form type-form" onsubmit="return false">
    <?php wp_nonce_field(FML::SLUG.'-flickr-search-verify', FML::SLUG.'-search-nonce'); ?>
    <div class="attachments-browser">
        <div class="media-toolbar">
            <div class="media-toolbar-secondary">
                <label for="<?php echo FML::SLUG ?>-select-main" class="screen-reader-text"><?php _e('Filter by type'); ?></label>
                <select id="<?php echo FML::SLUG ?>-select-main" class="attachment-filters">
                    <option value="self"><?php _e('Your Photos',FML::SLUG); ?></option>
                    <option value="sets"><?php _e('Your Sets',FML::SLUG); ?></option>
                    <option value="all"><?php _e('Everyone&rsquo;s Photos', FML::SLUG); ?></option>
                </select>
                <label for="<?php echo FML::SLUG ?>-select-filtersort" class="screen-reader-text"><?php _e('Filter by date'); ?></label>
                <select id="<?php echo FML::SLUG ?>-select-filtersort" class="attachment-filters"></select>
                <span class="spinner" style="display: none;"></span>
            </div>
            <div class="media-toolbar-primary search-form">
                <label for="<?php echo FML::SLUG; ?>-search-input" class="screen-reader-text"><?php _e('Search Flickr',FML::SLUG); ?></label>
                <input type="search" placeholder="<?php _e('Search'); ?>" id="<?php echo FML::SLUG; ?>-search-input" class="search" />
            </div>
        </div>
        <div class="media-sidebar" id="<?php echo FML::SLUG?>-media-sidebar"></div>
        <div id="<?php echo FML::SLUG ?>-ajax-error" class="error"></div>
        <ul tabindex="-1" class="attachments ui-sortable ui-sortable-disabled" id="<?php echo FML::SLUG ?>-photo-list">
        </ul>
    </div>
    </form>
</div>
<div class="media-iframe-toolbar">
    <div class="media-toolbar-primary search-form">
        <a href="#" id="<?php echo FML::SLUG; ?>-media-add-button" class="button media-button button-primary button-large media-button-select" disabled="disabled"><?php echo __('Add to media library', FML::SLUG); ?></a>
    </div>
</div>