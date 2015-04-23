<?php
namespace FML;
/**
 * Powers the admin pages for FML
 */
class FMLAdmin
{
	/**
	 * @var \FML\FML $_fml Used to avoid having to reference globals.
	 */
	private $_fml;
	/**
	 * @var string $_options_page_id Settings page slug
	 */
	private $_options_page_id = '';
	/**
	 * @var mixed Settings page hook suffix or false if capabilities not allowed
	 */
	private $_options_suffix = false;
	/**
	 * @var string The form action for flickr auth
	 */
	private $_flickr_auth_form_id = '';
	/**
	 * @var string The form action for flickr deauth
	 */
	private $_flickr_deauth_form_id = '';
	/**
	 * User_meta field for if display apikey and secret.
	 * 
	 * (Due to future cookie stuff, this can contain only ascii, numbers and underscores.)
	 * Also used as action for ajax request
	 * @var string
	 */
	private $_flickr_apikey_option_name = '';
	/**
	 * action name for FML ajax api
	 * 
	 * We merge all ajax apis into this and instead use the "method" param to
	 * distinguish different API calls into the FML ajax API.
	 * 
	 * @var  string 
	 */
	private $action_api = '';
	/**
	 * Tab ID of the "Insert from Flickr" upload tab
	 */
	private $_fml_upload_id = '';
	/**
	 * Plugin Admin function initialization 
	 * @param \FML\FML $fml the FML plugin object
	 */
	public function __construct($fml) {
		$this->_fml                       = $fml;
		$this->_options_page_id           = FML::SLUG.'-settings';
		$this->_flickr_auth_form_id       = FML::SLUG.'-flickr-auth';
		$this->_flickr_deauth_form_id     = FML::SLUG.'-flickr-deauth';
		$this->_action_api                = str_replace('-','_',FML::SLUG).'_api';

		$this->_flickr_apikey_option_name = str_replace('-','_',FML::SLUG).'_show_apikey';
		$this->_fml_upload_id             = str_replace('-','_',FML::SLUG).'_insert_flickr';
	}
	/**
	 * Stuff to do on plugins_loaded
	 *
	 * - Register init() on admin_init
	 * - Add various menu pages (e.g. Settings) to admin menu 
	 * - Add set permalink form to Permalink page 
	 * - Add link to Settings page to Plugin Page
	 * - If in Settings page, register plugin Settings page as Flickr callback for Auth
	 * - Add various ajax servers
	 * - Add tab to media upload button
	 * 
	 * @return void
	 */
	public function run() {
		// Register init() on admin_init
		add_action( 'admin_init', array( $this, 'init') );
		// Add various menu pages (e.g. Settings) to admin menu 
		add_action( 'admin_menu', array( $this, 'create_admin_menus' ) );
		// Add set permalink form to Permalink page 
		add_action( 'load-options-permalink.php', array( $this, 'handle_permalink_form') );
		// Add link to Settings page to Plugin page
		add_filter( 'plugin_action_links_'.$this->_fml->plugin_basename, array($this, 'filter_plugin_settings_links'), 10, 2 );
		// If in Settings page register plugin Settings page as Flickr callback
		// for auth
		if ( $this->_in_options_page() ) {
			$this->_fml->flickr_callback = admin_url(sprintf(
				'options-general.php?page=%s',
				urlencode($this->_options_page_id)
				// do i need more parameters to detect flickr callback?
			));
		}
		// Add ajax servers
		add_action( 'wp_ajax_'.$this->_action_api, array($this, 'handle_ajax') );
		// - for handling ajax api options
		add_action( 'wp_ajax_'.$this->_flickr_apikey_option_name, array($this, 'handle_ajax_option_setapi') );
		// Add tab to Media upload button
		add_filter( 'media_upload_tabs', array($this, 'filter_media_upload_tabs') );
		add_action( 'media_upload_'.$this->_fml_upload_id, array($this, 'get_media_upload_iframe') );
	}

