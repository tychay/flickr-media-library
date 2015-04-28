<?php
/**
 * Submenu of Settings for Flickr Media Library
 *
 * Local Variables
 * - $is_auth_with_flickr (boolean): if we've authenticated the flickr API
 * - $settings (array): plugin blog options 
 * - $this_page_url (string): relative path back to this page
 * - $api_secret_attr (string): API secret (if not the default)
 * - $auth_form_id (string)
 * - $deauth_form_id (string)
 * 
 * @author terry chay <tychay@php.net>
 */
namespace FML;
?>
<div class="wrap">

	<h2><?php esc_html_e('Flickr Media Library Settings', FML::SLUG) ?></h2>

	<h3><?php esc_html_e('Flickr authorization', FML::SLUG); ?></h3>
	<form action="<?php esc_attr_e($this_page_url); ?>" method="post" id="flickr_auth_form">
<?php if ($is_auth_with_flickr): ?>
		<?php wp_nonce_field($deauth_form_id.'-verify'); ?>
		<input type="hidden" name="action" value="<?php echo $deauth_form_id; ?>" />
		<table class="form-table">
			<!-- begin api screen options stuff -->
			<tr class="<?php echo $api_form_slug; ?> hidden">
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
		<p class="copy"><?php echo __('Flickr authorization is needed for plugin access of private photos as well as data syncronization features.', FML::SLUG); ?></p>
		<!-- begin api screen options stuff -->
		<table class="form-table">
			<tr class="<?php echo $api_form_slug; ?> hidden">
				<th scope="row"><label for="<?php echo $api_form_slug; ?>-secret"><?php esc_html_e('Flickr API Key', FML::SLUG); ?></label></th>
				<td><input id="<?php echo $api_form_slug; ?>-apikey" name="flickr_apikey" value="<?php echo esc_attr($settings['flickr_api_key']); ?>" disabled="disabled" />
					<p class="description"><?php echo __('Leave blank if you wish to use default value.', FML::SLUG); ?></p>
				</td>
			</tr>
			<tr class="<?php echo $api_form_slug; ?> hidden">
				<th scope="row"><label for="<?php echo $api_form_slug; ?>-secret"><?php esc_html_e('Flickr API Secret', FML::SLUG); ?></label></th>
				<td><input id="<?php echo $api_form_slug; ?>-secret" name="flickr_apisecret" value="<?php echo $api_secret_attr; ?>" disabled="disabled" /></td>
			</tr>
		</table>
		<!-- end api screen options stuff -->
		<?php wp_nonce_field($auth_form_id.'-verify'); ?>
		<input type="hidden" name="action" value="<?php echo $auth_form_id; ?>" />
		<?php submit_button(__('Authorize with Flickr', FML::SLUG)); ?>
<?php endif; ?>
	</form>
<?php
// TESTING CODE
if (false):
	if ($is_auth_with_flickr) {
		$parameters = array(
			'per_page' => 10,
			'extras' => 'url_sq,path_alias',
		);
		$response = $this->_fml->flickr->call('flickr.stats.getPopularPhotos', $parameters);
	} else {
		$parameters = array(
			'user_id' => '79053562@N00',
			'per_page' => 10,
			'extras' => 'url_sq,path_alias',
		);
		$response = $this->_fml->flickr->call('flickr.photos.search', $parameters, false);
	}
	$ok = @$response['stat'];
	if ($ok != 'ok') {
		echo '<plaintext>';var_dump($response,$this->_fml->flickr,$_SESSION);
		$photos = array ('photo'=>array());
	} else {
		$photos = $response['photos'];
	}
?>
	<ul id="photos">
<?php foreach ($photos['photo'] as $photo) : ?>
		<li>
			<a href="<?php echo sprintf("http://flickr.com/photos/%s/%s/", $photo['pathalias'], $photo['id']) ?>"><img src="<?php echo $photo['url_sq'] ?>" /></a>
		</li>
<?php endforeach; ?>
	</ul>
<?php endif; ?>
</div>
