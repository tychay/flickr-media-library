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
	 * Tab ID of the "Insert from Flickr" upload tab
	 */
	private $_fml_upload_id = '';
	/**
	 * Plugin Admin function initialization 
	 * @param \FML\FML $fml the FML plugin object
	 */
	public function __construct($fml)
	{
		$this->_fml                       = $fml;
		$this->_options_page_id           = FML::SLUG.'-settings';
		$this->_flickr_auth_form_id       = FML::SLUG.'-flickr-auth';
		$this->_flickr_deauth_form_id     = FML::SLUG.'-flickr-deauth';
		$this->_flickr_apikey_option_name = str_replace('-','_',FML::SLUG).'_show_apikey';
		$this->_action_flickr_sign        = str_replace('-','_',FML::SLUG).'_sign';
		$this->_action_add_flickr         = str_replace('-','_',FML::SLUG).'_add_flickr';
		$this->_fml_upload_id             = str_replace('-','_',FML::SLUG).'_insert_flickr';

		// Stuff to do after initialization
		add_action( 'admin_init', array( $this, 'init') );
		// Add Settings & ? pages to admin menu
		add_action( 'admin_menu', array( $this, 'create_admin_menus' ) );
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
		// Add ajax server for handling ajax api options
		add_action( 'wp_ajax_'.$this->_flickr_apikey_option_name, array($this, 'handle_ajax_option_setapi') );
		// Add ajax for client to get flickr api signing
		add_action( 'wp_ajax_'.$this->_action_flickr_sign, array($this, 'handle_ajax_sign_request') );
		// Add ajax for client to adding flickr image to media library
		add_action( 'wp_ajax_'.$this->_action_add_flickr, array($this, 'handle_ajax_add_flickr') );
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
			$this->_fml->version, //version
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
	 * hide/show API key + secret screeen option settings coming via ajax
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
	 * Client side flickr request signing service (via ajax)
	 */
	public function handle_ajax_sign_request() {
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
	 * Client side ajax to add a flickr image to flickr media library (via ajax)
	 */
	public function handle_ajax_add_flickr() {
		// This nonce is created in the page.flickr-upload-form.php template
		if ( !check_ajax_referer(FML::SLUG.'-flickr-search-verify','_ajax_nonce',false) ) {
			wp_send_json(array(
				'status' => 'fail',
				'code'   => 401, // HTTP CODE for unauthorized
				'reason' => sprintf(
					__('Missing or incorrect nonce %s=%s',FML::SLUG),
					'_ajax_nonce',
					( empty($_POST['_ajax_nonce']) ) ? '' : $_POST['_ajax_nonce']
				),
			));
		}
		if ( empty( $_POST['flickr_id']) ) {
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
		// TODO add code for attaching image to post
		// TODO: wp_insert_post here
		wp_send_json(array(
			'status' => 'ok',
			'post_id' => 'TODO',
			'flickr_id' => $_POST['flickr_id'],
		));
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
	/**
	 * Filter to inject my media tab to the media uploads iframe
	 * @param  array $tabs the current tabs to show (defaults)
	 * @return array tabs + ours
	 * @todo  remove this and replace with the new backbonejs/underscoresjs model
	 */
	public function filter_media_upload_tabs( $tabs )
	{
		$tabs[$this->_fml_upload_id] = __('Insert from Flickr', FML::SLUG);
		return $tabs;
	}
	/**
	 * Returns the ipframe that the upload form will be returned in.
	 * 
	 * @return string
	 * @todo  remove this and replace with the new backbonejs/underscoresjs model
	 */
	public function get_media_upload_iframe()
	{
		$settings = $this->_fml->settings;
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
		wp_register_script(
			FML::SLUG.'-old-media-form-script',
			$this->_fml->static_url.'/js/media-upload.js',
			array('jquery','sprintf.js'), //dependencies
			false, // version
			true // in footer?
		);
		$constants = array(
			'slug'               => FML::SLUG,
			'flickr_user_id'     => $settings[Flickr::USER_NSID],
			'ajax_url'           => admin_url( 'admin-ajax.php' ),
			'sign_action'        => $this->_action_flickr_sign,
			'add_action'         => $this->_action_add_flickr,
			'msg_ajax_error'     => __('AJAX error %s (%s).', FML::SLUG),
			'msg_flickr_error'   => __('AJAX error %s (%s).', FML::SLUG),
			'msg_flickr_error_unknown '=> __('Flickr API returned an unknown error.', FML::SLUG),
			'msg_pagination'     => __('Load More'),
			'msg_loading'        => __('Loading…', FML::SLUG),
			'msg_attachment_details' => __('Attachment Details'),
			'msg_url'            => __('URL'),
			'msg_title'          => __('Title'),
			'msg_description'    => __('Description'),
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
		wp_localize_script(FML::SLUG.'-old-media-form-script', 'FMLConst', $constants);
		wp_enqueue_script(FML::SLUG.'-old-media-form-script' );
		return wp_iframe(array($this,'show_media_upload_form'));
	}
	public function show_media_upload_form()
	{

		$settings = $this->_fml->settings;
		$admin_img_dir_url = admin_url('images/');

		include $this->_fml->template_dir.'/iframe.flickr-upload-form.php';
	}
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