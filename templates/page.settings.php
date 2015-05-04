<?php
/**
 * Submenu of Settings for Flickr Media Library
 *
 * Local Variables
 * - $settings (array): plugin blog options 
 * - $this_page_url (string): relative path back to this page
 * - $tabs (array): tabs and their tab names
 * - $active_tab (string): id of currently active tab
 * - $hidden_cols (array): the screen options for all the pages
 * - $form_ids (array): list of form hidden input action names
 * - $api_secret_attr (string): API secret (if not the default)
 * - $is_auth_with_flickr (boolean): if we've authenticated the flickr API
 * 
 * @author terry chay <tychay@php.net>
 */
namespace FML;

function _page_settings_th( $form_id, $select_name, $label ) {
	printf(
		'<th scope="row"><label for="%s-%s">%s</label></th>',
		esc_attr($form_id),
		esc_attr($select_name),
		$label
	);
}
function _page_input_select( $form_id, $select_name, $selects, $selected ) {
	printf( '<select id="%1$s-%2$s" name="%2$s">', esc_attr($form_id), esc_attr($select_name) );
	foreach ( $selects as $value=>$name ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr($value), ( $selected == $value ) ? ' selected="selected"' : '', esc_html($name) );
	}
	echo '</select>';
}
function _page_settings_input( $form_id, $input_name, $input_value, $disabled ) {
	printf(
		'<input id="%1$s-%2$s" name="%2$s" value="%3$s"%4$s />',
		esc_attr($form_id),
		esc_attr($input_name),
		esc_attr($input_value),
		( $disabled) ? ' disabled="disabled"' : ''
	);
}
function _page_settings_hidden_class( $screen_option_id, $that ) {
	$return = 'display_'.esc_attr( $screen_option_id);
	if ( $that->options_column_is_hidden( $screen_option_id ) ) $return .= ' hidden';
	echo $return;
}
?>
<div class="wrap">

	<h2><?php _e('Flickr Media Library Settings', FML::SLUG) ?></h2>

	<h2 class="nav-tab-wrapper">
<?php
	foreach( $tabs as $tab_id=>$tab_name ) {
		printf(
			'<a href="%s&amp;tab=%s" class="nav-tab%s">%s</a>',
			$this_page_url,
			esc_attr( $tab_id ),
			( $active_tab == $tab_id ) ? ' nav-tab-active' : '',
			$tab_name
		);
	}
?>
	</h2>

<?php
	switch ( $active_tab ):
		case 'flickr_options':
?>
	<h3><?php _e('Flickr authorization', FML::SLUG); ?></h3>
	<form method="post" id="flickr_auth_form">
<?php if ($is_auth_with_flickr): ?>
		<?php wp_nonce_field($form_ids['flickr_deauth'].'-verify'); ?>
		<input type="hidden" name="action" value="<?php echo $form_ids['flickr_deauth']; ?>" />
		<table class="form-table">
			<!-- begin api screen options stuff -->
			<tr class="<?php _page_settings_hidden_class('fml_show_apikey', $this ); ?>">
				<th scope="row"><?php esc_html_e('Flickr API Key', FML::SLUG); ?></th>
				<td><?php echo esc_html($settings['flickr_api_key']); ?></td>
			</tr>
			<!-- end api screen options stuff -->
			<tr>
				<th scope="row"><?php esc_html_e('Flickr Username', FML::SLUG); ?></th>
				<td><?php echo esc_html($settings[Flickr::USER_NAME]); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Flickr User Id', FML::SLUG); ?></th>
				<td><?php echo esc_html($settings[Flickr::USER_NSID]); ?></td>
			</tr>
		</table>
		<?php submit_button(__('Remove authorization', FML::SLUG)); ?>
<?php else:
		$form_id = $form_ids['flickr_auth'];
?>
		<?php wp_nonce_field($form_id.'-verify'); ?>
		<input type="hidden" name="action" value="<?php echo $form_id ?>" />
		<p class="copy"><?php echo __('Flickr authorization is needed for plugin access of private photos as well as data syncronization features.', FML::SLUG); ?></p>
		<!-- begin api screen options stuff -->
		<table class="form-table">
			<tr class="<?php _page_settings_hidden_class( 'fml_show_apikey', $this )?>">
				<?php _page_settings_th( $form_id, 'flickr_apikey', __('Flickr API Key',FML::SLUG) ); ?>
				<td><?php _page_settings_input( $form_id, 'flickr_apikey', $settings['flickr_api_key'], $this->options_column_is_hidden( 'fml_show_apikey' ) )  ?>
					<p class="description"><?php echo __('Leave blank if you wish to use default value.', FML::SLUG); ?></p>
				</td>
			</tr>
			<tr class="<?php _page_settings_hidden_class( 'fml_show_apikey', $this )?>">
				<?php _page_settings_th( $form_id, 'flickr_apisecret', __('Flickr API Secret',FML::SLUG) ); ?>
				<td><?php _page_settings_input( $form_id, 'flickr_apisecret', $api_secret_attr, $this->options_column_is_hidden( 'fml_show_apikey' ) )  ?></td>
			</tr>
		</table>
		<!-- end api screen options stuff -->
		<?php submit_button(__('Authorize with Flickr', FML::SLUG)); ?>
<?php endif; ?>
	</form>
<?php
			break;
		case 'cpt_options':
			$form_id = $form_ids['cpt_options'];
