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

$flickr_tos = __('<a href="https://www.flickr.com/help/guidelines">Flickr Community Guidelines</a>',FML::SLUG);
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
function _page_textarea( $form_id, $input_name, $input_value, $disabled=false ) {
	printf(
		'<textarea id="%1$s-%2$s" name="%2$s" rows="4" class="large-text code"%4$s>%3$s</textarea>',
		esc_attr($form_id),
		esc_attr($input_name),
		esc_textarea($input_value),
		( $disabled) ? ' disabled="disabled"' : ''
	);
}
function _page_settings_cb( $form_id, $input_name, $input_value, $disabled, $label ) {
	printf(
		'<input type="hidden" id="hidden-%1$s-%2$s" name="%2$s" value="%3$s"%4$s /> <label for="%1$s-%2$s"><input type="checkbox" id="%1$s-%2$s" name="cb-%2$s" class="bound_checkbox"%5$s /> %6$s</label>',
		esc_attr($form_id),
		esc_attr($input_name),
		( $input_value ) ? 'on'                   : 'off',
		( $disabled )    ? ' disabled="disabled"' : '',
		( $input_value ) ? ' checked="checked"'   : '',
		$label
	);
}
function _page_settings_hidden_class( $screen_option_id, $that ) {
	$return = 'display_'.esc_attr( $screen_option_id);
	if ( $that->options_column_is_hidden( $screen_option_id ) ) $return .= ' hidden';
	echo $return;
}
function _page_settings_get_info_link( $url, $use_thickbox=true ) {
	return sprintf(
		'<a target="_blank" href="%s"%s>(?)</a>',
		esc_attr($url),
		( $use_thickbox ) ? ' class="TB_fullscreen"' : ''
	);
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
	<h3 class="title"><?php _e('Flickr authorization', FML::SLUG); ?></h3>
	<form method="post" id="flickr_auth_form">
<?php if ( $is_auth_with_flickr ): ?>
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
	<form method="post" id="flickr_options_form">
<?php
			$form_id = $form_ids['flickr_options'];
?>
		<?php wp_nonce_field($form_id.'-verify'); ?>
		<input type="hidden" name="action" value="<?php echo $form_id; ?>" />
		<h3 class="title"><?php _e('Flickr API Options',FML::SLUG); ?></h3>
		<table class="form-table">
			<tr>
				<th scope="row"><?php _e('Search options',FML::SLUG); ?></th>
				<td>
					<?php
			if ( $is_auth_with_flickr ) {
				_page_settings_cb( $form_id, 'flickr_search_safe_search', $settings['flickr_search_safe_search'], false, __('Enable SafeSearch',FML::SLUG).' '. _page_settings_get_info_link('https://help.yahoo.com/kb/flickr/safesearch-sln14917.html', true) );
				// see above: we insert an extra br to separate it from the licenses
				echo '<br />';
			}
			$settings_licenses = explode( ',', $settings['flickr_search_license'] );
			add_thickbox();
			foreach ( $cb_licenses as $id=>$license ) {
				echo '<br />';
				// these bastages block iframes! :-(
				$description = ( $license['url'] )
						? esc_html( $license['name'] ) . ' ' . _page_settings_get_info_link( $license['url'], $license['iframe'] )
					    : esc_html($license['name']);
				_page_settings_cb( $form_id, 'flickr_search_license-'.$id, in_array( $id, $settings_licenses ), false, $description );
			}
					?>
				</td>
			</tr>
		</table>
		<?php submit_button(__('Save Changes')); ?>
<?php
			break;
		case 'cpt_options':
			$form_id = $form_ids['cpt_options'];
?>
	<form method="post" id="cpt_options_form">
		<?php wp_nonce_field($form_id.'-verify'); ?>
		<input type="hidden" name="action" value="<?php echo $form_id; ?>" />
		<h3 class="title"><?php _e('Custom Post Type Options',FML::SLUG); ?></h3>
		<table class="form-table">
			<tr>
				<?php _page_settings_th( $form_id, 'post_date_map', __('Post date alignment',FML::SLUG) ); ?>
				<td><?php _page_input_select( $form_id, 'post_date_map', $select_post_dates, $settings['post_date_map'] ); ?>
					<p class="description hidden" id="post_date_map_description"><?php _e( 'It is not recommended to map the date to flickr’s last modified time as it may change the post date arbitrarily.', FML::SLUG); ?></p>
				</td>
			</tr>
			<tr>
				<?php _page_settings_th( $form_id, 'post_excerpt_default', __('Caption template',FML::SLUG) ); ?>
				<td><?php _page_textarea( $form_id, 'post_excerpt_default', $settings['post_excerpt_default'] ); ?>
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
		<h3 class="title"><?php _e('Editor Options', FML::SLUG); ?></h3>
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
					<p class="description hidden" id="media_default_link_description"><?php printf( __('Remember %s state that you link back to Flickr when you post Flickr content elsewhere.', FML::SLUG), $flickr_tos ); ?></p>
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
					<p class="description"><?php printf( __('Use <code>%s</code> to emulate attachments',FML::SLUG), 'attachment' ); ?></p>
				</td>
			</tr>
			<tr class="<?php _page_settings_hidden_class( 'fml_show_rels', $this )?>">
				<?php _page_settings_th( $form_id, 'media_default_rel_post_id', __('Attachment "rel" w/ID',FML::SLUG) ); ?>
				<td><?php _page_settings_input( $form_id, 'media_default_rel_post_id', $settings['media_default_rel_post_id'], $this->options_column_is_hidden( 'fml_show_rels' ) )  ?>
					<p class="description"><?php printf( __('Use <code>%s</code> to emulate attachments',FML::SLUG), 'wp-att-%d' ); ?></p>
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
					<p class="description"><?php printf( __('Use <code>%s</code> to emulate attachments',FML::SLUG), 'size-%s' ); ?></p>
				</td>
			</tr>
			<tr class="<?php _page_settings_hidden_class( 'fml_show_classes', $this )?>">
				<?php _page_settings_th( $form_id, 'media_default_class_id', __('Image class for ID',FML::SLUG) ); ?>
				<td><?php _page_settings_input( $form_id, 'media_default_class_id', $settings['media_default_class_id'], $this->options_column_is_hidden( 'fml_show_classes' ) )  ?>
					<p class="description"><?php printf( __('Use <code>%s</code> to emulate attachments',FML::SLUG), 'wp-image-%d' ); ?></p>
				</td>
			</tr>
		</table>
		<h3 class="title"><?php _e('Shortcode Options',FML::SLUG); ?></h3>
		<table class="form-table">
			<tr>
				<?php _page_settings_th( $form_id, 'shortcode_default_link', __('Default link',FML::SLUG) ); ?>
				<td><?php _page_settings_input( $form_id, 'shortcode_default_link', $settings['shortcode_default_link'], false );  ?>
					<p class="description"><?php printf( __('Set to <code>flickr</code> to force compliance with %s.', FML::SLUG), $flickr_tos ); ?></p>
				</td>
			</tr>
			<tr class="<?php _page_settings_hidden_class( 'fml_show_rels', $this )?>">
				<?php _page_settings_th( $form_id, 'shortcode_default_rel_post', __('link=post "rel"',FML::SLUG) ); ?>
				<td><?php _page_settings_input( $form_id, 'shortcode_default_rel_post', $settings['shortcode_default_rel_post'], $this->options_column_is_hidden( 'fml_show_rels' ) )  ?>
					<p class="description"><?php printf( __('Use <code>%s</code> to emulate attachments',FML::SLUG), 'attachment' ); ?></p>
				</td>
			</tr>
			<tr class="<?php _page_settings_hidden_class( 'fml_show_rels', $this )?>">
				<?php _page_settings_th( $form_id, 'shortcode_default_rel_post_id', __('link=post "rel" w/ID',FML::SLUG) ); ?>
				<td><?php _page_settings_input( $form_id, 'shortcode_default_rel_post_id', $settings['shortcode_default_rel_post_id'], $this->options_column_is_hidden( 'fml_show_rels' ) )  ?>
					<p class="description"><?php printf( __('Use <code>%s</code> to emulate attachments',FML::SLUG), 'wp-att-%d' ); ?></p>
				</td>
			</tr>
			<tr class="<?php _page_settings_hidden_class( 'fml_show_rels', $this )?>">
				<?php _page_settings_th( $form_id, 'shortcode_default_rel_flickr', __('link=flickr "rel"',FML::SLUG) ); ?>
				<td><?php _page_settings_input( $form_id, 'shortcode_default_rel_flickr', $settings['shortcode_default_rel_flickr'], $this->options_column_is_hidden( 'fml_show_rels' ) )  ?>
				</td>
			</tr>
			<tr class="<?php _page_settings_hidden_class( 'fml_show_classes', $this )?>">
				<?php _page_settings_th( $form_id, 'shortcode_default_class_size', __('Image class for size',FML::SLUG) ); ?>
				<td><?php _page_settings_input( $form_id, 'shortcode_default_class_size', $settings['shortcode_default_class_size'], $this->options_column_is_hidden( 'fml_show_classes' ) )  ?>
					<p class="description"><?php printf( __('Use <code>%s</code> to emulate attachments',FML::SLUG), 'attachment-%s' ); ?></p>
				</td>
			</tr>
			<tr class="<?php _page_settings_hidden_class( 'fml_show_classes', $this )?>">
				<?php _page_settings_th( $form_id, 'shortcode_default_class_id', __('Image class for ID',FML::SLUG) ); ?>
				<td><?php _page_settings_input( $form_id, 'shortcode_default_class_id', $settings['shortcode_default_class_id'], $this->options_column_is_hidden( 'fml_show_classes' ) )  ?>
					<p class="description"><?php printf( __('Use <code>%s</code> to emulate attachments',FML::SLUG), '' ); ?></p>
				</td>
			</tr>
			<tr class="<?php _page_settings_hidden_class( 'fml_show_perf', $this )?>">
				<th scope="row"><?php _e('Performance',FML::SLUG); ?></th>
				<td>
					<?php _page_settings_cb( $form_id, 'shortcode_extract_flickr_id', $settings['shortcode_extract_flickr_id'], $this->options_column_is_hidden( 'fml_show_perf' ), __('Extract flickr ID from content',FML::SLUG) );  ?>
					<br />
					<?php _page_settings_cb( $form_id, 'shortcode_generate_custom_post', $settings['shortcode_generate_custom_post'], $this->options_column_is_hidden( 'fml_show_perf' ), __('Shortcode generates missing Flickr Media custom posts',FML::SLUG) );  ?>
				</td>
			</tr>
		</table>
		<h3 class="title"><?php _e('Image Options',FML::SLUG); ?></h3>
		<table class="form-table">
			<tr>
				<th scope="row"><?php _e('Javascript routines',FML::SLUG); ?></th>
				<td>
					<?php
			$description = __('Use CSS cropping',FML::SLUG);
			_page_settings_cb( $form_id, 'image_use_css_crop', $settings['image_use_css_crop'], false, $description );
			echo '<br />';
			$description = __('Use Picturefill 2',FML::SLUG) . ' ' . _page_settings_get_info_link( 'http://scottjehl.github.io/picturefill/', false );
			_page_settings_cb( $form_id, 'image_use_picturefill', $settings['image_use_picturefill'], false, $description );
					?>
				</td>
			</tr>
		</table>
		<?php submit_button(__('Save Changes')); ?>
	</form>
<?php
			break;
		case 'template_options':
			$form_id = $form_ids['template_options'];
			if ( !$template_selected || ( $template_selected == '__new__' ) ) {
				$new_class='';
				$update_class=' hidden';
			} else {
				$new_class=' hidden';
				$update_class='';
			}
?>
	<form method="post" id="template_options_form">
		<?php wp_nonce_field($form_ids['template_options'].'-verify'); ?>
		<input type="hidden" name="action" value="<?php echo $form_ids['template_options']; ?>" />
		<h3 class="title"><?php _e('Edit Templates', FML::SLUG); ?></h3>
		<div>
			<strong><label for="<?php echo $form_id.'-template'; ?>"><?php _e('Select template to edit:'); ?> </label></strong>
			<?php
			_page_input_select( $form_id, 'template', $edit_templates, $template_selected );
			foreach( $settings['templates'] as $template_id=>$template_content ) {
				printf(
					'<input class="hidden" id="template_%1$s" name="templates[%1$s]" value="%2$s" />',
					esc_attr($template_id),
					esc_attr($template_content)
				);
			}
			?>
			<input id="new_template_name" name="new_template_name" value="" class="code<?php echo $new_class; ?>"/>
		</div>
		<div id="template">
			<textarea cols="70" rows="30" name="content" id="content"><?php echo esc_textarea( $settings['templates'][$template_selected]); ?></textarea>
		</div>
		<p class="submit">
			<?php submit_button(__('Update Template'), 'primary'.$update_class, 'submit_update', false); ?>
			<?php submit_button(__('Delete Template'), 'delete'.$update_class, 'submit_delete', false ); ?>
			<?php submit_button(__('Create Template'), 'primary'.$new_class, 'submit_add', false ); ?>
		</p>
<?php
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
