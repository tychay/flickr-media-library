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
	 * - page_options: the page: Settings > Flickr Media Library
	 * - forms: array consisting of action names for various option forms
	 * - ajax_action: action name for FML ajax API (all ajax apis are merged into
	 *                this one and "method" is used to disnguish between
	 *                different API calls into teh FML ajax API)
	 * - tab_media_upload: the tab id of the "Insert from Flickr" upload tab
	 * @var array
	 */
	private $_ids = array();
	/**
	 * The tabs that appear on the options page
	 *
	 * Display name (on tab) keyed by column id
	 * @var array
	 */
	private $_options_tabs = array();
	/**
	 * The help tabs (indexed by (page) tab and tabid)
	 */
	private $_options_help_tabs = array();
	/**
	 * All the hidden screen options columns on the Settings page.
	 *
	 * Display name (in screen options) keyed by column id.
	 * @var array
	 */
	private $_options_checkboxes = array();
	/**
	 * media alignments (and display names)
	 * @var array
	 */
	private $_aligns = array();
	/**
	 * media links (and display names)
	 * @var array
	 */
	private $_links = array();
	/**
	 * Plugin Admin function initialization 
	 * 
	 * @param \FML\FML $fml the FML plugin object
	 */
	public function __construct($fml) {
		$this->_fml                       = $fml;
		$this->_ids = array(
			'page_options'     => FML::SLUG.'-settings',
			'permalink_slug'   => FML::SLUG.'-base',
			'forms'            => array(
				'flickr_auth'    => FML::SLUG.'-flickr-auth',
				'flickr_deauth'  => FML::SLUG.'-flickr-deauth',
				'flickr_options' => FML::SLUG.'-flickr_options',
				'cpt_options'    => FML::SLUG.'-cpt_options',
				'output_options' => FML::SLUG.'-output_options',
			),
			'ajax_action'      => str_replace('-','_',FML::SLUG).'_api',
			'tab_media_upload' => str_replace('-','_',FML::SLUG).'_insert_flickr',
		);

		$this->_options_tabs = array(
			'flickr_options' => __( 'Flickr API', FML::SLUG ),
			'cpt_options'    => __( 'Custom Post', FML::SLUG ),
			'output_options' => __( 'Editing &amp; Output', FML::SLUG ),
		);
		$this->_options_help_tabs = array(
			'flickr_options' => array(
				FML::SLUG.'-help-flickrauth' => __('Flickr authorization', FML::SLUG),
			),
			'cpt_options'    => array(
				FML::SLUG.'-help-cptoptions' => __('Custom Post Type options', FML::SLUG),
			),
			'output_options'  => array(
			),
		);
		// I am removing the verb and the period to standardize on columns
		// habits instead of on the special otpions
		// (e.g. "Show full-height editor and distraction-free functionality.")
		$this->_options_checkboxes = array(
			'fml_show_apikey' => __( 'Flickr API Key and Secret', FML::SLUG ),
			'fml_show_rels'   => __( 'Link "rel" attributes', FML::SLUG ),
			'fml_show_classes'=> __( 'Image "class" attributes', FML::SLUG ),
			'fml_show_perf'   => __( 'Performance-related options', FML::SLUG ),
		);
		$this->_aligns = array(
			'left'   => __( 'Left' ),
			'center' => __( 'Center' ),
			'right'  => __( 'Right' ),
			'none'   => __( 'None' ),
		);
		$this->_links = array(
			'file'   => __( 'Media File' ),
			'post'   => __( 'Attachment Page' ),
			'flickr' => __( 'Flickr Photo Page', FML::SLUG ),
			'custom' => __( 'Custom URL' ),
			'none'   => __( 'None' ),
		);
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
		// PERMALINK: Register init() on admin_init (PERMALINK)
		add_action( 'admin_init', array( $this, 'init') );
		// Add various menu pages (e.g. Settings) to admin menu (OPTIONS, CUSTOMPOST)
		add_action( 'admin_menu', array( $this, 'create_admin_menus' ) );

		// PLUGIN: Add link to Settings page
		add_filter( 'plugin_action_links_'.$this->_fml->plugin_basename, array($this, 'filter_plugin_settings_links'), 10, 2 );

		// PERMALINK: Handle set permalink form
		add_action( 'load-options-permalink.php', array( $this, 'permalink_handle_form') );

		// AJAX: Add ajax servers
		add_action( 'wp_ajax_'.$this->_ids['ajax_action'], array($this, 'handle_ajax') );

		// CUSTOMPOST: Add init handlers for custom post pages
		add_action( 'load-edit.php', array( $this, 'loading_edit') );
		add_action( 'load-post.php', array( $this, 'loading_post') );
		add_action( 'load-post-new.php', array( $this, 'loading_post_new') );

		// ADDMEDIA: Add tab to Media upload button
		add_filter( 'media_upload_tabs', array( $this, 'filter_media_upload_tabs' ) );
		add_action( 'media_upload_'.$this->_ids['tab_media_upload'], array($this,'media_upload_get_iframe') );
		//add_action( 'wp_enqueue_media', array( $this, 'wp_enqueue_media') );
		//add_action( 'image_send_to_editor', array($this,'addmedia_send_to_editor') );

		// This is called in three places (post.php, post-new.php and ajax), so
		// let's do it here (POST, ADDMEDIA)
		add_action( 'admin_post_thumbnail_html', array( $this, 'filter_admin_post_thumbnail_html' ), 10, 2 );
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
	
		// PERMALINK: Render permalink form	
		add_settings_field(
			$this->_ids['permalink_slug'],
			__( 'Flickr Media base', FML::SLUG ),     // title
			array( $this, 'permalink_render_field' ), // form render callback
			'permalink',                              // page
			'optional',                               // section
			array(                                    // args
				'label_for' => $this->_ids['permalink_slug']
			)
		);
	}
	/**
	 * Add the settings and media menus to admin page (and load loaders)
	 *
	 * @return  void
	 */
	public function create_admin_menus() {
		// OPTIONS: Add menu to settings page
		$_options_suffix = add_options_page(
			__( 'Flickr Media Library Settings', FML::SLUG ),
			__( 'Flickr Media Library', FML::SLUG ),
			'manage_options',
			$this->_ids['page_options'],
			array( $this, 'options_show_page' )
		);
		if ( $_options_suffix ) {
			add_action( 'load-'.$_options_suffix, array( $this, 'options_loading' ) );
		}
	}
	//
	// PERMALINK PAGE: Settings > Permalink
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
	public function permalink_handle_form() {
		if ( !empty( $_POST[$this->_ids['permalink_slug']] ) ) {
			check_admin_referer('update-permalink');
			$this->_fml->settings_update( array(
				'permalink_slug' => $_POST[$this->_ids['permalink_slug']]
			) );
		}
		// pass through "updated" status
	}
	/**
	 * Settings API to render base HTML form in options-permalink.php
	 * @param  [type] $args [description]
	 * @return [type]       [description]
	 */
	public function permalink_render_field($args) {
		$slug = $this->_fml->settings['permalink_slug'];
		printf(
			'<input name="%1$s" id="%1$s" type="text" value="%2$s" class="regular-text code" />',
			$args['label_for'],
			esc_attr($slug)
		);
	}
	//
	// OPTIONS PAGE: Settings > Flickr Media Library 
	// 
	/**
	 * Loading the settings page:
	 *
	 * - Register this page as Flickr callback for Auth
	 * - Handle an oAuth callback to the options page
	 * - Handle all form actions in _ids['forms']
	 * - Add Settings contextual help tabs
	 * - Add Settings custom control to screen options
	 * - Enqueue Settings-specific Javascript (controls screenoptions)
	 *
	 * Here's the auth process:
	 * 
	 * 1) user clicks submit button and creates action $_ids[form][flickr_auth]
	 * 2) server authenticates and receives request token and secret from flickr
	 * 3) user gets redirected by flickr object to https://www.flickr.com/services/oauth/authorize
	 * 4) User clicks authorize
	 * 5) User gets redirected back to this page with  an oauth_verifier parameter
	 * 6) flickr plugin attempts to trade request token for access token
	 * 7) This is saved to plugin options for future requests
	 * 
	 * @return void
	 */
	public function options_loading() {
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
		foreach( $this->_ids['forms'] as $form=>$action ) {
			add_action( 'admin_action_'.$action, array( $this, 'options_handle_'.$form ) );
		}
		if ( !empty($_POST['action'] ) ) {
			do_action( 'admin_action_'.$_POST['action'] );
		}

		// Add Settings contextual help tabs
		$screen = get_current_screen();
		$screen->remove_help_tabs();
		$this->_options_add_help_tabs();
		$screen->set_help_sidebar( $this->_options_get_help_sidebar() );

		// Hidden column support
		add_filter( 'manage_'.$screen->id.'_columns', array( $this, 'options_hidden_columns' ) );
		// this doesn't work yet, but can't hurt
		add_filter( 'default_hidden_columns', array( $this, 'options_hidden_columns') );
		add_filter( 'get_user_option_manage'.$screen->id.'columnshidden', array( $this, 'options_get_hidden_columns' ) );

		// Enqueue Settings-specific Javascript (controls screenoptions)
		wp_enqueue_script(
			FML::SLUG.'-screen-settings', //handle
			$this->_fml->static_url.'/js/admin-settings.js', //src
			array( 'jquery' ), //dependencies ajax
			FML::VERSION, //version
			true //in footer?
		);
		
		// debugging: clear options to test default
		//delete_user_option( get_current_user_id(), 'manage'.$screen->id.'columnshidden', true );
	}
	/**
	 * Form request to (start) Flickr oAuth.
	 * 
	 * @return void
	 */
	public function options_handle_flickr_auth() {
		check_admin_referer( $this->_ids['forms']['flickr_auth'] . '-verify' );
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
			$this->_fml->settings_update($settings);
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
	public function options_handle_flickr_deauth() {
		check_admin_referer( $this->_ids['forms']['flickr_deauth'] . '-verify' );
		$this->_fml->clear_flickr_authentication();
	}
	/**
	 * Form request to save flickr API settings
	 * 
	 * @return void
	 */
	public function options_handle_flickr_options() {
		check_admin_referer( $this->_ids['forms']['flickr_options'] . '-verify' );
		$options = array();
		foreach( $_POST as $key=>$value ) {
			switch( $key ) {
				case 'flickr_search_safe_search':
					if ( $value == 'on' ) {
						$options[$key] = true;
					} elseif ( $value == 'off' ) {
						$options[$key] = false;
					}
					break;
			}
		}
		// flickr licenses are a special case
		if ( !empty($_POST['flickr_search_license-0']) ) {
			$license_array = array();
			for ( $i=0; $i<9; ++$i ) {
				if ( $_POST['flickr_search_license-'.$i] == 'on' ) {
					$license_array[] = $i;
				}
			}
			// There can never be a search for nothing, set to No known restrictions
			if ( empty($license_array) ) {
				$license_array[] = 7;
			}
			$options['flickr_search_license'] = implode( ',', $license_array );
		}
		$this->_options_update_settings($options);
	}
	/**
	 * Form request to save settings related to custom post type
	 *
	 * Settings supported:
	 * - post_date_map: link between the custom post post_date and flickr dates
	 * 
	 * @return void
	 */
	public function options_handle_cpt_options() {
		check_admin_referer( $this->_ids['forms']['cpt_options'] . '-verify' );
		$options = array();
		foreach( $_POST as $key=>$value ) {
			switch( $key ) {
				case 'post_date_map':
					$options[$key] = $value;
					break;
				case 'post_excerpt_default':
					$options[$key] = wp_unslash( $value );
					break;
			}
		}
		$this->_options_update_settings($options);
	}
	/**
	 * Form request to save settings related to output
	 * 
	 * @return void
	 */
	public function options_handle_output_options() {
		check_admin_referer( $this->_ids['forms']['output_options'] . '-verify' );
		//var_dump($_POST);die;
		$options = array();
		foreach( $_POST as $key=>$value ) {
			switch( $key ) {
				case 'media_default_align':
					if ( in_array( $value, array_keys( $this->_aligns ) ) ) {
						$options[$key] = $value;
					}
					break;
				case 'media_default_link':
					if ( in_array( $value, array_keys( $this->_links ) ) ) {
						$options[$key] = $value;
					}
					break;
				case 'shortcode_generate_custom_post':
				case 'shortcode_extract_flickr_id':
				case 'image_use_css_crop':
				case 'image_use_picturefill':
					if ( $value == 'on' ) {
						$options[$key] = true;
					} elseif ( $value == 'off' ) {
						$options[$key] = false;
					}
					break;
				case 'media_default_size':
				case 'media_default_rel_post':
				case 'media_default_rel_post_id':
				case 'media_default_rel_flickr':
				case 'media_default_class_size':
				case 'media_default_class_id':
				case 'shortcode_default_link':
				case 'shortcode_default_rel_post':
				case 'shortcode_default_rel_post_id':
				case 'shortcode_default_class_size':
				case 'shortcode_default_class_id':
				//case 'image_default_class_size':
					$options[$key] = $value;
					break;
			}
		}
		//var_dump($options);die;
		$this->_options_update_settings($options);
		//var_dump($this->_fml->settings);die;
	}
	/**
	 * Utility function to update fml::settings and then report it to UI
	 * 
	 * @param  array  $settings settings to change
	 * @return void
	 */
	private function _options_update_settings( $settings ) {
		if ( !empty($settings) ) {
			$this->_fml->settings_update($settings);
			add_settings_error('general', 'settings_updated', __('Settings saved.'), 'updated');
		}
	}
	/**
	 * Filter to inject the hidden columns into the screens columns array for
	 * tracking.
	 *
	 * (Also used to set default_hidden_columns when that feature is supported
	 * since all columns should be hidden by default.)
	 * 
	 * @param  array  $columns columns indexed by column_id with the value being
	 *                         it's display name in screen options
	 * @return array           the columns with the new ones injected
	 */
	public function options_hidden_columns( $columns ) {
		return array_merge( $columns, $this->_options_checkboxes );
	}
	/**
	 * Filter the get_options on hidden columns to inject defaults
	 * @param  mixed  $columns the return from get_user_option() for columns
	 * @return array
	 */
	public function options_get_hidden_columns( $columns ) {
		if ( $columns === false ) {
			// all hidden columns should be hidden by default
			return array_keys( $this->_options_checkboxes );
		}
		return $columns;
	}
	/**
	 * Show the Settings (Options) page which allows you to do Flickr oAuth.
	 */
	public function options_show_page() {
		// set some utility template parameters
		//$flickr = $this->_fml->flickr;
		$settings        = $this->_fml->settings;
		$this_page_url   = 'options-general.php?page=' . urlencode( $this->_ids['page_options'] );
		$tabs            = $this->_options_tabs;
		$active_tab      = $this->_options_active_tab();
		$hidden_cols     = $this->_options_checkboxes;
		$form_ids        = $this->_ids['forms'];
		$api_secret_attr = ( $settings['flickr_api_secret'] == FML::_FLICKR_SECRET )
		                 ? ''
		                 : $settings['flickr_api_secret'];
		$is_auth_with_flickr = $this->_fml->is_flickr_authenticated();
		$select_post_dates   = $this->_fml->post_dates_map;
		$select_links        = $this->_links;
		$select_aligns       = $this->_aligns;
		$select_sizes        = $this->_fml->flickr_sizes;
		$cb_licenses         = $this->_fml->flickr_licenses;
		// add "full" size
		$select_sizes['full'] = __('Full',FML::SLUG);

		include $this->_fml->template_dir.'/page.settings.php';
	}
	/**
	 * Add all the tabs associated with a given Settings tab.
	 *
	 * Note that there is a default tab that appears on all options page tabs
	 * After that, it adds all the other tabs.
	 * 
	 * @return void
	 */
	private function _options_add_help_tabs() {
		$screen = get_current_screen();

		// show default tab on all tabs
		$tab_id = FML::SLUG.'-help-default';
		$screen->add_help_tab( array(
			'id'	=> $tab_id,
			'title' => __( 'Default' ),
			'content' => $this->_options_get_help_tab_content( 'default', $tab_id ),
		) );

		// show help tabs for specific options tab (confusing, I know)
		$active_page_tab = $this->_options_active_tab();
		$current_help_page = $this->_options_help_tabs[$active_page_tab];
		foreach ( $current_help_page as $tab_id => $tab_title ) {
			$screen->add_help_tab( array(
				'id'	=> $tab_id,
				'title' => $tab_title,
				'content' => $this->_options_get_help_tab_content( $active_page_tab, $tab_id ),
			) );
		}
	}
	/**
	 * Return the desired help tab for the Settings page tab showing.
	 *
	 * @param  string $page_tab The current tab of the options page
	 * @param  string $help_tab The desired help tab to show.
	 * @return void
	 */
	private function _options_get_help_tab_content( $page_tab, $help_tab ) {
		// remove the FML::SLUG part from the name
		$tab_switch          = $page_tab.'-'.substr( $help_tab, strlen(FML::SLUG)+1 );
		$is_auth_with_flickr = $this->_fml->is_flickr_authenticated();
		$tabs                = $this->_options_tabs;
		$hidden_cols         = $this->_options_checkboxes;

		ob_start();
		include $this->_fml->template_dir.'/help.options-tabs.php';
		return ob_get_clean();
	}
	/**
	 * Return contents of the help sidebar in the plugin settings page
	 * @return string
	 */
	private function _options_get_help_sidebar() {
		ob_start();
		include $this->_fml->template_dir.'/help.settings-sidebar.php';
		return ob_get_clean();
	}
	/**
	 * Shortcut to easily find if column is hidden.
	 * 
	 * @param  string $column_name the id of the column to check status of
	 * @return bool                true if column is hidden
	 */
	public function options_column_is_hidden( $column_name ) {
		$screen = get_current_screen();
		return ( in_array( $column_name, get_hidden_columns( $screen ) ) );
	}
	/**
	 * Return current tab showing (if on options page)
	 * 
	 * @return string tab id
	 */
	private function _options_active_tab() {
		if ( isset( $_GET['tab'] ) ) {
			if (in_array($_GET['tab'], array_keys( $this->_options_tabs ) ) ) {
				return $_GET['tab'];
			}
		}
		// default tab
		return 'flickr_options';
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
			'cb'     => $cols['cb'],
			'icon'   => '', // core has already this to be 80px wide
			'title'  => $cols['title'],
			'tags'   => $cols['tags'],
			//'parent' => _x( 'Uploaded to', 'column name' ), // from class-wp-media-list-table.php
			'date'   => $cols['date'],
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
		$post = get_post( $post_id );
		if ( !$post ) { return; }
		switch ( $column ) {
			case 'icon':
				// no need to get alt or title as there are other columns
				//echo get_image_tag( $post_id, '', '', 'center', 'Square');
				list( $img_src, $width, $height ) = image_downsize( $post_id, 'Large Square' );
				printf('<img src="%s" alt="" width="75" height="75" />', $img_src );
				break;
			/* // disabling parents
			case 'parent':
				$parent = ( $post->post_parent > 0 ) ? get_post( $post->post_parent ) : false;
				$user_can_edit = $user_can_edit = current_user_can( 'edit_post', $post_id );
				if ( $parent ) {
					$title = _draft_or_post_title( $post->post_parent );
					$parent_type = get_post_type_object( $parent->post_type );
					if ( $parent_type && $parent_type->show_ui && current_user_can( 'edit_post', $post->post_parent ) ) {
						$post_string = '<strong><a href="%2$s">%1$s</a></strong>, %3$s<br />';
					} else {
						$post_string = '<strong>%1$s</strong>, %3$s<br />';
					}
					printf(
						$post_string,
						$title,
						get_edit_post_link( $post->post_parent ),
						get_the_time( __('Y/m/d'), $parent )
					);
					if ( $user_can_edit ) {
						$detach_url = add_query_arg( array(
							'post_type'      => $post->post_type, // right edit page
							'parent_post_id' => $post->post_parent,
							'post[]'         => $post->ID,
							'_wpnonce'       => wp_create_nonce( 'bulk-'.$parent_type->labels->name ),
						), 'edit.php' );
						printf(
							'<a class="hide-if-no-js detach-from-jjparent" href="%s">%s</a>',
							$detach_url,
							__( 'Detach' )
						);
					}
				} else {
					_e( '(Unattached)' );
					echo '<br />';
					if ( $user_can_edit ) {
						printf(
							'<a class="hide-if-no-js" onclick="findPosts.open( \'post[]\',\'%d\'); return false;" href="#the-list">%s</a>',
							$post->ID,
							__( 'Attach' )
						);
					}
				}
				break;
			/* */
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
		// register alt_text meta box handler (can be universal, but lets play safe)
		add_action( 'save_post_'.FML::POST_TYPE, array( $this, 'handle_alt_meta_box_form' ) );
		// register caption template metabox handler
		add_action( 'save_post_'.FML::POST_TYPE, array( $this, 'handle_caption_template_meta_box_form' ) );
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
		$use_template = true;
		$post = get_post();
		$caption = ( $use_template )
		         ? FML::caption_read($post)
		         : $post->post_excerpt;
		include $this->_fml->template_dir.'/metabox.post_excerpt.php';
	}
	/**
	 * Handle saving of caption template meta box
	 * @param  int   $post_id the post id of the flickr media
	 * @return void
	 */
	public function handle_caption_template_meta_box_form( $post_id ) {
		if ( isset( $_POST['post_excerpt_template'] ) ) {
			FML::caption_update( $post_id, wp_unslash( $_POST['post_excerpt_template'] ), true );
		}
		// TODO: clean out old post hiearchy tempoarily
		if ( 0 != $post_id) {
			global $wpdb;
			$wpdb->update( $wpdb->posts, array( 'post_parent' => 0 ), array( 'ID' => $post_id ) );
			clean_post_cache( $post_id );
		}
	}
	/**
	* put here so it doesn't trigger everywhere, slightly more efficient than the above
	* add_action( 'pre_post_update', array( $this, 'do_caption_update' ), 10, 2 );
	* add_filter( 'wp_insert_post_data', )
	 *  @deprecated unfinished and wrong hook (andin wrong object)
	 */
	public function do_caption_update( $post_id, $data ) {
		$post = get_post( $post_id );
		if ( $post->post_type !== FML::POST_TYPE ) { return; }
		if ( isset( $_POST['post_excerpt_template'] ) ) {
			$data['post_excerpt'] = $this->parse_template( $post, $this->_fml->caption_get_template);
		}
		//return $
		//var_dump( $post_id, $data ); die;
	}
	/**
	 * Handle saving of image alt text meta box
	 * @param  int   $post_id the post id of the flickr media
	 * @return void
	 */
	public function handle_alt_meta_box_form( $post_id ) {
		if ( isset( $_POST['image_alt_text'] ) ) {
			FML::set_image_alt( $post_id, wp_unslash( $_POST['image_alt_text'] ) );
		}
	}
	/**
	 * Create a meta box for saving alt text.
	 * 
	 * @param  WP_Post $post post object
	 * @return void
	 */
	public function alt_meta_box( $post ) {
		$alt_text = FML::get_image_alt( $post );
		include $this->_fml->template_dir.'/metabox.alt_text.php';
	}
	// ADD NEW (post-new.php -> media-upload.php?chromeless=1&tab=flickr_media_library_insert_flickr&for=admin_menu)
	/**
	 * Triggers on clicking "add new" for custom post -> redirect to real add new page
	 * 
	 * @return void
	 */
	public function loading_post_new() {
		$screen = get_current_screen();
		// ONLY OPERATE ON FLICKR MEDIA
		if ( $screen->post_type != FML::POST_TYPE ) { return; }
		//http://terrychay.dev/wp-admin/media-upload.php?chromeless=1&tab=flickr_media_library_insert_flickr&for=admin_menu
		wp_redirect( sprintf(
			'media-upload.php?chromeless=1&tab=%s&for=admin_menu',
			esc_attr( $this->_ids['tab_media_upload'] )
		));
		//wp_redirect( admin_url( 'upload.php?page=' . esc_attr( $this->_ids['page_add_media'] ) ) );
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
				// This nonce is created in the form.flickr-upload.php template
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
				// This nonce is created in the form.flickr-upload.php template
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
				// This nonce is created in the form.flickr-upload.php template
				$this->_verify_ajax_nonce( FML::SLUG.'-flickr-search-verify', '_ajax_nonce' );
				$this->_require_ajax_post( 'flickr_id' );
				$post_id = FML::create_media_from_flickr_id($_POST['flickr_id']);
				if ( !$post_id ) {
					$this->_send_json_fail( -102, sprintf(
						__('Failed to create Media from flickr_id=%s', FML::SLUG ),
						$_POST['flickr_id']
					) );
				}
				$update = array();
				if ( !empty( $_POST['caption'] ) ) {
					$this->_fml->caption_update( $post_id, wp_unslash( $_POST['caption'] ) );
				}
				if ( !empty( $_POST['alt']) ) {
					FML::set_image_alt( $post_id, wp_unslash( $_POST['alt'] ) );
				}
				// data may have been been changed.
				$post = get_post($post_id);
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
				// WordPress core (in wp-init.php/wp_magic_quotes) adds magic_quotes to GPC. WTF? Right?!!
				$attachment = wp_unslash( $_POST['attachment'] );
				$settings = $this->_fml->settings;
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
						__('Incorrect post type %s=%s', FML::SLUG ),
						'attachment[id]',
						$id
					) );
				}
				// If this attachment is unattached, attach it. Primarily a back compat thing.
				if ( current_user_can( 'edit_post', $id ) ) {
					// just in case…
					if ( empty( $_POST['post_id'] ) ) { $_POST['post_id'] = 0; }
					/* // disabling post hierarchy due to URL breakages
					if ( 0 == $post->post_parent && $insert_into_post_id = intval( $_POST['post_id'] ) ) {
						wp_update_post( array( 'ID' => $id, 'post_parent' => $insert_into_post_id ) );
					}
					*/
				}
				$url = '';
				$rel = false;
				if ( !empty( $attachment['link'] ) ) {
					switch ( $attachment['link'] ) {
						case 'file':
						$url = get_attached_file( $id );
						//Flickr community guidelines: link the download page
						//$url = FML::get_flickr_link( $id ).'sizes/';
						//$rel = 'file';
						break;
						case 'post':
						$url = get_permalink( $id );
						$rel = 'post';
						break;
						case 'flickr':
						$url = FML::get_flickr_link( $id );
						$rel = $settings['media_default_rel_flickr'];
						break;
						case 'custom':
						$url = $attachment['linkUrl'];
						break;
						default:
						$url = '';
						// no link
					}
				}
				//https://developer.wordpress.org/reference/functions/get_image_send_to_editor/
				remove_filter( 'media_send_to_editor', 'image_media_send_to_editor' );
				$html = '';
				if ( wp_attachment_is_image( $id ) ) {
					$html = $this->get_image_send_to_editor(
						$id,
						( isset( $attachment['post_excerpt'] ) ) ? $attachment['post_excerpt'] : '', //caption
						$post->post_title, //insert title as it is NOT redundant (but would normally be smashed anyway)
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
			case 'maybe_attach_media_to_post':
				$this->_verify_ajax_nonce( FML::SLUG.'-flickr-search-verify', '_ajax_nonce' );
				$this->_require_ajax_post( 'attachment', array('id',) );
				$this->_require_ajax_post( 'post_id' );
				$attachment = $_POST['attachment'];
				$id = intval( $attachment['id'] );
				if ( ! $post = get_post( $id ) ) {
					$this->_send_json_fail( -100, sprintf(
						__('Incorrect parameter %s=%s',FML::SLUG),
						'attachment[id]',
						$id
					) );
				}
				if ( $post->post_type != FML::POST_TYPE ) {
					$this->_send_json_fail( -101, sprintf(
						__('Incorrect post type %s=%s',FML::SLUG),
						'attachment[id]',
						$id
					) );
				}
				$attached = false;
				/* // This messes with URLs in an unintended manner
				if ( current_user_can( 'edit_post', $id ) ) {
					if ( 0 == $post->post_parent && $insert_into_post_id = intval( $_POST['post_id'] ) ) {
						wp_update_post( array( 'ID' => $id, 'post_parent' => $insert_into_post_id ) );
						$attached = true;
					}
				}
				*/
				$return = array(
					'status'        => 'ok',
					'attached'      => $attached,
					'post_id'       => $_POST['post_id'],
					'attachment_id' => $id,
				);
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
	 * Emulate get_image_send_to_editor() for flickr media
	 * 
	 * In it's infinite wisdom, none of the hooks inside the real function pass
	 * on the rel attribute.
	 *
	 * Besides, the original function doesn't know there is a difference between
	 * title and alt tags
	 * 
	 * @param  int     $id      [description]
	 * @param  string  $caption [description]
	 * @param  string  $title   [description]
	 * @param  string  $align   [description]
	 * @param  url     $url     [description]
	 * @param  string  $rel     if !false then it is the rel to add (post is special case)
	 * @param  string  $size    [description]
	 * @param  string  $alt     [description]
	 * @return [type]           [description]
	 */
	public function get_image_send_to_editor( $id, $caption, $title, $align, $url='', $rel=false, $size='Medium', $alt='' ) {
	    $html = get_image_tag($id, $alt, $title, $align, $size);
	    $settings = $this->_fml->settings;
	
		if ( $rel ) {
			if ( $rel == 'post' ) {
				$rels = array();
				if ( $settings['media_default_rel_post'] ) {
					$rels[] = $settings['media_default_rel_post'];
				}
				if ( $settings['media_default_rel_post_id'] ) {
					$rels[] = sprintf( $settings['media_default_rel_post_id'], $id );
				}
				$rel = implode(' ',$rels);
			}
			// also traps non-post cases
			if ( $rel ) {
				$rel = ' rel="'.esc_attr($rel).'"';
			}
		} else {
			$rel = '';
		}
	 
	    if ( $url )
	        $html = '<a href="' . esc_attr($url) . "\"$rel>$html</a>";
	 
	    /**
	     * Filter the image HTML markup to send to the editor.
	     *
	     * @since 2.5.0
	     *
	     * @param string $html    The image HTML markup to send.
	     * @param int    $id      The attachment id.
	     * @param string $caption The image caption.
	     * @param string $title   The image title.
	     * @param string $align   The image alignment.
	     * @param string $url     The image source URL.
	     * @param string $size    The image size.
	     * @param string $alt     The image alternative, or alt, text.
	     */
	    $html = apply_filters( 'image_send_to_editor', $html, $id, $caption, $title, $align, $url, $size, $alt );
	 
	    return $html;		
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
	// (BROKEN) BACKBONE MEDIA UPLOADER
	//
	/**
	 * Action to add enqueues to 
	 * @return [type] [description]
	 */
	function wp_enqueue_media() {
		/* // Going to need to know backbone.js and requirejs a hell of a lot better before this will work
		wp_enqueue_script(
			FML::SLUG.'-override-query-sync',
			$this->_fml->static_url.'/js/override-query-sync.js',
			array('media-models'), //dependencies
			false, // version
			true // in footer?
		);
		/* */
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
	 * By default returns the iframe that the upload form will be returned in.
	 *
	 * There is another mode. If for=post_thumbnail in the query string, it
	 * is asking for a thickbox to support flickr media injection into the
	 * post thumbnail.
	 * 
	 * @return string
	 * @todo  remove this and replace with the new backbonejs/underscoresjs model
	 */
	public function media_upload_get_iframe() {
		$post_id = ( empty( $_GET['post_id'] ) ) ? 0 : (int) $_GET['post_id'];
		if ( empty ($_GET['for'] ) ) {
			$_GET['for'] = 'media_button';
		}
		$this->_enqueue_media( $_GET['for'], $post_id );
		wp_enqueue_style(
			FML::SLUG.'-old-media',
			$this->_fml->static_url.'/css/emulate-media-thickbox.css',
			'media-views',
			FML::VERSION
			// media
		);
		// see wp_enqueue_media()
		return wp_iframe( array($this,'media_upload_show_iframe_content') );
	}
	/**
	 * render iframe content (in thickbox if needed) for flickr media 
	 * upload and insertion.
	 * 
	 * @return void
	 */
	public function media_upload_show_iframe_content() {
		$settings = $this->_fml->settings;
		$admin_img_dir_url = admin_url( 'images/' );
		$is_auth_with_flickr = $this->_fml->is_flickr_authenticated();

		include $this->_fml->template_dir.'/iframe.flickr-upload.php';
	}
	//
	// POST THUMBNAIL STUFF
	//
	/**
	 * Inject a link to the FML chooser thickbox into the post thumbnail HTML.
	 * 
	 * The way we support post thumbnails (currently) is by injecting a thickbox
	 * link to a FML chooser into the post_thumbnail metabox (and ajax
	 * generation of metabox).
	 *
	 * This hook, in its (non-infinite) wisdom doesn't realize that $post_id is
	 * theoretically useless without the thumbnail id as this hook could be
	 * called before the set_post_thumbnail(). Luckily, it currently isn't so
	 * instead of doing some regex parsing of $html, let's assume that the
	 * $thumbnail_id is going to be the get_post_thumbnail_id().
	 * 
	 * @param  string  $html    The metabox html
	 * @param  integer $post_id the post_id to inject it to
	 * @return string           The metabox html with (possible) link to FML importer
	 */
	function filter_admin_post_thumbnail_html( $html, $post_id ) {
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		// A remove link is a remove link is a remove link
		if ( $thumbnail_id ) { return $html; }
		$url = admin_url( sprintf(
			// media upload iframe: http://terrychay.dev/wp-admin/media-upload.php?chromeless=1&post_id=6209&tab=flickr_media_library_insert_flickr
			// set post thumbnail tb: http://terrychay.dev/wp-admin/media-upload.php?post_id=6209&amp;type=image&amp;TB_iframe=1
			'media-upload.php?chromeless=1&post_id=%d&tab=flickr_media_library_insert_flickr&for=post_thumbnail',
			$post_id
		) );
		$html .= sprintf(
			'<a class="thickbox" id="%s-set-post-thumbnail" href="%s" title="%s">%s</a>',
			FML::SLUG,
			esc_attr($url),
			esc_attr__( 'Set featured image from flickr', FML::SLUG ),
			esc_html__( 'Set featured image from flickr', FML::SLUG )
		);
		//<a class="thickbox" id="set-post-thumbnail" href="http://terrychay.dev/wp-admin/media-upload.php?post_id=6209&amp;type=image&amp;TB_iframe=1" title="Set featured image">Set featured image</a>
		return $html;
	}
	//
	// UTILITY FUNCTIONS
	//
	private function _enqueue_media( $page_type, $post_id=0 ) {
		wp_enqueue_style(
			'media-views',
			admin_url('css/media-views.css')
			// deps
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
		// for it to support retina, picturefill.js needs to be loaded
		if ( $this->_fml->use_picturefill ) {
			wp_enqueue_script( 'picturefill' );
		}
		// Need the WPSetAsThumbnail script in post thumbnail handling
		if ( $page_type == 'post_thumbnail' ) {
			wp_enqueue_script( 'set-post-thumbnail' );
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
		$constants = $this->_media_upload_constants( $page_type, $post_id );
		wp_localize_script(FML::SLUG.'-old-media-form-script', 'FMLConst', $constants);
		wp_enqueue_script(FML::SLUG.'-old-media-form-script' );
	}
	/**
	 * Generate javascript constants for insertion into code.
	 * 
	 * @param  integer $post_id If the uploaded image should be attached to a
	 *                          post or page, this is the post id
	 * @return array            constants to be localized into a script
	 * @todo  change default props
	 */
	private function _media_upload_constants( $page_type, $post_id=0 ) {
		$settings = $this->_fml->settings;
		$props = array(
			'link'    => $settings['media_default_link'],
			'align'   => $settings['media_default_align'],
			'size'    => $settings['media_default_size'],
			'caption' => $settings['post_excerpt_default'],
		);
		$constants = array(
			'slug'               => FML::SLUG,
			'page_type'          => $page_type,
			'flickr_user_id'     => $settings[Flickr::USER_NSID],
			'flickr_search'      => array(
				'safe_search' => $settings['flickr_search_safe_search'],
				'license'     => $settings['flickr_search_license'],
			),
			'ajax_url'           => admin_url( 'admin-ajax.php' ),
			'ajax_action_call'   => $this->_ids['ajax_action'],
			'edit_url_format'    => admin_url( 'post.php?post=%d&action=edit' ),
			'default_props'      => $props,
			'msgs_error'         => array(
				'ajax'       => __('AJAX error %s (%s).', FML::SLUG),
				'flickr'     => __('Flickr API error %s (%s).', FML::SLUG),
				'flickr_unk' => __('Flickr API returned an unknown error.', FML::SLUG),
				'fml'        => __('Flickr Media Library API error %s (%s).', FML::SLUG),
			),
			'msgs_pagination'    => array(
				'load'    => __('Load More'),
				'loading' => __('Loading…', FML::SLUG),
			),
			'msgs_add_btn'       => array(
				'add_to'  => __( 'Add to media library', FML::SLUG ),
				'adding'  => __( 'Adding…', FML::SLUG ),
				'query'   => __( 'Querying…', FML::SLUG ),
				'already' => __( 'Already added', FML::SLUG ),
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
		if ( !$this->_fml->is_flickr_authenticated() ) {
			$constants['flickr_api_key'] = $settings['flickr_api_key'];
		}
		if ( $post_id ) {
			$post = get_post($post_id);
			$hier = $post && is_post_type_hierarchical( $post->post_type );
			$constants['post_id']                = $post_id;
			$constants['msgs_add_btn']['insert'] = ( $hier ) ? __( 'Insert into page' ) : __( 'Insert into post' );
		}
		if ( $page_type == 'post_thumbnail' ) {
			$constants['msgs_add_btn']['insert'] = _( 'Set featured image' );
			$constants['nonce_set_thumbnail'] = wp_create_nonce( 'set_post_thumbnail-'.$post_id );
		}
		return $constants;
	}
}