	/**
	 * Admin init.
	 *
	 * Currently this just injects a settings field on the permalink page.
	 * 
	 * @return void
	 */
	public function init()
	{
		// "As of WordPress 4.1, this function does not save settings if added to the permalink page."
		//register_setting( 'permalink', $this->_fml->permalink_slug_id, 'urlencode');
		add_settings_field(
			$this->_fml->permalink_slug_id,
			__('Flickr Media base', FML::SLUG), //title
			array($this, 'render_permalink_field'), //form render callback
			'permalink', //page
			'optional', // section
			array( //args
				'label_for' => $this->_fml->permalink_slug_id
			)
		);
	}
	/**
	 * Add the admin menus for FML to /wp_admin
	 */
	public function create_admin_menus()
	{
		$this->_options_suffix = add_options_page(
			__('Flickr Media Library Settings', FML::SLUG),
			__('Flickr Media Library', FML::SLUG),
			'manage_options',
			$this->_options_page_id,
			array( $this, 'show_settings_page' )
		);
		if ( $this->_options_suffix ) {
			add_action( 'load-'.$this->_options_suffix, array($this, 'loading_settings') );
		}
	}
	//
	// PERMALINK PAGE
	// 
	/**
	 * Save the permalink base option.
	 *
	 * This is due to a bug in the Settings.api where options-permalink.php uses
	 * the API for hooks but the URL goes to {@see options-permalink.php}
	 * instead of {@see options.php} which has the settings stuff.
	 */
	public function handle_permalink_form()
	{
		if ( !empty($_POST[$this->_fml->permalink_slug_id]) ) {
			check_admin_referer('update-permalink');
			$this->_fml->permalink_slug = urlencode($_POST[$this->_fml->permalink_slug_id]);
		}
		// pass through
	}
	/**
	 * Settings API to render base HTML form in options-permalink.php
	 * @param  [type] $args [description]
	 * @return [type]       [description]
	 */
	public function render_permalink_field($args)
	{
		$slug = $this->_fml->permalink_slug;
		printf(
			'<input name="%1$s" id="%1$s" type="text" value="%2$s" class="regular-text code" />',
			$args['label_for'],
			esc_attr($slug)
		);
	}
	//
	// SETTINGS PAGE
	// 
	/**
	 * Loading the settings page:
	 *
	 * - Handle an oAuth callback to the options page
	 * - Handle form action = authorize (_flickr_auth_form_id)
	 * - Handle form action = deauthorize (_flickr_deauth_form_id)
	 * - Add Settings contextual help tabs
	 * - Add Settings custom control to screen options
	 * - Enqueue Settings-specific Javascript (controls screenoptions)
	 *
	 * Here's the auth process:
	 * 
	 * 1) user clicks submit button and creates action $_flickr_auth_form_id
	 * 2) server authenticates and receives request token and secret from flickr
	 * 3) user gets redirected by flickr object to https://www.flickr.com/services/oauth/authorize
	 * 4) User clicks authorize
	 * 5) User gets redirected back to this page with  an oauth_verifier parameter
	 * 6) flickr plugin attempts to trade request token for access token
	 * 7) This is saved to plugin options for future requests
	 * 
	 * @return void
	 */
	public function loading_settings()
	{
	 	// Handle an oAuth callback to the options page [Steps 5-7]
		if ( !empty($_GET['oauth_verifier']) ) {
			if ( $this->_fml->flickr->authenticate('read') ) {
				// save flickr authentication
				$this->_fml->save_flickr_authentication();
				// Note auth succeeded
				add_settings_error(
					FML::SLUG, //setting name
					FML::SLUG.'-flickr-auth', //id
					__('Flickr authorization successful.', FML::SLUG),
					'updated fade'
					);
				//echo '<plaintext>'; var_dump($flickr); die('Yah!');
			} else {
				// Note auth failed (during access)
				add_settings_error(
					FML::SLUG, //setting name
					FML::SLUG.'-flickr-auth', //id
					__('Oops, something went wrong whilst trying to authorize access to Flickr.', FML::SLUG)
					);
				// TODO: note auth failed
				//echo '<plaintext>'; var_dump($flickr); die('Whoops!');
			}
		}
		if ( !empty($_POST['action']) ) {
			switch ($_POST['action']) {
				// Handle form action = authorize (_flickr_auth_form_id) [Steps 1-3]
				case $this->_flickr_auth_form_id:
					check_admin_referer( $this->_flickr_auth_form_id . '-verify' );
					$this->_fml->clear_flickr_authentication();
					if ( array_key_exists('flickr_apikey', $_POST) ) {
						if ( $_POST['flickr_apikey'] ) {
							$settings = array('flickr_api_key' => $_POST['flickr_apikey']);
						} else {
							// empty api field = reset to default
							$settings = array('flickr_api_key' => FML::_FLICKR_API_KEY);
						}
						// If API key is default, keep correct secret no matter what
						if ( $_POST['flickr_apikey'] == FML::_FLICKR_API_KEY ) {
							$settings['flickr_api_secret'] = FML::_FLICKR_SECRET;
						} elseif ( !empty($_POST['flickr_apisecret']) ) {
							$settings['flickr_api_secret'] = $_POST['flickr_apisecret'];
						}
						$this->_fml->update_settings($settings);
						// Just in case the flickr object has already been initialized....
						$this->_fml->reset_flickr();
					}
					// sign out and re-auth
					$flickr = $this->_fml->flickr;
					$flickr->signOut();
					if ( !$flickr->authenticate('read') ) {
						// Note auth failed (during request)
						add_settings_error(
							FML::SLUG, //setting name
							FML::SLUG.'-flickr-auth', //id
							__('Oops, something went wrong whilst trying to authorize access to Flickr.', FML::SLUG)
							);
						//echo '<plaintext>'; var_dump($flickr); die('Shit!');
					}
					break;
				// Handle form action = deauthorize (_flickr_deauth_form_id)
				case $this->_flickr_deauth_form_id:
					check_admin_referer( $this->_flickr_deauth_form_id . '-verify' );
					$this->_fml->clear_flickr_authentication();
			}
		}

		// Add Settings contextual help tabs
		//add_filter('contextual_help', array($this,'filter_settings_help'), 10, 3);
		$screen = get_current_screen();
		$screen->remove_help_tabs();
		/*
		$screen->add_help_tab( array(
			'id'       => FML::SLUG.'-default',
			'title'    => __('Default'),
			'content'  => '',
			'callback' => array($this,'show_settings_help_default')
		));
		*/
		$screen->add_help_tab( array(
			'id'       => FML::SLUG.'-flickrauth',
			'title'    => __('Flickr authorization', FML::SLUG),
			'content'  => '',
			'callback' => array($this,'show_settings_help_flickrauth')
		));
		$screen->set_help_sidebar($this->_get_settings_help_sidebar());
		// Add Settings custom control to screen options tab
		add_filter('screen_settings',array($this,'filter_settings_screen_options'),10,2);
		// Enqueue Settings-specific Javascript (controls screenoptions)
		wp_enqueue_script(
			FML::SLUG.'-screen-settings', //handle
			$this->_fml->static_url.'/js/admin-settings.js', //src
			array('jquery'), //dependencies ajax
			FML::VERSION, //version
			true //in footer?
		);
		// Save Settings screen options tab
		//add_filter('set-screen-option', array($this,'filter_settings_set_screen_options'), 11, 3);
	}
	/**
	 * Render the default help tab for settings
	 * @return void
	 */
	public function show_settings_help_default()
	{
		include $this->_fml->template_dir.'/help.settings-overview.php';
	}
	/**
	 * Render the "Flickr Authorization" help tab for plugin settings page.
	 * @return void
	 */
	public function show_settings_help_flickrauth()
	{
		include $this->_fml->template_dir.'/help.settings-flickrauth.php';
	}
	/**
	 * Return contents of the help sidebar in the plugin settings page
	 * @return string
	 */
	private function _get_settings_help_sidebar()
	{
		ob_start();
		include $this->_fml->template_dir.'/help.settings-sidebar.php';
		return ob_get_clean();
	}
	/**
	 * Tack on checkbox for customizing Flickr API Key
	 * 
	 * @param string $screen_settings The current state of the return value
	 * @param WP_SCREEN $screen the screen object that triggered this
	 * @return  string form element html to tack to the end of screen options
	 */
	public function filter_settings_screen_options($screen_settings, $screen)
	{
		// We should only be triggered if in the right screen already
		$form_html = sprintf(
			'<div class="%1$s %4$s"><label for="%1$s-toggle"><input type="checkbox" id="%1$s-toggle"%2$s/>%3$s</label></div>',
			$this->_flickr_apikey_option_name,
			checked( get_user_setting( $this->_flickr_apikey_option_name, 'off' ), 'on', false ),
			__( 'Show Flickr API Key and Secret.', FML::SLUG ),
			'hidden'
		);
		return $form_html . $screen_settings;
	}
	/**
	 * Filter used to define what screen_options are saved to user_meta
	 * @param mixed $status current value of screen option (if false, it does not save)
	 * @param string $option the option name that triggered filter
	 * @param mixed $value  the value assigned to the option (e.g. number of rows to use)
	 * @return mixed the value to set the screen option to (if false, don't save at all)
	 */
	/*
	public function filter_settings_set_screen_options($status, $option, $value)
	{
		if ($option == $this->_flickr_apikey_option_name) {
			return $value;
		}
		// pass through filter
		return $status;
	}
	*/
	/**
	 * hide/show API key + secret screen option settings coming via ajax
	 */
	public function handle_ajax_option_setapi() {
		// we are pirating the nonce created in screen for screen options :-)
		check_ajax_referer('screen-options-nonce','screenoptionnonce');
		//var_dump($_POST);
		if ( !empty($_POST[$this->_flickr_apikey_option_name]) ) {
			set_user_setting($this->_flickr_apikey_option_name, $_POST[$this->_flickr_apikey_option_name]);
			//die('here :-)');
		}
		//die('there :-(');
	}
	/**
	 * Show the Settings (Options) page which allows you to do Flickr oAuth.
	 */
	public function show_settings_page()
	{
		$is_auth_with_flickr = $this->_fml->is_flickr_authenticated();
		//$flickr = $this->_fml->flickr;
		$settings = $this->_fml->settings;
		$this_page_url = 'options-general.php?page=' . urlencode($this->_options_page_id);
		$api_form_slug = FML::SLUG.'-apiform';
		$api_secret_attr = ( $settings['flickr_api_secret'] == FML::_FLICKR_SECRET )
		                 ? ''
		                 : $settings['flickr_api_secret'];
		include $this->_fml->template_dir.'/page.settings.php';
	}
	//
	// PLUGINS PAGE
	// 
	/**
	 * Filter to inject a Settings link to plugin on the Plugin page
	 * 
	 * @param  array $actions object to be filtered
	 * @return array the $actions array with the link injected into it
	 */
	public function filter_plugin_settings_links( $actions, $plugin_file )
	{
		$actions[] = sprintf(
			'<a href="%s">%s</a>',
			admin_url('options-general.php?page='.FML::SLUG.'-settings'),
			__('Settings')
			);
		return $actions;
	}
	//
	// MEDIA UPLOAD IFRAME
	// 
	/**
	 * Client side flickr request signing service (via ajax)
	 */
	public function handle_ajax_sign_request()
	{
		// This nonce is created in the page.flickr-upload-form.php template
		if ( !check_ajax_referer(FML::SLUG.'-flickr-search-verify','_ajax_nonce',false) ) {
			wp_send_json(array(
				'status' => 'fail',
				'code'   => 401, //HTTP code for unauthorized
				'reason' => sprintf(
					__('Missing or incorrect nonce %s=%s',FML::SLUG),
					'_ajax_nonce',
					( empty($_POST['_ajax_nonce']) ) ? '' : $_POST['_ajax_nonce']
				),
			));
			//dies
		}
		if ( empty( $_POST['request_data']) ) {
			wp_send_json(array(
				'status' => 'fail',
				'code'   => 400, //HTTP code bad request
				'reason' => sprintf(
					__('Missing parameter: %s',FML::SLUG),
					'request_data'
				),
			));
			//dies
		}
		$json = @json_decode(stripslashes($_POST['request_data']));
		if ( empty($json) || !is_object($json) ) {
			wp_send_json(array(
				'status' => 'fail',
				'code'   => 400, //HTTP code bad request
				'reason' => sprintf(
					__('Invalid JSON input: %s',FML::SLUG),
					$_POST['request_data']
				),
			));
			//dies
		}

		$json->api_key = $this->_fml->settings['flickr_api_key'];
		wp_send_json(array(
			'status' => 'ok',
			'signed' => $this->_fml->flickr->getSignedUrlParams(
				$json->method,
				get_object_vars($json)
			),
		));
		//header('Content-type: application/json');
		//echo json_encode($return);
		//die();
	}
	/**
	 * Process all client side ajax requests
	 *
	 * The following parameters are required in $_POST
	 * - action: $this->_action_api already used to trigger this
	 * - method: which method to call
	 * - ??: nonce varies based on where form is
	 * - ??: other parameters vary based on method
	 *
	 * The following methods are supported
	 *
	 * - sign_flickr_request: sign a client size TBD Flickr API reqest with
	 *   request_data (returns 'signed')
	 * - get_media_by_flickr_id: get existing post information by flickr_id
	 *   (returns post_id=0 if none found)
	 * - new_media_from_flickr_id: create a new post from the flickr_id
	 *   (will update the post if post already exists)
	 * - send_attachment_to_editor: emulates wp_send_atttachment_to_editor()
	 *   for flickr media library posts
	 */
	public function handle_ajax()
	{
		//print_r($_POST);
		$this->_require_ajax_post( 'method' );
		switch ( $_POST['method'] ) {
			case 'sign_flickr_request':
				// This nonce is created in the page.flickr-upload-form.php template
				$this->_verify_ajax_nonce( FML::SLUG.'-flickr-search-verify', '_ajax_nonce' );
				$this->_require_ajax_post( 'request_data' ); //the API call to sign
				$json = @json_decode(stripslashes($_POST['request_data']));
				if ( empty($json) || !is_object($json) ) {
					wp_send_json(array(
						'status' => 'fail',
						'code'   => 400, //HTTP code bad request
						'reason' => sprintf(
							__('Invalid JSON input: %s',FML::SLUG),
							$_POST['request_data']
						),
					));
				}
				$json->api_key = $this->_fml->settings['flickr_api_key'];
				$return = array(
					'status' => 'ok',
					'signed' => $this->_fml->flickr->getSignedUrlParams(
						$json->method,
						get_object_vars($json)
					),
				);
				break;
			case 'get_media_by_flickr_id':
				// This nonce is created in the page.flickr-upload-form.php template
				$this->_verify_ajax_nonce( FML::SLUG.'-flickr-search-verify', '_ajax_nonce' );
				$this->_require_ajax_post( 'flickr_id' );
				$post = $this->_fml->get_media_by_flickr_id( $_POST['flickr_id'] );
				if ( $post ) {
					$this->_fml->update_flickr_post($post);  //TODO: temporary repair of broken posts
					$return = array(
						'status'    => 'ok',
						'flickr_id' => $_POST['flickr_id'],
						'post_id'   => $post->ID,
						'post'      => $this->_fml->wp_prepare_attachment_for_js($post),
					);
				} else {
					$return = array(
						'status'    => 'ok',
						'post_id'   => 0,
						'flickr_id' => $_POST['flickr_id'],
					);
				}
				break;
			case 'new_media_from_flickr_id':
				// This nonce is created in the page.flickr-upload-form.php template
				$this->_verify_ajax_nonce( FML::SLUG.'-flickr-search-verify', '_ajax_nonce' );
				$this->_require_ajax_post( 'flickr_id' );
				$post = $this->_fml->add_flickr($_POST['flickr_id']);
				$update = array();
				if ( !empty( $_POST['caption'] ) ) {
					$update['post_excerpt'] = $_POST['caption'];
				}
				if ( !empty( $_POST['alt']) ) {
					update_post_meta( $post->ID, '_wp_attachment_image_alt', $_POST['alt'] );
				}
				if ( !empty($update) ) {
					$update['ID'] = $post->ID;
					$post_id = wp_update_post( $update );
					// data's been changed.
					$post = wp_get_post($post->ID);
				}
				$return = array(
					'status'    => 'ok',
					'flickr_id' => $_POST['flickr_id'],
					'post_id'   => $post->ID,
					'post'      => $this->_fml->wp_prepare_attachment_for_js($post),
				);
				break;
			case 'send_attachment_to_editor':
				// emulate wp_ajax_send_attachment_to_editor() as 'attachment'
				// post type is hard coded
				$this->_verify_ajax_nonce( FML::SLUG.'-flickr-search-verify', '_ajax_nonce' );
				$this->_require_ajax_post( 'attachment', array('id',) );
				$attachment = $_POST['attachment']; //wp_unslash is outdated
				$id = intval( $attachment['id'] );
				if ( ! $post = get_post( $id ) ) {
					$this->_send_json_fail( -100, sprintf(
						__('Incorrect parameter %s=%s', FML::SLUG ),
						'attachment[id]',
						$id
					) );
				}
				if ( $post->post_type != FML::POST_TYPE ) {
					$this->_send_json_fail( -101, sprintf(
						__('Incorrect parameter %s=%s', FML::SLUG ),
						'attachment[id]',
						$id
					) );
				}
				// TODO: don't attach unnattached attachment for flickr media
				// TODO: we don't support custom URLs because of flickr TOS
				$url = '';
				$rel = false;
				if ( !empty( $attachment['link'] ) ) {
					switch ( $attachment['link'] ) {
						case 'file':
						//$url = get_attached_file( $id );
						//Flickr community guidelines: link the download page
						$url = $this->_fml->get_flickr_link( $id ).'sizes/';
						break;
						case 'post':
						$url = get_permalink( $id );
						$rel = true;
						break;
						case 'custom':
						$url = $this->_fml->get_flickr_link( $id );
						break;
						default:
						$url = '';
						// no link
					}
					// consume attachment link
					/*
					if ( empty( $url ) ) {
						unset( $attachment['link'] );
					}
					*/
				}
				//https://developer.wordpress.org/reference/functions/get_image_send_to_editor/
				remove_filter( 'media_send_to_editor', 'image_media_send_to_editor' );
				$html = '';
				if ( wp_attachment_is_image( $id ) ) {
					$html = get_image_send_to_editor(
						$id,
						( isset( $attachment['post_excerpt'] ) ) ? $attachment['post_excerpt'] : '', //caption
						$post->title, //insert title as it is NOT redundant
						isset( $attachment['align'] ) ? $attachment['align'] : 'none',
						$url,
						$rel,
						( isset( $attachment['image-size'] ) ) ? str_replace( ' ', '_', $attachment['image-size'] ) : 'Medium', // replace spaces in image-size so it can be used in class names
						( isset( $attachment['image_alt'] ) ) ? $attachment['image_alt'] : ''
					);
				} //TODO: add support for video
				// https://developer.wordpress.org/reference/hooks/media_send_to_editor/
				$html = apply_filters( 'media_send_to_editor', $html, $id, $attachment );
				$return = array(
					'status'    => 'ok',
					'html'      => $html,
				);
				if ( !empty( $_POST['post_id'] ) ) {
					$return['post_id'] = $_POST['post_id'];
				}
				break;
			default:
				$this->_send_json_fail( 406, sprintf( //HTTP Method Not Allowed
					__('Incorrect parameter %s=%s', FML::SLUG ),
					'method',
					$_POST['method']
				) );
				//dies
		}
		wp_send_json( $return );
	}
	/**
	 * If a parameter is missing, AJAX return a 400 Fail
	 * 
	 * @param  string      $post_key a post variable that must be set (and not
	 *                               zero). This should be html-safe
	 * @param  array|false $keys     provide if form has subkeys to be checked
	 * @return void|die
	 */
	private function _require_ajax_post( $post_key, $keys=false ) {
		if ( empty ( $_POST[$post_key] ) ) {
			$this->_send_json_fail( 400, sprintf( //HTTP Bad Request
					__('Missing parameter: %s',FML::SLUG),
					$post_key
			) );
			// dies
		}
		if ( !is_array($keys) ) { return; }
		foreach ( $keys as $key ) {
			if ( !empty( $_POST[$post_key][$key] ) ) { continue; }
			$this->_send_json_fail( 400, sprintf( //HTTP Bad Request
					__('Missing parameter: %s',FML::SLUG),
					$post_key.'['.$key.']'
			) );
			// dies
		}
	}
	/**
	 * Verify the ajax request has correct nonce. If not, return 401 Fail.
	 * 
	 * @param  string $action    the hidden form that had the nonce
	 * @param  string $query_arg where in $_POST to look for nonce
	 * @return void|die
	 */
	private function _verify_ajax_nonce( $action, $query_arg ) {
		if ( !check_ajax_referer( $action , $query_arg, false) ) {
			$this->_send_json_fail( 401, sprintf( //HTTP Unauthorized
				__('Missing or incorrect nonce %s=%s',FML::SLUG),
				$query_arg,
				( empty($_POST[$query_arg]) ) ? '' : $_POST[$query_arg]
			) );
		}
	}
	/**
	 * Shortcut to wp_send_json a failure
	 * 
	 * @param  int    $code machine readable failure code
	 * @param  string $text "human" readable localized error string
	 * @return die
	 */
	private function _send_json_fail( $code, $text ) {
		wp_send_json( array(
			'status' => 'fail',
			'code'   => $code,
			'reason' => $text,
		) );
	}
	/**
	 * Filter to inject my media tab to the media uploads iframe
	 * @param  array $tabs the current tabs to show (defaults)
	 * @return array tabs + ours
	 * @todo  remove this and replace with the new backbonejs/underscoresjs model
	 */
	public function filter_media_upload_tabs( $tabs ) {
		$tabs[$this->_fml_upload_id] = __('Insert from Flickr', FML::SLUG);
		return $tabs;
	}
	/**
	 * Returns the iframe that the upload form will be returned in.
	 * 
	 * @return string
	 * @todo  remove this and replace with the new backbonejs/underscoresjs model
	 */
	public function get_media_upload_iframe() {
		/*
		if ( empty($_GET['post_id']) ) {
			wp_enqueue_media();
		} else {
			wp_enqueue_media( array( 'post' => (int) $_GET['post_id'] ) );

		}
		// has some constants to make our lives easier
		wp_enqueue_script('media-views');
		*/
		wp_enqueue_style(
			'media-views',
			admin_url('css/media-views.css')
			// deps
			// ver
			// media
		);
		wp_enqueue_style(
			FML::SLUG.'-old-media-form-style',
			$this->_fml->static_url.'/css/media-upload-basic.css',
			'media-views'
			// ver
			// media
		);
		wp_register_script(
			'sprintf.js',
			$this->_fml->static_url.'/js/sprintf.js',
			array('jquery'), //dependencies
			'71d33bf', // version
			true // in footer?
		);
		// for it to queue properly, picturefill.js needs to be patched.
		// still it's not rendering 1x, 2x properly in firefox
		if( defined('PICTUREFILL_WP_VERSION') ) {
			wp_enqueue_script('picturefill');
		}
		wp_register_script(
			FML::SLUG.'-old-media-form-script',
			$this->_fml->static_url.'/js/media-upload.js',
			array('jquery','sprintf.js'), //dependencies
			false, // version
			true // in footer?
		);
		$settings = $this->_fml->settings;
		// TODO: Make this configurable
		// see wp_enqueue_media()
		$props = array(
			'link'  => get_option( 'image_default_link_type' ), // db default is 'file'
			'align' => get_option( 'image_default_align' ), // empty default
			'size'  => ucfirst(get_option( 'image_default_size' )),  // empty default
			// capitalize to make it have a chance of matching flickr's sizes if set
		);
		if ( !empty( $_GET['post_id'] ) ) {
			$post_id = (int) $_GET['post_id'];
			$post = get_post($post_id);
			$hier = $post && is_post_type_hierarchical( $post->post_type );
			$insert_msg = ( $hier ) ? __( 'Insert into page' ) : __( 'Insert into post' );
		} else {
			$post_id = 0;
			$insert_msg = __( 'Insert into post' );
		}
		$constants = array(
			'slug'               => FML::SLUG,
			'flickr_user_id'     => $settings[Flickr::USER_NSID],
			'ajax_url'           => admin_url( 'admin-ajax.php' ),
			'ajax_action_call'   => $this->_action_api,
			'default_props'      => $props,
			'msgs_error'         => array(
				'ajax'       => __('AJAX error %s (%s).', FML::SLUG),
				'flickr'     => __('Flickr API error %s (%s).', FML::SLUG),
				'flickr_unk' => __('Flickr API returned an unknown error.', FML::SLUG),
				'fml'        => __('Flickr Media Library API error %s (%s).', FML::SLUG),
			),
			'msgs_pagination' => array(
				'load'    => __('Load More'),
				'loading' => __('Loading…', FML::SLUG),
			),
			'msgs_attachment'    => array(
				'attachment_details'  => __( 'Attachment Details' ),
				'attachment_settings' => __( 'Attachment Display Settings' ),
				'url'                 => __( 'URL' ),
				'title'               => __( 'Title' ),
				'description'         => __( 'Description' ),
				'caption'             => __( 'Caption' ),
				'alt'                 => __( 'Alt Text' ),
				'alignment'           => __( 'Alignment' ),
				'left'                => __( 'Left' ),
				'center'              => __( 'Center' ),
				'right'               => __( 'Right' ),
				'none'                => __( 'None' ),
				'linkto'              => __( 'Link To' ),
				'file'                => __( 'Media File' ),
				'post'                => __( 'Attachment Page' ),
				'flickr'              => __( 'Flickr Page' ),
				'size'                => __( 'Size' ),
			),
			'msgs_add_btn'       => array(
				'add_to'  => __( 'Add to media library', FML::SLUG ),
				'insert'  => $insert_msg,
				'adding'  => __( 'Adding…', FML::SLUG ),
				'query'   => __( 'Querying…', FML::SLUG ),
				//'already' => __( 'Already added', FML::SLUG ),
			),
			'msgs_sort'			 => array(
				'date-posted-desc'     => __('Date posted (desc)', FML::SLUG),
				'date-posted-asc'      => __('Date posted (asc)', FML::SLUG),
				'date-taken-desc'      => __('Date taken (asc)', FML::SLUG),
				'date-taken-desc'      => __('Date taken (desc)', FML::SLUG),
				'interestingness-asc'  => __('Interestingness (asc)', FML::SLUG),
				'interestingness-desc' => __('Interestingness (desc)', FML::SLUG),
				'relevance'            => __('Relevance', FML::SLUG),
			),
			//'plugin_uri' => 
			//'plugin_img_uri' =>
			//'msg_pages'          => __('(%1$s / %2$s page(s), %3$s photo(s))', FML::SLUG),
			//'setting_photo_link' => 1,
			//'setting_link_rel'   => 'testrel',
			//'setting_link_class' => 'testclass',
			'flickr_errors'      => array(
				0 => __('No photos found', FML::SLUG),
				1 => __('Too many tags in ALL query', FML::SLUG),
				2 => __('Unknown user', FML::SLUG),
				3 => __('Parameter-less searches have been disabled', FML::SLUG),
				4 => __('You don’t have permission to view this pool', FML::SLUG),
				10 => __('Sorry, the Flickr search API is currently unavailable.', FML::SLUG),
				11 => __('No valid machine tags', FML::SLUG),
				12 => __('Exceeded maximum allowable machine tags', FML::SLUG),
				100 => __('Invalid API key', FML::SLUG),
				104 => __('Service currently unavailable', FML::SLUG),
				999 => __('Unknonw error', FML::SLUG),
			),
		);
		if ( $post_id ) {
			$constants['post_id'] = $post_id;
		}
		wp_localize_script(FML::SLUG.'-old-media-form-script', 'FMLConst', $constants);
		wp_enqueue_script(FML::SLUG.'-old-media-form-script' );
		return wp_iframe(array($this,'show_media_upload_form'));
	}
	/**
	 * render the iframe content for upload form
	 * 
	 * @return void
	 */
	public function show_media_upload_form() {

		$settings = $this->_fml->settings;
		$admin_img_dir_url = admin_url( 'images/' );

		include $this->_fml->template_dir.'/iframe.flickr-upload-form.php';
	}
	//
	// UTILITY FUNCTIONS
	// 
	/**
	 * Are we viewing the plugin settings page?
	 * @return boolean true if viewing optiosn page
	 */
	private function _in_options_page()
	{
		return $this->_in_page('options-general.php', $this->_options_page_id);
	}
	/**
	 * What admin page are we in?
	 * @param  string $menu    menu page url or part of the url
	 * @param  string $submenu submenu name
	 * @return boolean
	 */
	private function _in_page($menu, $submenu)
	{
		if ( strpos(basename($_SERVER['PHP_SELF']), $menu) !== 0 ) {return false; }
		if ( empty($submenu) ) { return true; }
		if ( !isset($_GET['page']) ) { return false; }
		if ( strpos($_GET['page'], $submenu) !== 0 ) { return false; }
		return true;
	}
}