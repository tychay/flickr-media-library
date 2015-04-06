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
                    <option value="self">Your Photos</option>
                    <option value="sets">Your Sets</option>
                    <option value="all">Everyone's Photos</option>
                </select>
                <label for="<?php echo FML::SLUG ?>-select-filtersort" class="screen-reader-text"><?php _e('Filter by date'); ?></label>
                <select id="<?php echo FML::SLUG ?>-select-filtersort" class="attachment-filters">
                    <option value="all">All dates</option><option value="0">September 2014</option><option value="1">September 2011</option><option value="2">June 2010</option><option value="3">May 2010</option><option value="4">April 2010</option><option value="5">March 2010</option><option value="6">February 2010</option>
                </select>
                <span class="spinner" style="display: none;"></span>
            </div>
            <div class="media-toolbar-primary search-form">
                <label for="<?php echo FML::SLUG; ?>-search-input" class="screen-reader-text"><?php _e('Search Flickr',FML::SLUG); ?></label>
                <input type="search" placeholder="<?php _e('Search'); ?>" id="<?php echo FML::SLUG; ?>-search-input" class="search" />
            </div>
        </div>
        <!--<div class="media-sidebar">INFO CONTENT HERE</div>-->
        <div id="<?php echo FML::SLUG ?>-ajax-error" class="error"></div>
        <ul tabindex="-1" class="attachments ui-sortable ui-sortable-disabled" id="<?php echo FML::SLUG ?>-photo-list">
<!--
            <li tabindex=0" role="checkbox" aria-label="LABEL" aria-checked="false" data-id="ID HERE" class="attachment save-ready">
                <div class="attachment-preview js--select-attachment type-image subtype-jpeg landscape">
                    <div class="thumbnail">
                        <div class="centered">
                            <img src="http://terrychay.dev/wp-content/uploads/2014/09/DSC0516_DxO-300x200.jpg" draggable="false" alt="">
                        </div>
                    </div>
                </div>
                <a class="check" href="#" title="Deselect" tabindex="-1"><div class="media-modal-icon"></div></a>
            </li>         
-->
        </ul>
    </div>
    </form>
</div>
<div class="media-iframe-toolbar">
    <div class="media-toolbar-primary search-form">
        <a href="#" class="button media-button button-primary button-large media-button-select" disabled="disabled"><?php echo __('Insert into page'); ?></a>
    </div>
</div>