?>
	<form method="post" id="cpt_options_form">
		<?php wp_nonce_field($form_ids['cpt_options'].'-verify'); ?>
		<input type="hidden" name="action" value="<?php echo $form_ids['cpt_options']; ?>" />
		<h3><?php _e('Custom Post Type Options', FML::SLUG); ?></h3>
		<table class="form-table">
			<tr>
				<?php _page_settings_th( $form_id, 'post_date_map', __('Post date Alignment',FML::SLUG) ); ?>
				<td><?php _page_input_select
			( $form_id, 'post_date_map', $select_post_dates, $settings['post_date_map'] ); ?>
					<p class="description hidden" id="post_date_map_description"><?php _e( 'It is not recommended to map the date to flickr’s last modified time as it may change the post date arbitrarily.', FML::SLUG); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(__('Save Changes')); ?>
	</form>
<?php
			break;
		case 'output_options':
			$form_id = $form_ids['output_options'];
?>
	<form method="post" id="output_options_form">
		<?php wp_nonce_field($form_ids['output_options'].'-verify'); ?>
		<input type="hidden" name="action" value="<?php echo $form_ids['output_options']; ?>" />
		<h3><?php _e('Editor Options', FML::SLUG); ?></h3>
		<table class="form-table">
			<tr>
				<?php _page_settings_th( $form_id, 'media_default_align', __('Default Alignment',FML::SLUG) ); ?>
				<td><?php _page_input_select
			( $form_id, 'media_default_align', $select_aligns, $settings['media_default_align'] ); ?></td>
			</tr>
			<tr>
				<?php _page_settings_th( $form_id, 'media_default_link', __('Default Link To',FML::SLUG) ); ?>
				<td><?php _page_input_select
			( $form_id, 'media_default_link', $select_links, $settings['media_default_link'] ); ?>
					<p class="description hidden" id="media_default_link_description"><?php _e('Remember <a href="https://www.flickr.com/help/guidelines">Flickr Community Guidelines</a> state that you link back to Flickr when you post Flickr content elsewhere.',FML::SLUG); ?></p>
				</td>
			</tr>
			<tr>
				<?php _page_settings_th( $form_id, 'media_default_size', __('Default Size',FML::SLUG) ); ?>
				<td><?php _page_input_select
			( $form_id, 'media_default_size', $select_sizes, $settings['media_default_size'] ); ?>
					<p class="description" id="media_default_size_description"><?php _e('Some sizes will be unavailable for photos that are too small or too old.',FML::SLUG); ?></p>
				</td>
			</tr>
			<tr class="<?php _page_settings_hidden_class( 'fml_show_rels', $this )?>">
				<?php _page_settings_th( $form_id, 'media_default_rel_post', __('Attachment "rel"',FML::SLUG) ); ?>
				<td><?php _page_settings_input( $form_id, 'media_default_rel_post', $settings['media_default_rel_post'], $this->options_column_is_hidden( 'fml_show_rels' ) )  ?>
					<p class="description"><?php _e('Use <code>attachment</code> to emulate attachments'); ?></p>
				</td>
			</tr>
			<tr class="<?php _page_settings_hidden_class( 'fml_show_rels', $this )?>">
				<?php _page_settings_th( $form_id, 'media_default_rel_post_id', __('Attachment "rel" w/ID',FML::SLUG) ); ?>
				<td><?php _page_settings_input( $form_id, 'media_default_rel_post_id', $settings['media_default_rel_post_id'], $this->options_column_is_hidden( 'fml_show_rels' ) )  ?>
					<p class="description"><?php _e('Use <code>wp-att-</code> to emulate attachments'); ?></p>
				</td>
			</tr>
			<tr class="<?php _page_settings_hidden_class( 'fml_show_rels', $this )?>">
				<?php _page_settings_th( $form_id, 'media_default_rel_flickr', __('Flickr Photo "rel"',FML::SLUG) ); ?>
				<td><?php _page_settings_input( $form_id, 'media_default_rel_flickr', $settings['media_default_rel_flickr'], $this->options_column_is_hidden( 'fml_show_rels' ) )  ?>
				</td>
			</tr>
			<tr class="<?php _page_settings_hidden_class( 'fml_show_classes', $this )?>">
				<?php _page_settings_th( $form_id, 'media_default_class_size', __('Image class for size',FML::SLUG) ); ?>
				<td><?php _page_settings_input( $form_id, 'media_default_class_size', $settings['media_default_class_size'], $this->options_column_is_hidden( 'fml_show_classes' ) )  ?>
					<p class="description"><?php _e('Use <code>size-</code> to emulate attachments'); ?></p>
				</td>
			</tr>
			<tr class="<?php _page_settings_hidden_class( 'fml_show_classes', $this )?>">
				<?php _page_settings_th( $form_id, 'media_default_class_id', __('Image class for ID',FML::SLUG) ); ?>
				<td><?php _page_settings_input( $form_id, 'media_default_class_id', $settings['media_default_class_id'], $this->options_column_is_hidden( 'fml_show_classes' ) )  ?>
					<p class="description"><?php _e('Use <code>wp-image-</code> to emulate attachments'); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(__('Save Changes')); ?>
	</form>
<?php
			break;
	endswitch;

	// Inject the hidden column table for screen options
?>
	<table class="hidden_column_table" style="position: absolute; left: -999em;">
		<tr>
<?php
	foreach( $hidden_cols as $key=>$value ) {
		printf('<th scope="col" id="%1$s" class="manage-column column-%1$s" style="%2$s"></th>',
			$key,
			( $this->options_column_is_hidden( $key ) ) ? 'display: none;' : ''
		);
	}
?>
		</tr>
	</table>
</div>
