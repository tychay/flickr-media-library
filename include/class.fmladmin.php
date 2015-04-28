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
	 * A hash containing ids to html elements, pages, scripts, etc. that are
	 * used in multiple places in the object.
	 *
	 * - page_add_media: the page: Media > Add Flickr
	 * - page_options: the page: Settings > Flickr Media Library
	 * - form_flickr_auth: form action for flickr authentication
	 * - form_flickr_deauth: form action for flickr deauthentication
	 * - ajax_action: action name for FML ajax API (all ajax apis are merged into
	 *                this one and "method" is used to disnguish between
	 *                different API calls into teh FML ajax API)
	 * - tab_media_upload: the tab id of the "Insert from Flickr" upload tab
	 * @var array
	 */
	private $_ids = array();
	/**
	 * User_meta field for if display apikey and secret.
	 * 
	 * (Due to future cookie stuff, this can contain only ascii, numbers and underscores.)
	 * Also used as action for ajax request
	 * @var string
	 * @deprecated will be getting rid of this soon
	 */
	private $_flickr_apikey_option_name = '';
	/**
	 * Plugin Admin function initialization 
	 * 
	 * @param \FML\FML $fml the FML plugin object
	 */
	public function __construct($fml) {
		$this->_fml                       = $fml;
		$this->_ids = array(
			'page_add_media'     => FML::SLUG.'-add-flickr',
			'page_options'       => FML::SLUG.'-settings',
			'form_flickr_auth'   => FML::SLUG.'-flickr-auth',
			'form_flickr_deauth' => FML::SLUG.'-flickr-deauth',
			'ajax_action'        => str_replace('-','_',FML::SLUG).'_api',
			'tab_media_upload'   => str_replace('-','_',FML::SLUG).'_insert_flickr',
		);

		$this->_flickr_apikey_option_name = str_replace('-','_',FML::SLUG).'_show_apikey';
	}
	/**
	 * Stuff to do on plugins_loaded
	 *
	 * - Register init() on admin_init
	 * - Add menu pages (e.g. Settings) to admin menu 
	 * - Plugins: Add link to Settings page to Plugin Page
	 * - Settings > Permalink: Add handling of permalink form to Permalink page 
	 * - AJAX: Add various ajax servers
	 * - Media Library: Add init handlers for custom post pages
	 * - Media Upload: Add tab to media upload button
	 * 
	 * @return void
	 */
	public function run() {
		// Register init() on admin_init
		add_action( 'admin_init', array( $this, 'init') );
		// Add various menu pages (e.g. Settings) to admin menu 
		add_action( 'admin_menu', array( $this, 'create_admin_menus' ) );
		// Add link to Settings page to Plugin page
		add_filter( 'plugin_action_links_'.$this->_fml->plugin_basename, array($this, 'filter_plugin_settings_links'), 10, 2 );
		// Add set permalink form to Permalink page 
		add_action( 'load-options-permalink.php', array( $this, 'handle_permalink_form') );
		// Add ajax servers
		add_action( 'wp_ajax_'.$this->_ids['ajax_action'], array($this, 'handle_ajax') );
		// - for handling ajax api options
		add_action( 'wp_ajax_'.$this->_flickr_apikey_option_name, array($this, 'handle_ajax_option_setapi') );
		// Add init handlers for custom post pages
		add_action( 'load-edit.php', array( $this, 'loading_edit') );
		add_action( 'load-post.php', array( $this, 'loading_post') );
		add_action( 'load-post-new.php', array( $this, 'loading_post_new') );
		// Add tab to Media upload button
		add_filter( 'media_upload_tabs', array( $this, 'filter_media_upload_tabs' ) );
		add_action( 'media_upload_'.$this->_ids['tab_media_upload'], array( $this, 'get_media_upload_iframe' ) );
	}
	/**
	 * Admin init.
	 *
	 * Currently this just injects a settings field on the permalink page.
	 * 
	 * @return void
	 */
	public function init() {
		// "As of WordPress 4.1, this function does not save settings if added to the permalink page."
		//register_setting( 'permalink', $this->_fml->permalink_slug_id, 'urlencode');
		add_settings_field(
			$this->_fml->permalink_slug_id,
			__( 'Flickr Media base', FML::SLUG ), //title
			array( $this, 'render_permalink_field' ), //form render callback
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
	public function create_admin_menus() {
		$_options_suffix = add_options_page(
			__( 'Flickr Media Library Settings', FML::SLUG ),
			__( 'Flickr Media Library', FML::SLUG ),
			'manage_options',
			$this->_ids['page_options'],
			array( $this, 'show_settings_page' )
		);
		if ( $_options_suffix ) {
			add_action( 'load-'.$_options_suffix, array( $this, 'loading_settings' ) );
		}
		$_add_media_suffix = add_media_page(
			__( 'Add New Flickr Media', FML::SLUG ),
			__( 'Add Flickr', FML::SLUG ),
			'edit_posts',
			$this->_ids['page_add_media'],
			array( $this, 'show_add_media_page' )
		);
		if ( $_add_media_suffix ) {
			add_action( 'load-'.$_add_media_suffix, array( $this, 'loading_add_media' ) );
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
	 *
	 * Note that things like settings-updated=true will be handled by the
	 * default things itself.
	 */
	public function handle_permalink_form() {
		if ( !empty( $_POST[$this->_fml->permalink_slug_id] ) ) {
			check_admin_referer('update-permalink');
			$this->_fml->permalink_slug = urlencode( $_POST[$this->_fml->permalink_slug_id] );
		}
		// pass through
	}
	/**
	 * Settings API to render base HTML form in options-permalink.php
	 * @param  [type] $args [description]
	 * @return [type]       [description]
	 */
	public function render_permalink_field($args) {
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
	 * - Register this page as Flickr callback for Auth
	 * - Handle an oAuth callback to the options page
	 * - Handle form action = authorize (_ids[form_flickr_auth])
	 * - Handle form action = deauthorize (_ids[form_flickr_deauth])
	 * - Add Settings contextual help tabs
	 * - Add Settings custom control to screen options
	 * - Enqueue Settings-specific Javascript (controls screenoptions)
	 *
	 * Here's the auth process:
	 * 
	 * 1) user clicks submit button and creates action $_ids[form_flickr_auth]
	 * 2) server authenticates and receives request token and secret from flickr
	 * 3) user gets redirected by flickr object to https://www.flickr.com/services/oauth/authorize
	 * 4) User clicks authorize
	 * 5) User gets redirected back to this page with  an oauth_verifier parameter
	 * 6) flickr plugin attempts to trade request token for access token
	 * 7) This is saved to plugin options for future requests
	 * 
	 * @return void
	 */
	public function loading_settings() {
		// Register this page as the Flickr callback for oAuth
		$this->_fml->flickr_callback = admin_url(sprintf(
			'options-general.php?page=%s',
			urlencode($this->_ids['page_options'])
			// do i need more parameters to detect flickr callback?
		));
	 	// Handle an oAuth callback to the options page [Steps 5-7]
		if ( !empty( $_GET['oauth_verifier'] ) ) {
			if ( $this->_fml->flickr->authenticate( 'read' ) ) {
				// save flickr authentication
				$this->_fml->save_flickr_authentication();
				// Note auth succeeded
				add_settings_error(
					FML::SLUG, //setting name
					FML::SLUG.'-flickr-auth', //id
					__( 'Flickr authorization successful.', FML::SLUG ),
					'updated fade'
					);
				//echo '<plaintext>'; var_dump($flickr); die('Yah!');
			} else {
				// Note auth failed (during access)
				add_settings_error(
					FML::SLUG, //setting name
					FML::SLUG.'-flickr-auth', //id
					__( 'Oops, something went wrong whilst trying to authorize access to Flickr.', FML::SLUG )
					);
				// TODO: note auth failed
				//echo '<plaintext>'; var_dump($flickr); die('Whoops!');
			}
		}
		// Handle form posts that are not otherwise supported
		// - authorize: oAuth with Flickr
		add_action( 'admin_action_'.$this->_ids['form_flickr_auth'], array( $this, 'handle_auth_form') );
		// - deauthorize: remove oAuth settings
		add_action( 'admin_action_'.$this->_ids['form_flickr_deauth'], array( $this, 'handle_deauth_form') );

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
			'callback' => array( $this, 'show_settings_help_flickrauth' ),
		));
		$screen->set_help_sidebar( $this->_get_settings_help_sidebar() );
		// Add Settings custom control to screen options tab
		add_filter('screen_settings',array( $this, 'filter_settings_screen_options' ), 10, 2 );
		// Enqueue Settings-specific Javascript (controls screenoptions)
		wp_enqueue_script(
			FML::SLUG.'-screen-settings', //handle
			$this->_fml->static_url.'/js/admin-settings.js', //src
			array( 'jquery' ), //dependencies ajax
			FML::VERSION, //version
			true //in footer?
		);
		// Save Settings screen options tab
		//add_filter('set-screen-option', array($this,'filter_settings_set_screen_options'), 11, 3);
	}
	/**
	 * Form request to (start) Flickr oAuth.
	 * 
	 * @return void
	 */
	public function handle_auth_form() {
		check_admin_referer( $this->_ids['form_flickr_auth'] . '-verify' );
		$this->_fml->clear_flickr_authentication();
		if ( array_key_exists( 'flickr_apikey', $_POST ) ) {
			if ( $_POST['flickr_apikey'] ) {
				$settings = array( 'flickr_api_key' => $_POST['flickr_apikey'] );
			} else {
				// empty api field = reset to default
				$settings = array( 'flickr_api_key' => FML::_FLICKR_API_KEY );
			}
			// If API key is default, keep correct secret no matter what
			if ( $_POST['flickr_apikey'] == FML::_FLICKR_API_KEY ) {
				$settings['flickr_api_secret'] = FML::_FLICKR_SECRET;
			} elseif ( !empty( $_POST['flickr_apisecret'] ) ) {
				$settings['flickr_api_secret'] = $_POST['flickr_apisecret'];
			}
			$this->_fml->update_settings($settings);
			// Just in case the flickr object has already been initialized....
			$this->_fml->reset_flickr();
		}
		// sign out and re-auth
		$flickr = $this->_fml->flickr;
		$flickr->signOut();
		if ( !$flickr->authenticate( 'read' ) ) {
			// Note auth failed (during request)
			add_settings_error(
				FML::SLUG, //setting name
				FML::SLUG.'-flickr-auth', //id
				__( 'Oops, something went wrong whilst trying to authorize access to Flickr.', FML::SLUG )
				);
			//echo '<plaintext>'; var_dump($flickr); die('Shit!');
		}
	}
	/**
	 * Form request to remove Flickr oAuth settings
	 * 
	 * @return void
	 */
	public function handle_deauth_form() {
		check_admin_referer( $this->_ids['form_flickr_deauth'] . '-verify' );
		$this->_fml->clear_flickr_authentication();
	}
	/**
	 * Render the default help tab for settings
	 * @return void
	 */
	public function show_settings_help_default() {
		include $this->_fml->template_dir.'/help.settings-overview.php';
	}
	/**
	 * Render the "Flickr Authorization" help tab for plugin settings page.
	 * @return void
	 */
	public function show_settings_help_flickrauth() {
		include $this->_fml->template_dir.'/help.settings-flickrauth.php';
	}
	/**
	 * Return contents of the help sidebar in the plugin settings page
	 * @return string
	 */
	private function _get_settings_help_sidebar() {
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
	public function filter_settings_screen_options($screen_settings, $screen) {
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
	public function filter_settings_set_screen_options($status, $option, $value) {
		if ( $option == $this->_flickr_apikey_option_name ) {
			return $value;
		}
		// pass through filter
		return $status;
	}
	*/
	/**
	 * hide/show API key + secret screen option settings coming via ajax
	 *
	 * @todo  probably needs moving somewhere else
	 */
	public function handle_ajax_option_setapi() {
		// we are pirating the nonce created in screen for screen options :-)
		check_ajax_referer( 'screen-options-nonce', 'screenoptionnonce' );
		//var_dump($_POST);
		if ( !empty( $_POST[$this->_flickr_apikey_option_name] ) ) {
			set_user_setting( $this->_flickr_apikey_option_name, $_POST[$this->_flickr_apikey_option_name] );
			//die('here :-)');
		}
		//die('there :-(');
	}
	/**
	 * Show the Settings (Options) page which allows you to do Flickr oAuth.
	 */
	public function show_settings_page() {
		$is_auth_with_flickr = $this->_fml->is_flickr_authenticated();
		//$flickr = $this->_fml->flickr;
		$settings        = $this->_fml->settings;
		$this_page_url   = 'options-general.php?page=' . urlencode( $this->_ids['page_options'] );
		$api_form_slug   = FML::SLUG.'-apiform';
		$api_secret_attr = ( $settings['flickr_api_secret'] == FML::_FLICKR_SECRET )
		                 ? ''
		                 : $settings['flickr_api_secret'];
		$auth_form_id    = $this->_ids['form_flickr_auth'];
		$deauth_form_id  = $this->_ids['form_flickr_deauth'];
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
	public function filter_plugin_settings_links( $actions, $plugin_file ) {
		$actions[] = sprintf(
			'<a href="%s">%s</a>',
			esc_attr( admin_url( 'options-general.php?page='.FML::SLUG.'-settings' ) ),
			__('Settings')
			);
		return $actions;
	}
	// 
	// CUSTOM POST PAGES
	// 
	// LIST (edit.php)
	/**
	 * Injects custom post-specific stuff for edit.php
	 * 
	 * Triggers on displaying custom post list view screen (edit.php).
	 *
	 * - add filter to manage which columns exists
	 * - add filter for rendering new column
	 * - no need to add sortable columns list
	 * @return [type] [description]
	 */
	public function loading_edit() {
		$screen = get_current_screen();
		// ONLY OPERATE ON FLICKR MEDIA
		if ( $screen->post_type != FML::POST_TYPE ) { return; }

		add_filter( 'manage_'.FML::POST_TYPE.'_posts_columns', array( $this, 'filter_manage_post_columns' ) );
		add_action( 'manage_'.FML::POST_TYPE.'_posts_custom_column', array( $this, 'manage_posts_custom_column' ), 10, 2 );
		//add_filter( 'manage_edit-'.FML::POST_TYPE.'_sortable_columns', array( $this, 'filter_manage_sortable_colu,ms' ) );
	}
	/**
	 * Control which columns exist/are displayed
	 *
	 * By default the $cols array looks like:
	 * - cb: <input type="checkbox" />
	 * - title: Title
	 * - tags: Tags
	 * - date: Date
	 * 
	 * @param  array $cols  Hash of column ids and their title name
	 * @return array        filtered
	 */
	public function filter_manage_post_columns( $cols ) {
		$return = array(
			'cb' => $cols['cb'],
			'icon' => '', // core has already this to be 80px wide
			'title' => $cols['title'],
			'tags' => $cols['tags'],
			'date' => $cols['date'],
		);
		return $return;
	}
	/**
	 * Inject column content for a post
	 *
	 * @param  string $column  column id
	 * @param  int    $post_id post_id of flickr media
	 * @return string          HTML content
	 * @todo  consider making it easier to get Large Square and sizing it down
	 */
	public function manage_posts_custom_column( $column, $post_id ) {
		switch ( $column ) {
			case 'icon':
			// no need to get alt or title as there are other columns
			//echo get_image_tag( $post_id, '', '', 'center', 'Square');
			list( $img_src, $width, $height ) = image_downsize( $post_id, 'Large Square' );
			printf('<img src="%s" alt="" width="75" height="75" />', $img_src );
			break;
		}
	}
	// EDIT (post.php)
	/**
	 * Injects custom post-specific stuff for post.php
	 * 
	 * Triggers on display of post's edit screen (post.php)
	 *
	 * - handle user clicking on refresh button
	 * - enqueue script that modifies edit form functionality
	 * - enqueue css that modifies edit form look and functionality
	 * - inject content in place of rich editor
	 * - add action for handling alt text metabox
	 * - queue in handler for adding and removing metaboxes
	 * 
	 * @return void
	 */
	public function loading_post() {
		$screen = get_current_screen();
		// ONLY OPERATE ON FLICKR MEDIA
		if ( $screen->post_type != FML::POST_TYPE ) { return; }

		// handle refresh
		add_action( 'admin_action_refreshpost', array( $this, 'handle_refresh_post' ) );

		// EDITING EXISTING POST…
		// Enqueue Settings-specific Javascript (controls screenoptions)
		wp_enqueue_script(
			FML::SLUG.'-hack-post', //handle
			$this->_fml->static_url.'/js/admin-post.js', //src
			array('jquery'), //dependencies ajax
			FML::VERSION, //version
			true //in footer?
		);
		wp_enqueue_style(
			FML::SLUG.'-hack-post',
			$this->_fml->static_url.'/css/admin-post.css'//,
			// deps
			// ver
			// media
		);
		add_action( 'edit_form_after_title', array( $this, 'edit_insert_post_content' ), 10, 1 );

		add_action( 'add_meta_boxes', array( $this, 'adding_edit_meta_boxes' ) );
		// register alt_text meta box handler
		add_action( 'save_post', array( $this, 'handle_alt_meta_box_form' ) );
	}
	/**
	 * Handle form request to refresh flickr media custom post from flickr
	 * @return void
	 */
	public function handle_refresh_post() {
		if ( empty( $_POST['post_ID'] ) ) { return; }
		$post_id = (int) $_POST['post_ID'];

		// verify nonce
		check_admin_referer( 'update-post_' . $post_id );
		// call API and update post from flickr data
		FML::update_flickr_post( $post_id, true );
		// update status: Don't get all fancy with the different messages,
		// just say "updated"
		$location = add_query_arg( 'message', 1, get_edit_post_link( $post_id, 'url' ) );
		wp_redirect( apply_filters( 'redirect_post_location', $location, $post_id ) );
		exit; // for some reason the wp_redirect() doesn't actually exit. :-(
	}
	/**
	 * Show the_content for the post in a box that is normally for TinyMCE
	 *
	 * @param  WP_Post $post The post to display
	 * @return void
	 */
	public function edit_insert_post_content($post) {
		// use the side effect that we know $post is also the global
		$content = apply_filters('the_content', $post->post_content);
		$content = str_replace(']]>', ']]&gt;', $content);
		//var_dump( wp_get_attachment_metadata( $post->ID ) );
		include $this->_fml->template_dir.'/metabox.post_content.php';
	}
	/**
	 * Change the metaboxes for the FML edit post page
	 * 
	 * For some reason the slug metabox is on by default. Make sure it isn't.
	 * 
	 * @return void
	 */
	public function adding_edit_meta_boxes() {
		global $wp_meta_boxes;
		remove_meta_box( 'slugdiv', FML::POST_TYPE, 'normal' );
		// tweak post excerpt meta box as a "Caption" meta box
		remove_meta_box( 'postexcerpt', FML::POST_TYPE, 'normal' );
		add_meta_box(
			'postexcerpt-caption',                   // id attribute
			__( 'Caption' ),                         // title in edit screen
			array( $this, 'post_excerpt_meta_box' ), // callback
			FML::POST_TYPE,                          // screen
			'normal',                                // context (part of page)
			'core'                                   // priority (within context)
		);
		add_meta_box(
			'attachment-image-alt',
			__( 'Alt Text', FML::SLUG ),
			array( $this, 'alt_meta_box' ),
			FML::POST_TYPE,
			'normal',
			'core'
		);
		//var_dump($wp_meta_boxes);die;
	}
	/**
	 * Just like the normal post excerpt meta box, except they're called
	 * Captions and set the default caption text
	 * 
	 * @param  WP_Post $post post object
	 * @return void
	 */
	public function post_excerpt_meta_box( $post ) {
		include $this->_fml->template_dir.'/metabox.post_excerpt.php';
	}
	/**
	 * Handle saving of image alt text meta box
	 * @param  int   $post_id the post id of the flickr media
	 * @return void
	 */
	public function handle_alt_meta_box_form( $post_id ) {
		if ( isset( $_POST['image_alt_text'] ) ) {
			update_post_meta( $post_id, '_wp_attachment_image_alt', $_POST['image_alt_text'] );
		}
	}
	/**
	 * Create a meta box for saving alt text.
	 * 
	 * @param  WP_Post $post post object
	 * @return void
	 */
	public function alt_meta_box( $post ) {
		$alt_text = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
		include $this->_fml->template_dir.'/metabox.alt_text.php';
	}
	// ADD NEW (post-new.php -> upload.php?page=flickr-media-library-add-flickr)
	public function loading_add_media() {

	}
	public function show_add_media_page() {
		// set settings
		//include $this->_fml->template_dir.'/page.add_media.php'
		include $this->_fml->template_dir.'/iframe.flickr-upload-form.php';
	}
	/**
	 * Triggers on clicking "add new" for custom post -> redirect to real add new page
	 * 
	 * @return void
	 */
	public function loading_post_new() {
		$screen = get_current_screen();
		// ONLY OPERATE ON FLICKR MEDIA
		if ( $screen->post_type != FML::POST_TYPE ) { return; }
		wp_redirect( admin_url( 'upload.php?page=' . esc_attr( $this->ids['page_add_media'] ) ) );
		//wp_die('TODO: Need to add flickr importer');
		// Adding to current post
	}
	//
	// AJAX
	// 
	/**
	 * Process all client side ajax requests
	 *
	 * The following parameters are required in $_POST
	 * - action: $this->_ids[action_ajax] already used to trigger this
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
	public function handle_ajax() {
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
				$post = FML::get_media_by_flickr_id( $_POST['flickr_id'] );
				if ( $post ) {
					//FML::update_flickr_post($post, true);  // uncomment to repair broken posts
					$return = array(
						'status'    => 'ok',
						'flickr_id' => $_POST['flickr_id'],
						'post_id'   => $post->ID,
						'post'      => FML::wp_prepare_attachment_for_js($post),
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
				$post = FML::create_media_from_flickr_id($_POST['flickr_id']);
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
					$post = get_post($post->ID);
				}
				$return = array(
					'status'    => 'ok',
					'flickr_id' => $_POST['flickr_id'],
					'post_id'   => $post->ID,
					'post'      => FML::wp_prepare_attachment_for_js($post),
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
						$url = FML::get_flickr_link( $id ).'sizes/';
						break;
						case 'post':
						$url = get_permalink( $id );
						$rel = true;
						break;
						case 'custom':
						$url = FML::get_flickr_link( $id );
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
						$post->post_title, //insert title as it is NOT redundant (but will be smashed anyway)
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
	//
	// MEDIA UPLOAD IFRAME
	// 
	/**
	 * Filter to inject my media tab to the media uploads iframe
	 * @param  array $tabs the current tabs to show (defaults)
	 * @return array tabs + ours
	 * @todo  remove this and replace with the new backbonejs/underscoresjs model
	 */
	public function filter_media_upload_tabs( $tabs ) {
		$tabs[$this->_ids['tab_media_upload']] = __('Insert from Flickr', FML::SLUG);
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
		// TODO: Make this configurable
		// see wp_enqueue_media()
		$post_id = ( empty( $_GET['post_id'] ) ) ? 0 : (int) $_GET['post_id'];
		$constants = $this->_media_upload_constants( $post_id );
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
	 * Generate javascript constants for insertion into code.
	 * 
	 * @param  integer $post_id If the uploaded image should be attached to a
	 *                          post or page, this is the post id
	 * @return array            constants to be localized into a script
	 */
	private function _media_upload_constants( $post_id=0 ) {
		$settings = $this->_fml->settings;
		$props = array(
			'link'  => get_option( 'image_default_link_type' ), // db default is 'file'
			'align' => get_option( 'image_default_align' ), // empty default
			'size'  => ucfirst(get_option( 'image_default_size' )),  // empty default
			// capitalize to make it have a chance of matching flickr's sizes if set
		);
		$constants = array(
			'slug'               => FML::SLUG,
			'flickr_user_id'     => $settings[Flickr::USER_NSID],
			'ajax_url'           => admin_url( 'admin-ajax.php' ),
			'ajax_action_call'   => $this->_ids['ajax_action'],
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
			$post = get_post($post_id);
			$hier = $post && is_post_type_hierarchical( $post->post_type );
			$constants['post_id']                = $post_id;
			$constants['msgs_add_btn']['insert'] = ( $hier ) ? __( 'Insert into page' ) : __( 'Insert into post' );
		}
		return $constants;
	}
}