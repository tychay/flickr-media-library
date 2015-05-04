<?php
namespace FML;
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
        <div class="media-sidebar" id="<?php echo FML::SLUG?>-media-sidebar">
            <!--No Uploading stuff-->
            <div tabindex="0" class="attachment-details save-ready hidden">
                <h3><?php _e('Attachment Details'); ?><!--removed spinner--></h3>
                <div class="attachment-info">
                    <div class="thumbnail thumbnail-image"><!--<img draggable="false" src="http://terrychay.dev/wp-content/uploads/2014/09/DSC0516_DxO-300x200.jpg">--></div>
                    <div class="details">
                        <div class="filename"></div>
                        <div class="uploaded"></div>
                        <div class="file-size hidden"></div>
                        <div class="dimensions"></div>
                        <!-- delete edit, refresh, delete links -->
                        <div class="compat-meta"><!-- TODO: unsupported currently --></div>
                    </div>
                </div>
                <label data-setting="url" class="setting">
                    <span class="name"><?php _e('URL'); ?></span>
                    <input type="text" value="" />
                </label>
                <label data-setting="title" class="setting">
                    <span class="name"><?php _e('Title'); ?></span>
                    <input type="text" value="" />
                </label>
                <label data-setting="caption" class="setting">
                    <span class="name"><?php _e('Caption'); ?></span>
                    <textarea></textarea>
                </label>
                <label data-setting="alt" class="setting">
                    <span class="name"><?php _e('Alt Text'); ?></span>
                    <input type="text" value="" />
                </label>
                <label data-setting="description" class="setting">
                    <span class="name"><?php _e('Description'); ?></span>
                    <textarea></textarea>
                </label>
            </div>
            <!-- delete form compat-item -->
            <div class="attachment-display-settings hidden">
                <h3><?php _e('Attachment Display Settings'); ?></h3>
                <label class="setting">
                    <span><?php _e('Alignment'); ?></span>
                    <select data-user-setting="align" data-setting="align" class="alignment">
<?php
foreach( $this->_aligns as $value=>$text ) {
    printf( '<option value="%s">%s</option>', esc_attr($value), $text );
}
?>
                    </select>
                </label>
                <div class="setting">
                    <label>
                        <span><?php _e('Link To'); ?></span>
                        <select data-user-setting="urlbutton" data-setting="link" class="link-to">
<?php
foreach( $this->_links as $value=>$text ) {
    printf( '<option value="%s">%s</option>', esc_attr($value), $text );
}
?>
                        </select>
                    </label>
                    <input type="text" data-setting="linkUrl" class="link-to-custom" readonly="" />
                </div>
                <label class="setting">
                    <span><?php _e('Size'); ?></span>
                    <select data-user-setting="imgsize" data-setting="size" name="size" class="size"></select>
                </label>
            </div>
        </div>
        <div id="<?php echo FML::SLUG ?>-ajax-error" class="error"></div>
        <ul tabindex="-1" class="attachments ui-sortable ui-sortable-disabled" id="<?php echo FML::SLUG ?>-photo-list">
        </ul>
    </div>
    </form>
