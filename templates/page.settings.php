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
			<tr class="display_fml_show_apikey<?php if ( $this->_options_column_is_hidden('fml_show_apikey') ) { echo ' hidden'; } ?>">
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
<?php else: ?>
		<?php wp_nonce_field($form_ids['flickr_auth'].'-verify'); ?>
		<input type="hidden" name="action" value="<?php echo $form_ids['flickr_auth']; ?>" />
		<p class="copy"><?php echo __('Flickr authorization is needed for plugin access of private photos as well as data syncronization features.', FML::SLUG); ?></p>
		<!-- begin api screen options stuff -->
		<table class="form-table">
			<tr class="display_fml_show_apikey<?php if ( $this->_options_column_is_hidden('fml_show_apikey') ) { echo ' hidden'; } ?>">
				<th scope="row"><label for="<?php echo $form_ids['flickr_auth']; ?>-secret"><?php esc_html_e('Flickr API Key', FML::SLUG); ?></label></th>
				<td><input id="<?php echo $form_ids['flickr_auth']; ?>-apikey" name="flickr_apikey" value="<?php echo esc_attr($settings['flickr_api_key']); ?>" disabled="disabled" />
					<p class="description"><?php echo __('Leave blank if you wish to use default value.', FML::SLUG); ?></p>
				</td>
			</tr>
			<tr class="display_fml_show_apikey<?php if ( $this->_options_column_is_hidden('fml_show_apikey') ) { echo ' hidden'; } ?>">
				<th scope="row"><label for="<?php echo $form_ids['flickr_auth']; ?>-secret"><?php esc_html_e('Flickr API Secret', FML::SLUG); ?></label></th>
				<td><input id="<?php echo $form_ids['flickr_auth']; ?>-secret" name="flickr_apisecret" value="<?php echo $api_secret_attr; ?>" disabled="disabled" /></td>
			</tr>
		</table>
		<!-- end api screen options stuff -->
		<?php submit_button(__('Authorize with Flickr', FML::SLUG)); ?>
<?php endif; ?>
	</form>
<?php
			break;
		case 'cpt_options':
?>
	<form method="post" id="cpt_options_form">
		<?php wp_nonce_field($form_ids['cpt_options'].'-verify'); ?>
		<input type="hidden" name="action" value="<?php echo $form_ids['cpt_options']; ?>" />
		<h3><?php _e('Custom Post Type Options', FML::SLUG); ?></h3>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="<?php echo $form_ids['cpt_options']; ?>-post_date_map"><?php _e('Post date', FML::SLUG); ?></label>
				</th>
				<td>
					<select id="<?php echo $form_ids['cpt_options']; ?>-post_date_map" name="post_date_map">
<?php
			foreach ( $post_dates_map as $id=>$name ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr($id), ( $settings['post_date_map'] == $id ) ? ' selected="selected"' : '', esc_html($name) );
			}
?>
					</select>
					<p class="description hidden" id="post_date_map_description"><?php _e( 'It is not recommended to map the date to flickr’s last modified time as it may change the post date arbitrarily.', FML::SLUG); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(__('Save Changes')); ?>
	</form>

<?php
			break;
		case 'output_options':
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
			( $this->_options_column_is_hidden( $key ) ) ? 'display: none;' : ''
		);
	}
?>
		</tr>
	</table>
</div>
