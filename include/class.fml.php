<?php 
namespace FML;

/**
 * Flickr Media Library plugin code that needs to be available everywhere
 * 
 */
class FML implements FMLConstants
{
	//
	// "STATIC" PROPERTIES
	//
	/**
	 * @var string Cache the location of this directory (for pathing)
	 */
	public $plugin_dir;
	/**
	 * @var string Cache the location of plugin templates directory (different
	 * from the templates stuff below :-()
	 */
	public $template_dir;
	/**
	 * @var string Cache the url location of plugin statics directory
	 */
	public $static_url;
	/**
	 * Needed to inject into link in plugins directory.
	 * @var string Cache the plugin basename
	 */
	public $plugin_basename;
	/**
	 * List of possible post dates names (indexed by the setting name)
	 * 
	 * @var array
	 */
	public $post_dates_map;
	/**
	 * List of all Flickr sizes
	 */
	public $flickr_sizes = array();
	/**
	 * List of all Flickr Photo Licenses.
	 *
	 * Putting it here saves on an API call
	 * 
	 * @see https://help.yahoo.com/kb/flickr/safesearch-sln14917.html
	 * @var array
	 */
	public $flickr_licenses = array();
	/**
	 * @var array store the post_meta names of FML-specific metadata. This is
	 * accessible publicly as $post_metas (but not writeable).
	 */
	private $_post_metas = array();
	/**
	 * Set this temporarily to block picturefill sizes from being executed
	 * until later.
	 * 
	 * @var boolean
	 */
	private $_picturefill_delay_sizes_transient = false;
	/**
	 * FML Media Post of the template being processed
	 * @var WP_Post|false
	 */
	private $_template_post = false;
	private $_template_params = array();
	//
	// CONSTRUCTORS AND DESTRUCTORS
	//
	/**
	 * Plugin Initialization (called from get_instance())
	 *
	 * Note that currently this is not called until `plugins_loaded` has been
	 * fired, but in the future, it'd be created directly on the main page.
	 *
	 * This will set the static variables used by the plugin.
	 * 
	 * @param  string $pluginFile __FILE__ for the plugin file
	 */
	private function __construct($pluginFile) {
		$this->plugin_dir         = dirname($pluginFile);
		$this->template_dir       = $this->plugin_dir . '/templates';
		$this->static_url         = plugins_url('static',$pluginFile);
		$this->post_dates_map     = array(
			'posted'     => __( 'Date uploaded to flickr', self::SLUG ),
			'taken'      => __( 'Date photo taken', self::SLUG ),
			'lastupdate' => __( 'When last updated on flickr', self::SLUG ),
			'none'       => __( 'WordPress-only date', self::SLUG )
			);
		$this->plugin_basename    = plugin_basename($pluginFile);
		$this->_post_metas        = array(
			'flickr_id'        => '_flickr_id',
			'api_data'         => '_'.str_replace('-','_',self::SLUG).'_api_data',
			'caption_template' => '_post_excerpt_template',
		);
		$this->flickr_sizes = array(
			'Square'       => __('Square', self::SLUG),
			'Large Square' => __('Large Square', self::SLUG),
			'Thumbnail'    => __('Thumbnail', self::SLUG),
			'Small'        => __('Small', self::SLUG),
			'Small 320'    => __('Small 320', self::SLUG),
			'Medium'       => __('Medium', self::SLUG),
			'Medium 640'   => __('Medium 640', self::SLUG),
			'Medium 800'   => __('Medium 800', self::SLUG),
			'Large'        => __('Large', self::SLUG),
			'Large 1600'   => __('Large 1600', self::SLUG),
			'Large 2048'   => __('Large 2048', self::SLUG),
			'Original'     => __('Original', self::SLUG),
		);
		$this->flickr_licenses = array(
			0 => array(
				'name' => __('All Rights Reserved',FML::SLUG),
				'url'  => '',
				'iframe' => false, //whether or not it's thickbox'ble
			),
			1 => array(
				'name' => __('Attribution-NonCommercial-ShareAlike License',FML::SLUG),
				'url' => 'http://creativecommons.org/licenses/by-nc-sa/2.0/',
				'iframe' => true,
			),
			2 => array(
				'name' => __('Attribution-NonCommercial License',FML::SLUG),
				'url' => 'http://creativecommons.org/licenses/by-nc/2.0/',
				'iframe' => true,
			),
			3 => array(
				'name' => __('Attribution-NonCommercial-NoDerivs License',FML::SLUG),
				'url' => 'http://creativecommons.org/licenses/by-nc-nd/2.0/',
				'iframe' => true,
			),
			4 => array(
				'name' => __('Attribution License',FML::SLUG),
				'url' => 'http://creativecommons.org/licenses/by/2.0/',
				'iframe' => true,
			),
			5 => array(
				'name' => __('Attribution-ShareAlike License',FML::SLUG),
				'url' => 'http://creativecommons.org/licenses/by-sa/2.0/',
				'iframe' => true,
			),
			6 => array(
				'name' => __('Attribution-NoDerivs License',FML::SLUG),
				'url' => 'http://creativecommons.org/licenses/by-nd/2.0/',
				'iframe' => true,
			),
			7 => array(
				'name' => __('No known copyright restrictions',FML::SLUG),
				'url' => 'http://flickr.com/commons/usage/',
				'iframe' => false,
			),
			8 => array(
				'name' => __('United States Government Work',FML::SLUG),
				'url' => 'http://www.usa.gov/copyright.shtml',
				'iframe' => false,
			),
		);
		// $settings and $flickr are lazy loaded
	}
	/**
	 * Returns instance.
	 *
	 * Used for static method calls. The first time it is called (in bootstrap)
	 * it should be provided the $plugin_file so it can properly initialize
	 * itself.
	 * 
	 * @param  string $plugin_file pass to constructor (on creation)
	 * @return [type]              [description]
	 */
	static function get_instance($plugin_file='') {
		static $_self;
		if ( $_self )	{
			return $_self;
		}
		$_self = new FML($plugin_file);
		return $_self;
	}
	/**
	 * Stuff to run on `plugins_loaded`
	 *
	 * - register `init` handler
	 * - add shortcode handler
	 * - register filter_image_downsize to add fmlmedia image_downsize() support
	 * - register filter_get_attached_file to make get_attached_file() return
	 *   proper url for flickr media
	 * - register filter_wp_get_attachment_metadata to inject metadata results
	 *   without having to clutter up post_meta with more stuff
	 * - register filter media_send_to_editor to wrap shortcode around fmlmedia
	 * - register get_image_tag_class to override classes in send_to_editor images
	 * - maybe register emulation of prepend_attachment on the_content (regular or full)
	 * - maybe register flickr embed/oEmbed hijacking
	 * - maybe register caption injection of post thumbnail html
	 *
	 * @return void
	 */
	public function run() {
		$settings = $this->settings;

		add_action( 'init', array( $this, 'init' ) );

		// run [fmlmedia] shortcode before wpautop (and other shortcodes)
		add_filter( 'the_content', array( $this, 'run_shortcode' ), 8);
		// placeholder for strip_shortcodes() to work
		//add_shortcode( self::SHORTCODE, array( $this, 'shortcode') );
		add_shortcode( self::SHORTCODE, '__return_false' );
		add_shortcode( self::TEMPLATE_SHORTCODE, array( $this, 'template_shortcode') );

		add_filter( 'image_downsize', array( $this, 'filter_image_downsize'), 10, 3 );
		add_filter( 'get_attached_file', array( get_class($this), 'filter_get_attached_file'), 10, 2 );
		add_filter( 'wp_get_attachment_metadata', array( $this, 'filter_wp_get_attachment_metadata'), 10, 2 );
		// TODO make this optional depending on the style of handling shortcode injection
		add_filter( 'media_send_to_editor', array( $this, 'filter_media_send_to_editor'), 10, 3 );
		add_filter( 'get_image_tag_class', array($this,'filter_image_tag_class'), 10, 4 );

		if ( $settings['shortcode_support_embed'] ) {
			wp_embed_register_handler( self::SLUG.'-embed', self::REGEX_FLICKR_PHOTO_URL, array($this,'shortcode_handle_embed'), 3 );
		}
		if ( $settings['post_thumbnail_caption'] ) {
			add_filter( 'post_thumbnail_html', array($this,'post_thumbnail_add_caption'), 10, 3 );
		}
	}
	/**
	 * Stuff to run on `init`
	 * 
	 * - Register the custom post type used for storing flickr photos. If the
	 *   register is called earlier, it won't trigger due to missing object on
	 *   rewrite rule. {@see https://codex.wordpress.org/Function_Reference/register_post_type}
	 * 
	 * @return void
	 */
	public function init() {
		$this->register_post_type();

		$settings = $this->settings;

		// move the code later to give themes a chance to filter it after plugins_loaded
		
		if ( apply_filters( 'fml_attachment_prepend', $settings['attachment_prepend'] ) ) {
			add_filter( 'the_content', array( $this, 'attachment_prepend_media') ); //can run anytime
			if ( apply_filters( 'fml_attachment_prepend_remove', $settings['attachment_prepend_remove'] ) ) {
				add_filter( 'template_include', array( $this, 'attachment_maybe_remove_prepend') );
			}
		}

		if ( $this->use_picturefill ) {
			// Add Picturefill.WP 2 emulation specific code here.
			// yes, you're "supposed" to call this in wp_enqueue_script but
			// that's b.s. for two reaons:
			// 1) My code should override picturefill.js.wp because it's version
			//    2 (needed), it's up-to-date, and has better support
			// 2) picturefill.js can be registered anywhere to allow you to use
			//    it on admin or login pages without having to add 3 actions
			$this->register_picturefill_script();
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'attachment_inject_srcset_srcs' ), 10, 3 );
		}

		if ( apply_filters( 'fml_image_use_css_crop', $settings['image_use_css_crop'] ) ) {
			wp_register_script(
				'csscrop',
				$this->static_url.'/js/csscrop.js',
				array('jquery'),
				self::VERSION,
				true
			);
			// we need larger image dimensions for this s--t to work (of course
			// picturefill() will put in larger dims, but what if picturefill
			// isn't running? We want the larger image for that case)
			add_filter( 'fml_image_downsize_can_crop', '__return_true' );
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'attachment_inject_css_crop'), 10,  3 );
		}
		// Debugging
		//add_filter( 'fml_shortcode', array( get_class($this), 'debug_filter_show_variables' ), 10, 3 );
		//add_filter( 'fml_shortcode_image_attributes', array( get_class($this), 'debug_filter_show_variables' ), 10, 3 );
		//add_filter( 'fml_shortcode_link_attributes', array( get_class($this), 'debug_filter_show_variables' ), 10, 3 );
		//add_filter( 'image_downsize', array( get_class($this), 'debug_filter_show_variables' ), 9, 3 );
		//add_filter( 'image_downsize', array( get_class($this), 'debug_filter_show_variables' ), 11, 3 );
	}
	/**
	 * Actually register the flickr media custom post type
	 *
	 * This is separate from init because it can be called from the activation hook
	 * @return void
	 */
	public function register_post_type() {
		// https://codex.wordpress.org/Function_Reference/register_post_type
		register_post_type( self::POST_TYPE, array(
			'labels'              => array(
				// name of post type in plural and singualr form
				'name'               => _x( 'Flickr Media', 'plural', self::SLUG ),
				'singular_name'      => _x( 'Flickr Media', 'singular', self::SLUG ),
				//'menu_name'          => __( 'menu_name', self::SLUG ), //name that appears in custom menu and listing (there is none so not needed)
				//'name_admin_bar'     => __( 'name_admin_bar', self::SLUG ), //as post appears in the + New part of the admin bar
				'add_new'            => __( 'Import New', self::SLUG ), // name for "add new" menu and button using lang space, so no need to use context
				'add_new_item'       => __( 'Add New Flickr Media', self::SLUG ),
				'edit_item'          => __( 'Edit Flickr Media', self::SLUG ),
				//'new_items'          => __( 'new_items', self::SLUG ),
				'search_items'       => __( 'Search Flickr Media', self::SLUG ),
				'not_found'          => __( 'No Flickr media found', self::SLUG ),
				'not_found_in_trash' => __( 'No Flickr media found in trash', self::SLUG ),
				//'parent_item_colon'  => __( 'parent_item_colon', self::SLUG ),
			),
			'description'         => __( 'A mirror of media on Flickr', self::SLUG ),
			'public'              => true, // shortcut for show_in_nav_menus, show_ui, exclude_from_search, publicly_queryable
			'exclude_from_search' => false, // show on front end search results site/?s=search-term
			'publicly_queryable'  => true,  // can show on front end
			'show_ui'             => true,  // TODO: display admin panel interface for this post type
			'show_in_nav_menus'   => true,  // TODO: allow to show in navigation menus
			'show_in_menu'        => 'upload.php',  // TODO: shows in admin menu inside Media menu
			
			'show_in_admin_bar'   => false, //whether + New post type is available in admin bar
			//'menu_position'       => null,  // where to show menu in main menu
			//'menu_icon'           => '', // url to icon for menu or anme of icon in iconfont
			//'capability_type'     => 'post', //base capability type, attachment/mediapage is not allowed :-(
			//'capabilities'        => array(), //TODO
			//'map_meta_cap'        => null,
			'hierarchical'        => true, //post type can have parent, support'page-attributes'
			'supports'            => array(
				'title',
				//'editor',          // not editable
				//'author',          // change author
				//'thumbnail',       // assign featured image
				'excerpt',           // caption text
				'trackbacks',      
				//'custom-fields',
				//'comments',        // TODO
				//'revisions',       // TODO
				//'page-attributes', // assign parent if hierarchical is true, sets menu order
						// TODO: The above doesn't work because it assumes linked to same post type
				//'post-formats',    // TODO
			),
			//'register_meta_box_cb'=> //TODO: callback function when setting up metaboxes
			'taxonomies'          => array( 'post_tag' ), // support post tags taxonom
			'has_archive'         => true, // can have archive template
			//'permalink_epmask'    => // endpoint bitmask?
			'rewrite'             => array(
				'slug' => $this->settings['permalink_slug'],
				'with_front' => false, //make it a root level, just like categories & tags
				//'feeds' //defaults to 'has_archive' value
				//'pages' //allow pagination?
				////ep_mask
			),
			//'query_var'           => '', default query var
			//'can_export'          => true, // can be exported
		) );
	}
	//
	// OVERLOAD PROPERTIES
	// 
	/**
	 * Simplify access to properties of this plugin
	 *
	 * The following properties exist:
	 * 
	 * - settings(array): The options array for the plugin
	 * - flickr (\FML\Flickr): flickr api object
	 * - flickr_callback: the callback URL when authenticating flickr requests
	 * 
	 * @param  string $name the property to get
	 * @return mixed the thing to be gotten
	 */
	public function __get($name) {
		switch( $name ) {
			case 'settings':
				if ( empty( $this->_settings ) ) {
					$this->_settings_load();
				}
				return $this->_settings;
			case 'use_picturefill':
				return apply_filters( 'fml_image_support_picturefill', $this->settings['image_use_picturefill'] ); 
			case 'picturefill_sizes':
				return $this->_picturefill_sizes;
			case 'picturefill_size_map':
				return $this->_picturefill_size_map;
			case 'flickr':
				return $this->_get_flickr();
			case 'flickr_callback':
				return $this->_flickr_callback;
			case 'post_metas':
				return $this->_post_metas;
			default:
				trigger_error(sprintf('Property %s does not exist',$name));
				return null;
		}
	}
	/**
	 * __setter() override
	 *
	 * @param string $name the property to set
	 * @param mixed $value the value to set it to.
	 */
	public function __set($name, $value) {
		switch( $name ) {
			case 'settings':
				trigger_error( 'Set plugin settings through settings_update()' );
				break;
			case 'picturefill_sizes':
			case 'picturefill_size_map':
				trigger_error( sprintf( 'Set %s through fml_register_sizes() or FML::register_sizes()', $name ) );
			case 'flickr':
				trigger_error( sprintf( 'Not allowed to externally set flickr API.', $name ) );
				break;
			case 'flickr_callback':
				$this->_flickr_callback = $value;
				break;
			default:
				trigger_error( sprintf( 'Property %s is not settable', $name ) );
			break;
		}
	}
	/**
	 * The indexes are the following:
	 *
	 * - permalink_slug: the slug used to prepend URLs to flickr media
	 * - flickr_api_key: the api key to use
	 * - flickr_api_secret: the api secret for the key
	 * - Flickr::*: various flickr-specific variables like user name, access token, etc.
	 * - flickr_search_*: restrict flickr API search (done on the client)
	 * - post-date_map: what the custom post date maps onto (only: posted, taken, lastupdate, none)
	 * - media_default_*: defaults related to "add media" button in editor
	 * - shortcode_default*: defaults related to the [fmlmedia] shortcode
	 * - shortcode_*: features related to the [fmlmedia] shortcode
	 * - image_*: features related to flickr media image rendering (attachment emulation)
	 * 
	 * @var array Plugin blog options
	 */
	private $_settings = array();
	// PROPERTIES: Settings
	/**
	 * Load options array into settings variable
	 * 
	 * (and assign anything missing so it upgrades transparently).
	 *
	 * The options array is stored in the blog options using the SLUG as the
	 * key
	 */
	private function _settings_load() {
		$settings_changed = false;
		$settings = get_option( self::SLUG, array() );
		// setting an attribute into false may mean that the actual default will
		// be set later
		$_default_settings = $this->_settings_defaults();

		// upgrade missing parameters (or initialize defaults if none)
		// cannot do a straight array_merge because we're looking for if we
		// need to update the blog options.
		foreach ( $_default_settings as $option=>$value ) {
			if ( !array_key_exists( $option, $settings ) ) {
				// values that may involve calls to dB are put here to resolve
				// defaults only on a new/upgrade of setting
				if ( $value === false ) {
					$value = $this->_settings_default_special( $option );
				}
				$settings[$option] = $value;
			}
			$settings_changed = true;
		}
		$this->_settings = $settings;
		if ( $settings_changed ) {
			$this->settings_update();
		}
	}
	public function settings_default( $option ) {
		$defaults = $this->_settings_defaults( false );
		$default = $defaults[$option];
		if ( $default === false ) {
			$default = $this->_settings_default_special( $option );
		}
		return $default;
	}
	/**
	 * Get the settings array default
	 * 
	 * @param  boolean $translated By default false values may need to be
	 *         interpreted into actual values later. Set to true to false.
	 * @return array               Default settings values
	 */
	private function _settings_defaults( $translated=false ) {
		$_default_settings = array(
			'permalink_slug'                  => self::_DEFAULT_BASE,
			'flickr_api_key'                  => self::_FLICKR_API_KEY,
			'flickr_api_secret'               => self::_FLICKR_SECRET,
			Flickr::USER_FULL_NAME            => '',
			Flickr::USER_NAME                 => '',
			Flickr::USER_NSID                 => '',
			Flickr::OAUTH_ACCESS_TOKEN        => '',
			Flickr::OAUTH_ACCESS_TOKEN_SECRET => '',
			'flickr_search_safe_search'       => true,
			'flickr_search_license'           => '1,2,3,4,5,6,7,8', //everything but all rights reserved
			'post_date_map'                   => 'taken',
			'post_author_id_other'            => 0, //disabled
			'post_excerpt_default'            => '[fmldata template="attribution"]',
			'media_default_link'              => false,
			'media_default_align'             => false,
			'media_default_size'              => false,
			'media_default_rel_post'          => 'attachment',
			'media_default_rel_post_id'       => 'wp-att-%d',
			'media_default_rel_flickr'        => 'flickr',
			'media_default_class_size'        => 'size-%s',
			'media_default_class_id'          => 'wp-image-%d',
			'media_shortcode_only'            => false,
			'shortcode_default_link'          => 'flickr',
			'shortcode_default_size'          => '',
			'shortcode_default_keephw'        => true,
			'shortcode_default_rel_post'      => 'attachment',
			'shortcode_default_rel_post_id'   => 'wp-att-',
			'shortcode_default_rel_flickr'    => 'flickr',
			'shortcode_default_class_size'    => 'attachment-%s',
			'shortcode_default_class_id'      => '',
			'shortcode_extract_flickr_id'     => true,
			'shortcode_generate_custom_post'  => true,
			'shortcode_support_embed'         => true,
			'attachment_prepend'              => true,
			'attachment_prepend_remove'       => false,
			'attachment_prepend_size'         => 'Medium',
			'attachment_prepend_link'         => 'flickr',
			'post_thumbnail_caption'          => false,
			'post_thumbnail_post_only'        => true,
			'post_thumbnail_caption_template' => 'attribution',
			'image_use_css_crop'              => true,
			'image_use_picturefill'           => false,
			'image_prefer_http'               => false,
			'template_default'                => '',
			'templates'                       => false,
		);
		if ( $translated ) {
			$iterate_settings = array(
				'media_default_link',
				'media_default_align',
				'media_default_size',
				'image_use_picturefill',
				'templates',
			);
			foreach ( $iterate_settings as $setting_name ) {
				$_default_settings[ $setting_name ] = $this->_settings_default_special( $setting_name );
			}
		}
		return $_default_settings;
	}
	/**
	 * Do the extra processing necessary to set the default values.
	 * 
	 * This only returns the right value if the default value is normally false
	 * (no checking is done).
	 * @param  [type] $option [description]
	 * @return [type]         [description]
	 */
	private function _settings_default_special( $option ) {
		switch ( $option) {
			case 'media_default_link':
				$value = get_option( 'image_default_link_type' ); // db default 'file'
				return ( $value ) ? $value : 'flickr'; // comply with flickr TOS
			case 'media_default_align':
				$value  = get_option( 'image_default_align' ); //empty default
				return ( $value ) ? $value : 'none'; // comply with flickr TOS
			case 'media_default_size':
				$value = ucfirst( 'image_default_size' ); //empty default
				switch ( $value ) {
					case 'thumb':
					case 'thumbnail': // normally 150x150 square
					return 'Large Square';
					break;
					case 'medium': //normally 300 max
					return 'Small';
					break;
					case 'large': //normally 640 max
					return 'Medium 640';
					break;
					case 'full': //original
					return 'full';
					break;
					default:
					return 'Medium';
				}
				break;
			case 'image_use_picturefill':
				// basically, prefer false, but if picturefill.wp 2
				// is installed then default true
				return ( defined('PICTUREFILL_WP_VERSION') && '2' === substr(PICTUREFILL_WP_VERSION, 0, 1) );
			case 'templates':
				return apply_filters( 'fml_templates_default', array(
					'yahoo_weather' => '<a href="{{attr:FLICKR_PHOTO_URL}}">© by {{html:FLICKR_OWNER_REALNAME}}{{?!FLICKR_OWNER_REALNAME}}{{html:FLICKR_OWNER_USERNAME}}{{/?FLICKR_OWNER_REALNAME}} on <span class="trademark flickr">flickr</span></a>',
					'attribution'   => '<a href="{{attr:FLICKR_PHOTO_URL}}">{{html:POST_TITLE,photo}}</a> by <a href="{{attr:FLICKR_OWNER_PEOPLE_URL}}">{{html:FLICKR_OWNER_REALNAME}}{{?!FLICKR_OWNER_REALNAME}}{{html:FLICKR_OWNER_USERNAME}}{{/?FLICKR_OWNER_REALNAME}}</a>',
					'terry_photo_format' => '<b>{{html:POST_TITLE,Untitled}}</b><br />
{{?COUNTRY}}{{?_venue}}{{_venue}}, {{/?_venue}}{{?REGION}}{{REGION}}, {{/?REGION}}{{COUNTRY}}<br />{{/?COUNTRY}}
<br />
{{?CAMERA_CLEAN}}<i>{{CAMERA_CLEAN}}{{?LENS}}, {{LENS}}{{/?LENS}}<br />
{{?EXPOSURE_CLEAN}}{{EXPOSURE_CLEAN}} @ {{/?EXPOSURE_CLEAN}}{{APERTURE_CLEAN}}{{?ISO}}, iso{{ISO}}{{/?ISO}}{{?FOCAL_LENGTH_CLEAN}}, {{FOCAL_LENGTH_CLEAN}}{{/?FOCAL_LENGTH_CLEAN}}{{?FOCAL_LENGTH_35}} ({{FOCAL_LENGTH_35}}){{/?FOCAL_LENGTH_35}}</i>{{/?CAMERA_CLEAN}}',
					'google_static_map' => '{{?LATITUDE_CLEAN}}<img src="http://maps.googleapis.com/maps/api/staticmap?zoom={{_geo_zoom,15}}&size={{_geo_width,400}}x{{_geo_height,240}}&maptype={{_geo_maptype,roadmap}}&markers=color:{{_gmap_color,blue}}%7Clabel:{{_gmap_label,P}}%7C{{LATITUDE_CLEAN}},{{LONGITUDE_CLEAN}}&sensor=false" width="{{_geo_width,400}}" height="{{_geo_height:240}}{{/?LATITUDE_CLEAN}}" />',
				) );
		}
		return false;
	}
	/**
	 * Change any settings and save to blog options
	 * 
	 * @param array $settings options to modify/save. If you just want to save
	 *        the existing options, just don't provide any settings here.
	 * @return void
	 */
	public function settings_update( $settings=array() ) {
		// Make sure we have settings loaded before we start "overwriting" them
		if ( empty($this->_settings) ) { $this->_settings_load(); }
		foreach ( $settings as $key=>$value ) {
			$this->_settings[$key] = $value;
		}
		// If you mess up the post_date_map, just unlink it
		if ( !empty( $settings['post_date_map'] ) ) {
			if ( !in_array( $settings['post_date_map'], array_keys($this->post_dates_map ) ) ) {
				$this->_settings['post_date_map'] = 'none';
			}
		}
		update_option(self::SLUG, $this->_settings);
	}
	/**
	 * Reset all settings to their default values.
	 *
	 * I only use this for testing currently, because it's so destructive.
	 * 
	 * @return void
	 */
	public function settings_reset() {
		$this->settings_update( $this->_settings_defaults( true ) );
	}
	// PROPERTIES: Picturefill
	/**
	 * List of sizes strings indexed by handle
	 * @var array
	 */
	private $_picturefill_sizes = array();
	/**
	 * Maps image size to a "sizes" handle
	 * @var array
	 */
	private $_picturefill_size_map = array();
	/**
	 * Emulate picturefill_wp_register_sizes()
	 *
	 * Register a sizes for use for flickr media. This will make calls into the
	 * emulated if it exists.
	 * 
	 * @param  string $handle       name of the class "sizes-$handle" to attach to
	 * @param  string $sizes_string the sizes attribute to write out
	 * @param  mixed  $attach_to    single image size (or list of images) to auto apply to
	 * @return void
	 */
	public function register_sizes( $handle, $sizes_string, $attach_to='') {
		$this->_picturefill_sizes[$handle] = $sizes_string;
		if ( empty( $attach_to ) ) { return; }
		if ( !is_array( $attach_to ) ) { $attach_to = array( $attach_to ); }
		foreach ( $attach_to as $size ) {
			$this->_picturefill_size_map[$size] = $handle;
		}
	}
	// PROPERTIES: Flickr
	/**
	 * @var \FML\Flickr Flickr API object
	 */
	private $_flickr;
	/**
	 * Get the flickr API singleton
	 * @todo handle authenticated version
	 */
	private function _get_flickr()
	{
		if ( !empty( $this->_flickr) ) {
			return $this->_flickr;
		}
		$settings = $this->settings; // trigger load of settings and store locally
		$this->_flickr = new Flickr(
			$settings['flickr_api_key'],
			$settings['flickr_api_secret'],
			$this->_flickr_callback
		);
		// are we authenticated with flickr? if so then set up 1auth param
		if ( $this->is_flickr_authenticated() ) {
		    $this->_flickr->useOAuthAccessCredentials(array(
		        Flickr::USER_FULL_NAME            => $settings[Flickr::USER_FULL_NAME],
		        Flickr::USER_NAME                 => $settings[Flickr::USER_NAME],
		        Flickr::USER_NSID                 => $settings[Flickr::USER_NSID],
		        Flickr::OAUTH_ACCESS_TOKEN        => $settings[Flickr::OAUTH_ACCESS_TOKEN],
		        Flickr::OAUTH_ACCESS_TOKEN_SECRET => $settings[Flickr::OAUTH_ACCESS_TOKEN_SECRET],
		    ));
		}
		return $this->_flickr;
	}
	/**
	 * @var string|null flickr callback url
	 */
	private $_flickr_callback = null;
	//
	// FLICKR API PUBLIC METHODS
	// 
	/**
	 * Return whether we are authenticated with flickr
	 * @return  bool  true or false
	 */
	function is_flickr_authenticated() {
		return ( !empty($this->settings[Flickr::OAUTH_ACCESS_TOKEN]) );
	}
	/**
	 * Reset the flickr object for a new use
	 */
	public function reset_flickr() {
		$this->_flickr = null;
	}
	/**
	 * Take flickr oAuth data and save it to settings/options
	 * @return null
	 */
	public function save_flickr_authentication()
	{
		$this->settings_update(array(
			Flickr::USER_FULL_NAME            => $this->_flickr->getOauthData(Flickr::USER_FULL_NAME),
			Flickr::USER_NAME                 => $this->_flickr->getOauthData(Flickr::USER_NAME),
			Flickr::USER_NSID                 => $this->_flickr->getOauthData(Flickr::USER_NSID),
			Flickr::OAUTH_ACCESS_TOKEN        => $this->_flickr->getOauthData(Flickr::OAUTH_ACCESS_TOKEN),
			Flickr::OAUTH_ACCESS_TOKEN_SECRET => $this->_flickr->getOauthData(Flickr::OAUTH_ACCESS_TOKEN_SECRET),
		));
	}
	/**
	 * Clear out flickr oAuth credentials
	 * @return null
	 */
	public function clear_flickr_authentication()
	{
		$this->flickr->signout();
		$this->settings_update(array(
			Flickr::USER_FULL_NAME            => '',
			Flickr::USER_NAME                 => '',
			Flickr::USER_NSID                 => '',
			Flickr::OAUTH_ACCESS_TOKEN        => '',
			Flickr::OAUTH_ACCESS_TOKEN_SECRET => '',
		));
	}
	//
	// FMLMEDIA SHORTCODE HANDLING
	//
	/**
	 * Modify HTML attachment to add shortcode for flickr media when inserting
	 * from editor
	 *
	 * This does a transform of the attachment attributes into something
	 * readable (and processable) by the [fmlmedia] shortcode handler.
	 * For instance, shortcode attribute names cannot have '-'.
	 * 
	 * @param  string $html       HTML to send to editor
	 * @param  int    $id         post id of attachment
	 * @param  array  $attachment array of attachment attributes
	 * @return string             Filtered HTML
	 * @see https://developer.wordpress.org/reference/hooks/media_send_to_editor/
	 */
	public function filter_media_send_to_editor($html, $id, $attachment) {
		$post = get_post ( $id );
		if ( $post->post_type != self::POST_TYPE ) {
			return $html;
		}
		$attr_string = '';
		foreach ( $attachment as $key=>$value ) {
			switch ($key) {
				// these are the same in both shortcode and form params
				case 'id':
				case 'align':
				break;
				case 'link': 
				// if it's a custom, we don't need to tell it
				if ( $value == 'custom' ) {
					$key = '';
				}
				break;
				case 'url': 
				// don't pass through url if the link type is something special
				if ( in_array( $attachment['link'], array('flickr','post','file') ) ) {
					$key = '';
				}
				// these need to be transformed a bit
				case 'image_alt': $key = 'alt'; break;
				case 'image-size': $key = 'size'; break;
				// the rest should be filtered as they're not supported in shortcode
				default: $key = ''; // continue does not work inside switches
			}
			if ( !$key ) { continue; }
			$attr_string .=  sprintf(
				' %s="%s"',
				$key,
				esc_attr( $value )
			);
		}
		if ( $this->settings['media_shortcode_only'] ) {
			$shortcode = sprintf( '[%1$s%2$s][/%1$s]', self::SHORTCODE, $attr_string );
			// prune out the <a><img> link from the html
 			if ( preg_match( '!(<a\s[^>]*>)?<img\s[^>]*>(</a>)?!im', $html, $matches ) ) {
 				$pos = strpos( $html, $matches[0] );
 				return substr( $html, 0, $pos) . $shortcode . substr( $html, $pos + strlen( $matches[0] ) );
 			}
 			// this should never happen, but let's play it safe
 			return $shortcode . $html;
		} else {
			return sprintf( '[%1$s%2$s]%3$s[/%1$s]', self::SHORTCODE, $attr_string, $html );
		}
	}
	public function filter_image_tag_class($class, $id, $align, $size) {
		$post = get_post($id);
		// only operate on fml media
		if ( !$post || ( $post->post_type != self::POST_TYPE ) ) { return $class; }
		$settings = $this->settings;
		$classes = array( 'align'.esc_attr($align) );
		if ( $settings['media_default_class_size'] ) {
			$classes[] = esc_attr( sprintf( $settings['media_default_class_size'], str_replace( ' ', '_', $size ) ) );
		}
		if ( $settings['media_default_class_id'] ) {
			$classes[] = esc_attr( sprintf( $settings['media_default_class_id'], $id ) );
		}
		return implode( ' ', $classes );
	}
	/**
	 * Trigger the process of the [fmlmedia] shortcode earlier.
	 *
	 * This works the same way as the [embed] shortcode.
	 * 
	 * 1. Remove existing shortcodes
	 * 2. Register the real [fmlmedia] shortcode
	 * 3. Run do_shortcode() on content
	 * 4. Restore existing shortcodes
	 */
	public function run_shortcode( $content ) {
		global $shortcode_tags;

		$orig_shortcode_tags = $shortcode_tags;
		remove_all_shortcodes();

		add_shortcode( self::SHORTCODE, array( $this, 'shortcode') );

		$content = do_shortcode( $content );

		// Put the original shortcodes back in
		$shortcode_tags = $orig_shortcode_tags;

		return $content;
	}
	/**
	 * Embed handler to replace flickr oEmbed and embeds
	 * @param  [type] $matches  [description]
	 * @param  [type] $atts     [description]
	 * @param  [type] $url      [description]
	 * @param  array  $raw_atts [description]
	 * @return [type]           [description]
	 */
	public function shortcode_handle_embed( $matches, $atts, $url, $raw_atts=array() ) {
		$size = array( $atts['width'], $atts['height'] );
		$attr = array(
			'size'      => $size,
			'flickr_id' => $matches[1],
		);
		$return = $this->shortcode( $attr, '', 'embed' );
		if ( $return ) {
			return apply_filters( 'fml_embed_flickr_photo_url', $return, $attr, $matches, $atts, $url, $raw_atts );
		} else {
			// fml didn't work (most likely, not allowing creation of flickr
			// media), try to do default (taking into account recursion)
			global $wp_embed;
			wp_embed_unregister_handler( self::SLUG.'-embed', 3 );
			$return = $wp_embed->shortcode( array(), $matches[0] );
			wp_embed_register_handler( self::SLUG.'-embed', self::REGEX_FLICKR_PHOTO_URL, array($this,'embed_handle_photo'), 3 );
			// not triggering filter because of fail
			return $return;
		}
	}
	/**
	 * Process [fmlmedia] shortcode for $content
	 *
	 * In order for this to work as expected, this shortcode handler is loaded
	 * using the pattern used by embed and syntaxhighlighter to run before
	 * `wpautop()`, so this can be nested inside a caption shortcode (for
	 * instance).
	 *
	 * This will allow you to preseerve unsupported attributes in image and
	 * the hyperlink. It does this by overwriting the content attributes with
	 * the generated output (while appending to img.class). The assumption is
	 * the first valid tag is either the img or the a>img. It does this through
	 * regex to avoid having to use an expensive DOM parser (and transients).
	 * Class attributes are treated specially (appended instead of  replaced).
	 * 
	 * Modify caption is not support because we don't want to be injecting
	 * content willy nilly at runtime. Note that if you want to deal with the
	 * fact that caption width attributes are not responsive (which they are
	 * not), rip that stuff out in caption using the `img_caption_shortcode`
	 * filter.
	 *
	 * Remember if doing a bare [fmlmedia]…[/fmlmedia], this will still override
	 * the link href and the size with the defaults for the shortcode. For
	 * instance, you may have linked a large, but we're substituting
	 * substituting a medium. If you want to do override the defaults, take the
	 * time to provide the image_size and link tags.
	 *
	 * This method:
	 *
	 * 1. Process shortcode attributes (and get attachment, tag attribtues, and extract)
	 * 2. Generate html output (and extract attributes)
	 * 3. If there is no (extracted) content, return html output (prepended)
	 * 4. If there is content, generate new html by merging remembering to treat
	 *    class specially. Then inject into content
	 * 
	 * @see  FML\FML::run_shortcode() Process handler to run shortcode earlier
	 * @param  array  $atts    shortcode attributes {@see FML\FML::_shortcode_attrs()}
	 * @param  string $content content shortcode wraps
	 * @param  string $context Most of the time it's FML::SHORTCODE, but if
	 *                         called directly, it can be something different.
	 * @return string          HTML output corrected to embed FML asset correctly
	 * @todo   inject plugin defaults for attributes
	 * @todo  add setting for extraacting content to flickrid
	 * @todo  add option for auto-adding missing media
	 */
	public function shortcode( $raw_atts, $content='', $context='' ) {
		// 1. Process shortcode attributes (and get attachment)
		list ( $atts, $post, $a, $img, $needle ) = $this->_shortcode_attrs( $raw_atts, $content );
		if ( !$post || ( $post->post_type != self::POST_TYPE ) ) {
			$post_id = ( $post ) ? $post->ID : 0;
			return $this->_shortcode_return( '', $content, '', $post_id, $atts );
		}
		if ( $context ) { $atts['_context'] = $context; }
		//var_dump( array( $atts, $raw_atts ) );

		$id    = $post->ID;
		$alt   = $atts['alt'];
		$title = $atts['title'];
		$align = $atts['align'];
		$size  = ( is_string($atts['size']) ) ? str_replace( ' ', '_', $atts['size'] ) : $atts['size'];
		$rel   = $atts['rel'];

		$settings = $this->settings;

		// 2. Generate HTML output from shortcode (and extract attributes)
		
		//     This is basically the corrected get_image_send_to_editor()
		//     without any caption content (which would trigger caption handling)
		//     Remember the real get_image_send_to_editor and its hooks are
		//     not available since they are part of admin code.
		/* // DO NOT RUN IT THIS WAY: Reason: this does not trigger wp_get_attachment_image_attributes which is needed for things like post thumbnails, etc.
		$html = get_image_tag($id, $alt, $title, $align, $size);
		*/
		//    Emulate the output of get_image_tag() in wp_get_attachment_image()
		// TODO: Add support for configuring which of these classes get written
		// by default (and how). For instance, size-Large instead of attachment-Large
		// or map sizes to internal strings.
		$iatts = array();
		$classes = array();
		// Default class id is empty in the case of emulating this function
		if ( $settings['shortcode_default_class_id'] ) {
			$classes[] = esc_attr( sprintf( $settings['shortcode_default_class_id'], $id ) );
		}
		// The default class size (fancy emulation of the one in
		// wp_get_attachment_image where the string is attachment-%s )
		if ( $settings['shortcode_default_class_size'] ) {
			$size_string = ( is_array( $size ) )
			             ? implode( 'x', $size )
			             : str_replace( ' ', '_', $size );
			$classes[] = esc_attr( sprintf( $settings['shortcode_default_class_size'], $size_string ) );
		}
		if ( $atts['sizes'] ) {
			$classes[] = 'sizes-' . $atts['sizes'];
		}
		// This seems weird but is correct (WordPress is wrong). Title should be
		// the title of the image, and alt should be a description provided for
		// accessibility (screen readers). You can/should have both.
		if ( $alt )   { $iatts['alt']   = $alt; }
		if ( $title ) { $iatts['title'] = $title; }
		//if ( $align ) { $classes[] = 'align'.$align; }
		// always override the class class, but there is emulation above
		$iatts['class'] = implode( ' ', $classes );

		$this->_picturefill_delay_sizes_transient = true;
		$html = wp_get_attachment_image( $id, $size, false, $iatts);
		$this->_picturefill_delay_sizes_transient = false;
		$img_gen = self::extract_html_attributes( $html );

		if ( $atts['url'] ) {
			$a_gen = array(
				'element'    => 'a',
				'attributes' => array( 'href' => $atts['url'] ),
				'content'    => $html, // this isn't always valid, but no worries as it will be overwritten 
			);
			if ( $rel ) { $a_gen['attributes']['rel'] = $rel; }
			/*
			$html = sprintf(
				'<a href="%s"%s>%s</a>',
				esc_attr( $atts['url'] ),
				( $rel ) ? ' rel="'.esc_attr($rel).'"' : '',
				$html
			);
			/* */
		} else {
			$a_gen = false;
		}

		// 3. If there is no content (extracted), return html output
		//if ( !$content ) { return apply_filters( 'fml_shortcode', $html, $post->ID, $atts ); }
		if ( !$a && !$img ) {
			// make sure to trigger all the filters
			$img_gen['attributes'] = $this->_image_inject_sizes( $img_gen['attributes'], $post, $size, $atts );
			$img = apply_filters( 'fml_shortcode_image_attributes', $img_gen, $post->ID, $atts );
			$html = self::build_html_attributes( $img );
			if ( $a_gen )  {
				$a_gen['content'] = $html;
				$a = apply_filters( 'fml_shortcode_link_attributes', $a_gen, $post->ID, $atts );
				$html = self::build_html_attributes( $a );
			}
			return $this->_shortcode_return( $html, $content, $needle, $post->ID, $atts );
		}
		// this handles the empty content case too

		// 4. If there is content, merge unique things from that into our 
		//    processed output.
		//    a. no need to run regex on output because we've already extracted
		//       $a_gen and $img_gen when we generated it
		//    b. no need to run regex processing on content (or save the match)
		//       because we've done that above
		//    c. Strip out generated image_hwstring if the target image has them
		//       and we requested keephw
		if ( $atts['keephw'] ) {
			if ( !empty( $img['attributes']['height']) ) {
				unset( $img_gen['attributes']['height'] );
			}
			if ( !empty( $img['attributes']['width']) ) {
				unset( $img_gen['attributes']['width'] );
			}
		}
		//    d. iterate through img tag of content injecting the generated attrs
		//       (there must always be an $img or we wouldn't have reached this
		//       point in the code)
		foreach ( $img_gen['attributes'] as $key=>$value ) {
			// special case, class should be merged, not overwritten
			if ( $key == 'class' && !empty( $img['attributes']['class'] ) ) {
				// TODO think about how to filter align* attributes??
				$img['attributes'][$key] = self::_merge_attribute( $img['attributes'][$key], $value );
				continue;
			}
			// overwrite
			$img['attributes'][$key] = $value;
		}
		$img['attributes'] = $this->_image_inject_sizes( $img['attributes'], $post, $size, $atts );
		$img = apply_filters( 'fml_shortcode_image_attributes', $img, $post->ID, $atts );
		$replace = self::build_html_attributes( $img );

		//    e. iterate through a tag (if so) injecting that stuff inside
		$do_link = true;
		if ( $a && $a_gen ) {
			// nothing special, just merge
			foreach ( $a_gen['attributes'] as $key=>$value ) {
				if ( $key == 'rel' && !empty( $a['attributes']['rel'] ) ) {
					$a['attributes'][$key] = self::_merge_attribute( $a['attributes'][$key], $value );
					continue;
				}
				// overwrite
				$a['attributes'][$key] = $value;
			}
			//$a['attributes'] = array_merge( $a['attributes'], $a_gen['attributes'] );
			// and then insert img content above
		} elseif ( $a ) {
			// just the a tag in content
		} elseif ( $a_gen ) {
			// just the a tag in generated injection
			$a = $a_gen;
		} else {
			// $replace has been set properly if just img tag
			$do_link = false;
		}
		if ( $do_link) {
			$a['content'] = $replace;
			$a = apply_filters( 'fml_shortcode_link_attributes', $a, $post->ID, $atts );
			$replace = self::build_html_attributes( $a );
		}
		
		//    f. restore and return
		return $this->_shortcode_return( $replace, $content, $needle, $post->ID, $atts );
	}
	/**
	 * Format shortcode return
	 *
	 * Basically inserts the shortcode changes back in and triggers the filter
	 * correctly.
	 * 
	 * @param  string $replace generated content
	 * @param  string $content enclosed content
	 * @param  string $needle  string in enclosed content to match and replace
	 * @param  int    $post_id post_id of flickr media used
	 * @param  array  $atts    the processed attributes
	 * @return string          HTML return for shortcode
	 */
	private function _shortcode_return( $replace, $content, $needle, $post_id, $atts ) {
		if ( $needle ) {
			$start = strpos( $content, $needle );
			$end   = $start + strlen( $needle );
			$html = substr( $content, 0, $start ) . $replace . substr( $content, $end );
		} else {
			$html = $replace . $content;
		}
		return apply_filters( 'fml_shortcode', $html, $post_id, $atts );
	}
	/**
	 * Do attribute processing.
	 *
	 * These are the attributes:
	 * 
	 *   - id: The post ID of the flickr Media
	 *   - flickr_id: if ID is not provided, this is the flickr ID of the image
	 *   - alt: The alt tag to use in the image
	 *   - title: image title
	 *   - size: the size of the image to use, if not provided, it tries to
	 *           extract the size.
	 *   - align: alignment (not really used except in class names)
	 *   - link: the link type (or 'custom')
	 *   - url: the url to link (if link is custom)
	 *   - keephw: do not overwrite existing height and width attributes if
	 *             content has provided them
	 *
	 * 1. Extract img and a tags + attributes from $content. Must be <img />
	 *    or <a><img /></a>.
	 * 2. Get default attributes
	 * 3. Modify defaults based on extracted content
	 * 4. Process attributes against defaults
	 * 5. Try to grab a flickr_id if there is no id or flickr_id given
	 * 6. Verify post is FML media
	 *    If no id, might have to do post creation from flickr
	 * 7. Process other attributes
	 *    - format flags
	 *    - set missing size
	 *    - inject title
	 *    - inject alt
	 *    - set url and rel based on link
	 * @param  array  $raw_atts attributes from the shortcode processor
	 * @param  string $content  the content of the script
	 * @return array an array consisting of the the following:
	 * - $attr: the processed attributes
	 * - $post: the post (or false)
	 * - $a: the $a attributes (or false)
	 * - $img: the img attributes (or false)
	 * - $needle: the string to be replaced in $content
	 */
	private function _shortcode_attrs( $raw_atts, $content ) {
		global $wp_version;
		$settings = $this->settings;

		// 1. Extract img and a tags + attributes from $content. Must be
		//    <a><img /></a> or <img />
		$a      = false;
		$img    = false;
		$needle = false;
		if ( preg_match( '!(<a\s[^>]*>)?<img\s[^>]*>(</a>)?!im', $content, $matches ) ) {
			//save for later for to find point of re-insertion
			$needle = $matches[0];
			$extract = self::extract_html_attributes( $needle );
			if ( $extract['element'] == 'a' ) {
				$img = self::extract_html_attributes ( $extract['content'] );
				if ( $img['element'] != 'img' ) {
					// bare a tag in content? Prepend injection
					return apply_filters( 'fml_shortcode', $html.$content, $post->ID, $atts );
				}
				$a = $extract;
			} elseif ( $extract['element'] == 'img' ) {
				$a = false;
				$img = $extract;
			} else {
				$a = false;
				$img = false;
			}
		} else {
			$a = false;
			$img = false;
		}

		// 2. Get Default Attributes
		$default_atts = array(
			'id'        => 0,
			'flickr_id' => 0,
			'alt'       => '',
			'title'     => '',
			'size'      => $settings['shortcode_default_size'],
			                         // LEAVE BLANK: transformed from image-size in editor
			'sizes'     => '',       // LEAVE BLANK: allows injection of sizes (picturefill)
			'align'     => '',       // LEAVE BLANK: editor default may be none, but ours is no attribute/class
			'link'      => $settings['shortcode_default_link'],
			                         // Set to 'flickr' because of TOS
			'url'       => '',
			'keephw'    => $settings['shortcode_default_keephw'],
			//'rel'                  // internally applied based on link and url
			//'_context'             // internally applied based on how called
			//'post_excerpt' => '',  // caption not used in a or img
		);
		/**
		 * Filter shortcode attributes for shortcode processing in prepend_media()
		 *
		 * @since 1.0
		 * @see FML\FML::_shortcode_attrs()
		 * @param array $attrs shortcode attribute defaults
		 */
		$default_atts = apply_filters('fml_shortcode_default_attrs', $default_atts );

		// 3. Modify defaults based on extracted content
		if ( $a ) {
			if ( !empty( $a['attributes']['href']) ) {
				$default_atts['link']  = 'custom';
				$default_atts['url']   = $a['attributes']['href'];
			}
		}
		if ( $img ) {
			if ( !empty( $img['attributes']['title']) ) {
				$default_atts['title'] = $img['attributes']['title'];
			}
			if ( !empty( $img['attributes']['alt']) ) {
				$default_atts['alt']   = $img['attributes']['alt'];
			}
			if ( !empty( $img['attributes']['class']) ) {
				// can be mis-typed as "false" but shouldn't be an issue
				$default_atts['size']  = self::extract_flickr_sizes( $img['attributes']['class'], true );
			}
			if ( !$default_atts['size'] && !empty( $img['attributes']['width']) && !empty( $img['attributes']['height']) ) {
				$default_atts['size'] = array(
					'width'   =>  $img['attributes']['width'],
					'height'  =>  $img['attributes']['height'],
					'crop'    =>  false,
				);
			}
		}
		 
		// 4. Process Attributes against defaults
		// Technically we support WordPress 3.5 so we need to trap this (untested)
		if ( version_compare( $wp_version, '3.6', '<' ) ) {
			$atts = shortcode_atts( $default_atts, $raw_atts );
			$atts = apply_filters( 'shortcode_atts_fmlmedia', $atts, $default_atts, $raw_atts );
		} else {
			$atts = shortcode_atts( $default_atts, $raw_atts, self::SHORTCODE );
		}

		// 5. Try to grab a flickr_id if there is no id or flickr_id given
		if ( $atts['id'] == 0
		  && $atts['flickr_id'] == 0
		  && $settings['shortcode_extract_flickr_id']
		  ) {
			if ( $a && !empty( $a['attributes']['href'] ) ) {
				$atts['flickr_id'] = self::extract_flickr_id( $a['attributes']['href'] );
			} else {
				// look for iframe (flickr's default "share")
 				if ( preg_match( '!(<iframe\s[^>]*>).*?</iframe>?!im', $content, $matches ) ) {
 					$iframe = self::extract_html_attributes( $matches[1] );
 					if ( $iframe
 						&& !empty( $iframe['attributes']['src'] )
 						&& ( $atts['flickr_id'] = self::extract_flickr_id( $iframe['attributes']['src'] ) )
 				) {
						//save for later for to find point of re-insertion
						$needle = $matches[0];
						// also grab width/height attributes
	 					if ( !$atts['size'] && !empty( $iframe['attributes']['width']) && !empty( $iframe['attributes']['height']) ) {
							$atts['size'] = array(
								'width'   =>  $iframe['attributes']['width'],
								'height'  =>  $iframe['attributes']['height'],
								'crop'    =>  false,
							);
						}
					}
 				} else {
 					// not an iframe, look for url in content
					$atts['flickr_id'] = self::extract_flickr_id( $content );
					// if found a URL smash the entire content in case it is
					// a bare URL or an [embed]url[/embed]
					if ( $atts['flickr_id'] ) {
						$needle = $content;
					}
	 			}
			}
		}

		// 6. Verify post is FML media first.
		//    To do this, we must have either the id or flickr_id set, prefering
		//    id, optionally this can extract or generate flickr
		if ( $atts['id'] == 0 && $atts['flickr_id'] == 0 ) {
			return array( $atts, false, $a, $img, $needle );
		}
		if ( $atts['id'] == 0 ) {
			$post = self::get_media_by_flickr_id( $atts['flickr_id'] );
			if ( !$post && $settings['shortcode_generate_custom_post'] ) {
				// generate FML media automatically
				$post_id = self::create_media_from_flickr_id( $atts['flickr_id'] );
				$post = get_post( $post_id );
				// Could extract size from img src but we already check width
				// and height and class so let's just use the default at this point
			}
		} else {
			$post = get_post( $atts['id'] );
		}
		if ( !$post ) {
			return array( $atts, $post, $a, $img, $needle );
		}

		// 7. Process other attributes
		//    Format flags
		$atts['keephw'] = self::shortcode_bool( $atts['keephw'] );
		//    Set missing size
		if ( !$atts['size'] ) {
			$atts['size'] = apply_filters( 'fml_shortcode_attr_size', 'Medium' );
		}
		//    Inject title if missing/not provided
		if ( !$atts['title'] ) {
			$atts['title'] = trim( $post->post_title );
		}
		//    Inject alt if missing/not provided
		if ( !$atts['alt'] ) {
			$atts['alt'] = trim( self::get_image_alt( $post ) );
		}
		//    Find url to link if any
		$atts['rel'] = '';
		if ( $atts['link'] ) {
			switch ( $atts['link'] ) {
				case 'file':
				//$url = get_attached_file( $id );
				//Flickr community guidelines: link the download page
				$downsize = image_downsize( $post->ID, 'full' );
				$atts['url'] = $this->maybe_http( $downsize[0] );
				//$atts['rel'] = 'original';
				//$atts['url'] = self::get_flickr_link( $post ).'sizes/';
				//$atts['rel'] = 'flickr-download';
				break;
				case 'post':
				$atts['url'] = get_permalink( $post );
				$rels = array();
				if ( $settings['shortcode_default_rel_post'] ) {
					$rels[] = $settings['shortcode_default_rel_post'];
				}
				if ( $settings['shortcode_default_rel_post_id'] ) {
					$rels[] = sprintf( $settings['shortcode_default_rel_post_id'], $post->ID );
				}
				$atts['rel'] = implode(' ',$rels);
				break;
				case 'flickr':
				$atts['url'] = self::get_flickr_link( $post );
				$atts['rel'] = $settings['shortcode_default_rel_flickr'];
				break;
				case 'custom': //if 'custom' with no URL, ignore it
				$atts['link'] = '';
				/*
				if ( !$atts['url'] ) {
					$atts['url'] = self::get_flickr_link( $post );
					//$atts['link'] = 'flickr';
					$rel = 'flickr';
				}
				$atts['link'] = '';
				*/
				break;
				case 'none':
				$atts['url'] ='';
				case '': // preserve URL default
				break;
				default: // unknown
				$atts['link'] = '';
				//$atts['url']  = ''; //clear URL field just in case
			}
		}
		return array( $atts, $post, $a, $img, $needle );
	}
	/**
	 * Helper method to determine if a shortcode attribute is true or false.
	 * (Taken from gistpress)
	 *
	 * @param string|int|bool $var Attribute value.
	 * @return bool
	 */
	static public function shortcode_bool( $var ) {
		$falsey = array( 'false', '0', 'no', 'n' );
		return ( ! $var || in_array( strtolower( $var ), $falsey ) ) ? false : true;
	}
	/**
	 * Extract HTML attributes.
	 *
	 * @see  https://gist.github.com/tovic/b3b683f28d899e19f830
	 * @param  string $input HTML tag to extract from
	 * @return array         hash with the element (tag name), content, and
	 *                       attributes as key/value pairs
	 */
	static public function extract_html_attributes($input) {
	    if( ! preg_match('#^(<)([a-z0-9\-._:]+)((\s)+(.*?))?((>)([\s\S]*?)((<)\/\2(>))|(\s)*\/?(>))$#ims', $input, $matches)) return false;
	    $matches[5] = preg_replace('#(^|(\s)+)([a-z0-9\-]+)(=)(")(")#i', '$1$2$3$4$5<attr:value>$6', $matches[5]);
	    $results = array(
	        'element' => $matches[2],
	        'attributes' => null,
	        'content' => isset($matches[8]) && $matches[9] == '</' . $matches[2] . '>' ? $matches[8] : null
	    );
	    if(preg_match_all('#([a-z0-9\-]+)((=)(")(.*?)("))?(?:(\s)|$)#i', $matches[5], $attrs)) {
	        $results['attributes'] = array();
	        foreach($attrs[1] as $i => $attr) {
	            $results['attributes'][$attr] = isset($attrs[5][$i]) && ! empty($attrs[5][$i]) ? ($attrs[5][$i] != '<attr:value>' ? $attrs[5][$i] : "") : $attr;
	        }
	    }
	    return $results;
	}
	/**
	 * Reverse extract_html_attributes()
	 * 
	 * @param  array  $extract output (or equiv) from extract_html_attributes()
	 * @return string          the tag rebuilt
	 */
	static public function build_html_attributes( $extract ) {
		$return = '<'.$extract['element'];
		foreach ( $extract['attributes'] as $key=>$value ) {
			$return .= ' '.$key.'="'.$value.'"';
		}
		if ( $extract['content'] ) {
			return $return . '>' . $extract['content'] . '</' . $extract['element'] . '>';
		} else {
			return $return . ' />';
		}
	}
	private static function _merge_attribute ( $att1, $att2 ) {
		return implode( ' ', array_unique( array_merge(
			explode( ' ', $att1 ),
			explode( ' ', $att2 )
		) ) );
	}
	//
	// TEMPLATING
	//
	/**
	 * Handle template injection of flickr data
	 * 
	 * @param  [type] $raw_atts [description]
	 * @param  string $content  [description]
	 * @param  string $context  [description]
	 * @return mixed            actually returns (false) if there is no matching
	 *                          template (or data missing)
	 */
	public function template_shortcode( $raw_atts, $content='', $context='') {
		$settings = $this->settings;
		$default_atts = array(
			'id'        => 0,
			'flickr_id' => 0,
			'template'  => apply_filters( 'fml_template_shortcode_default', '', $raw_atts ),
		);
		$atts = shortcode_atts( $default_atts, $raw_atts, self::TEMPLATE_SHORTCODE );
		// troll for template parameters
		$params = array();
		foreach ( $raw_atts as $key=>$value ) {
			if ( substr($key,0,1) == '_' ) {
				$params[$key] = $value;
			}
		}
		// Try to deduce what post the template is for
		$post = false;
		if ( !$atts['id'] ) {
			if ( $atts['flickr_id'] ) {
				$post = self::get_media_by_flickr_id( $atts['flickr_id'] );
			}
			if ( !$post ) {
				$post = get_post();
			}
		} else {
			$post = get_post( $atts['id'] );
		}
		return apply_filters( 'fml_template_shortcode', $this->_template_process( $post, $atts['template'], $params, ( $content ) ? $content : false ), $atts, $content, $context );
	}
	private function _template_process( $post, $template_name='', $params=array(), $html=false ) {
		//$this->settings_reset(); var_dump($this->settings);
		if ( !is_object( $post ) ) {
			$post = get_post( $post );
		}
		if ( !$post || ( $post->post_type != self:: POST_TYPE ) ) { return false; }
		$this->_template_post = $post;
		$this->_template_params = $params;
		$html = $this->template_parse( $template_name, $html );
		return apply_filters( 'fml_template_process', $html, $post, $template_name );
	}
	public function template_parse( $template_name, $html=false ) {
		$settings = $this->settings;
		if ( $template_name && array_key_exists( $template_name, $settings['templates'] ) ) {
			$html = $settings['templates'][$template_name];
		}
		// If there is no template to process, send back "false"
		if ( $html === false ) { return false; }
		do {
			$html_old = $html;
			$html = preg_replace_callback('!\{\{\?(\!)?([a-zA-Z0-9_]+)\}\}(.+)\{\{\/\?\2\}\}!ms', array( $this, 'template_replace_condition' ), $html );
			$html = preg_replace_callback('!\{\{([a-z]*):?([a-zA-Z0-9_]+),?(\w*)\}\}!ms', array($this,'template_replace_variable'), $html );
		} while ( $html != $html_old );
		//var_dump($html, self::get_flickr_data($this->_template_post)); die;
		return $html;
	}
	public function template_replace_variable( $matches ) {
		$filter = $matches[1];
		$return = $this->_template_variable( $matches[2] );
		if ( ( $return !== false ) && $filter ) {
			switch ( $filter ) {
				case 'html':     return esc_html( $return );
				case 'attr':     return esc_attr( $return );
				case 'js':       return esc_js( $return );
				case 'sql':      return esc_sql( $return );
				case 'textarea': return esc_textarea( $return );
				case 'url':      return esc_url( $return );
				case 'var':      return str_replace( ' ', '_', $return );
				case 'int':      return intval( $return );
			}
		}
		if ( !$return  && $matches[3] ) {
			//$return = $this->_template_variable( $matches[3] );
			//if ( !$return ) {
				$return = $matches[3];
			//}
		}
		return $return;
	}
	public function template_replace_condition( $matches ) {
		$condition = ( $this->_template_variable( $matches[2] ) );
		if ( $matches[1] ) {
			$condition = !$condition;
		}
		if ( $condition ) {
			return $matches[3];
		} else {
			return '';
		}
	}
	private function _template_variable( $variable, $flickr_data = false, $metadata = false ) {
		if ( !$this->_template_post ) { return false; }
		$post = $this->_template_post;
		if ( !$flickr_data ) {
			$flickr_data = self::get_flickr_data( $post );
		}
		if ( !$metadata ) {
			$metadata = wp_get_attachment_metadata( $post->ID );
		}

		// check if it's a explicit call for parameter
		if ( array_key_exists( $variable, $this->_template_params ) ) {
			return $this->_template_params[$variable];
		}
		// allow parameters to override any variables/template, remember that
		// params must be lower case due to limitation of shortcode processor
		$maybe_param = '_'.strtolower($variable);
		if ( array_key_exists( $maybe_param, $this->_template_params ) ) {
			return $this->_template_params[$maybe_param];
		}
		//var_dump(array($metadata,$flickr_data));
		switch ( $variable ) {
			case 'POST_TITLE':
				return $post->post_title;
			case 'POST_CONTENT':
				return $post->post_content;
			case 'WIDTH':
			case 'HEIGHT':
			case 'FILE':
				$key = strtolower( $variable );
				return ( array_key_exists( $key, $metadata ) ) ? $metadata[$key] : false;
			case 'FLICKR_ID':
			case 'FLICKR_SECRET':
			case 'FLICKR_SERVER':
			case 'FLICKR_FARM':
			case 'FLICKR_DATEUPLOADED':
			case 'FLICKR_ISFAVORITE':
			case 'FLICKR_LICENSE':
			case 'FLICKR_SAFETY_LEVEL':
			case 'FLICKR_ROTATION':
			case 'FLICKR_ORIGINALSECRET':
			case 'FLICKR_ORIGINALFORMAT':
			case 'FLICKR_VIEWS':
				$key = strtolower( substr( $variable, 7 ) );
				return ( array_key_exists( $key, $flickr_data ) ) ? $flickr_data[$key] : false;
			case 'FLICKR_OWNER_NSID':
			case 'FLICKR_OWNER_USERNAME':
			case 'FLICKR_OWNER_REALNAME':
			case 'FLICKR_OWNER_LOCATION':
			case 'FLICKR_OWNER_ICONSERVER':
			case 'FLICKR_OWNER_ICONFARM':
			case 'FLICKR_OWNER_PATH_ALIAS':
				$key = strtolower( substr( $variable, 13 ) );
				return ( array_key_exists( $key, $flickr_data['owner'] ) ) ? $flickr_data['owner'][$key] : false;
			case 'FLICKR_OWNER_PEOPLE_URL':
				// TODO better formatting here, also good idea to check if
				// I need to use NSID (does flickr ever return empty/missing username?)
				return 'https://flickr.com/people/'.$flickr_data['owner']['path_alias'];
			case 'FLICKR_OWNER_PHOTOS_URL':
				return 'https://flickr.com/photos/'.$flickr_data['owner']['path_alias'];
				//      'owner' => 
			case 'FLICKR_PHOTO_URL':
			case 'FLICKR_PHOTOPAGE':
				return self::get_flickr_link( $post );
			// TODO: DATES
			// TODO: PERMISSIONS, VIEWS, EDITABILITY, PUBLIC EDITABILITY, USAGE, COMMENTS, NOTES, PEOPLE
		}
		// Check image_meta
		$maybe_imagemeta = strtolower( $variable );
		if ( array_key_exists( $maybe_imagemeta, $metadata['image_meta'] ) ) {
			if ( $maybe_imagemeta != '_flickr' ) {
				return $metadata['image_meta'][$maybe_imagemeta];
			}
		}

		// Maybe it's a template??
		return $this->template_parse( $variable ); //can be recursive :-(
	}
	//
	// FEATURED IMAGE (POST THUMBNAIL) SUPPORT
	//
	public function post_thumbnail_add_caption( $html, $post_id, $post_thumbnail_id ) {
		$settings = $this->settings;
		// Handle case where we want to display post thumbnail caption on the
		// post page only
		if ( $settings['post_thumbnail_post_only'] ) {
			// if it's not the post page don't display
			//if ( !is_post( $post_id ) ) { return $html; } // can't use is_page() inside loop
			if ( !is_single( $post_id ) ) { return $html; }
			// if the post thumbnail that is being displayed is not the post
			// thumbnail for this post (e.g. using post thumbnail for page
			// navigation), don't display
			if ( $post_thumbnail_id != get_post_thumbnail_id( $post_id ) ) {
				return $html;
			}
		}
		$caption_html = $this->_template_process( $post_thumbnail_id, $settings['post_thumbnail_caption_template'] );
		$class = apply_filters( 'fml_post_thumbnail_caption_class', 'fml-post-thumbnail-caption', $post_id, $post_thumbnail_id );
		if ( current_theme_supports( 'html5', 'caption' ) ) {
			$return = sprintf(
				'<figure class="%s">%s<figcaption class="wp-caption-text">%s</figccaption></figure>',
				esc_attr( $class ),
				$html,
				$caption_html
			);
		} else {
			$return = sprintf(
				'%s<div class="%s">%s</div>',
				$html,
				esc_attr( $class ),
				$caption_html
			);
		}
		return apply_filters( 'fml_post_thumbnail_caption', $return, $html, $caption_html, $post_id, $post_thumbnail_id, $class );
	}
	//
	// CAPTION TEMPLATE SUPPORT
	//
	/**
	 * Saves the default caption values
	 * 
	 * @param  mixed  $post 
	 * @return void
	 */
	static public function caption_create( $post ) {
		$self = self::get_instance();
		$self->caption_set_template( $post, $self->settings['post_excerpt_default'] );
	}
	static public function caption_read( $post ) {
		$self = self::get_instance();
		return $self->caption_get_template( $post );
	}
	/**
	 * [refresh_caption description]
	 * 
	 * @param  mixed $post_id          WP_Post or integer
	 * @param  mixed $caption_template if !false then save the captiont emplate
	 * @return void
	 */
	static public function caption_update( $post, $caption_template = false ) {
		if ( !is_object( $post ) ) {
			$post = get_post( $post );
		}
		if ( !$post || ( $post->post_type != self:: POST_TYPE ) ) { return false; }

		$self = self::get_instance();

		if ( $caption_template === false ) {
			$caption_template = $self->caption_get_template( $post );
		}
		$self->caption_set_template( $post, $caption_template );
	}
	/**
	 * Remember, getting the caption is just post_excerpt
	 * @param  mixed  $post_id WP_Post or post ID
	 * @return string          the caption template HTML
	 */
	public function caption_get_template( $post ) {
		if ( !is_object( $post ) ) {
			$post = get_post( $post );
		}
		if ( !$post || ( $post->post_type != self:: POST_TYPE ) ) { return false; }
		
		return get_post_meta( $post->ID, $this->_post_metas['caption_template'], true );
	}
	public function caption_set_template( $post, $caption_template ) {
		if ( !is_object( $post ) ) {
			$post = get_post( $post );
		}
		if ( !$post || ( $post->post_type != self:: POST_TYPE ) ) { return; }
		
		update_post_meta( $post->ID, $this->_post_metas['caption_template'], $caption_template );
		// also update post_excerpt
		$caption = $this->parse_template( $post, $caption_template );
		/* // cannot do because of recursion
		$update = array(
			'ID'           => $post->ID,
			'post_excerpt' => $caption,
		);
		wp_update_post( $update );
		*/
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_excerpt' => $caption ), array( 'ID' => $post->ID ) );
		clean_post_cache( $post->ID );
	}
	public function parse_template( $post, $template ) {
		// set context
		if ( $post ) {
			if ( !is_object( $post ) ) {
				$post = get_post( $post );
			}

			if ( isset( $GLOBALS['post'] ) ) {
				$_global_post = $GLOBALS['post'];
				$GLOBALS['post'] = $post;
				$caption = do_shortcode( $template );
				$GLOBALS['post'] = $_global_post;
			} else {
				$GLOBALS['post'] = $post;
				$caption = do_shortcode( $template );
				unset($GLOBALS['post']);
			}
		} else {
			$caption = do_shortcode( $template );
		}
		return $caption;
	}
	//
	// PICTUREFILL SUPPORT
	//
	/**
	 * A better picturefill.js version 2 script registration
	 */
	public function register_picturefill_script() {
      // For safety, don't use cdn on admin pages
      if ( apply_filters( 'picturefill_wp_use_cdn', false ) && !is_admin() ) {
        $picturefill_url =  sprintf(
          apply_filters( 'picturefill_wp_cdn_urlstring', 'http%s://cdnjs.cloudflare.com/ajax/libs/picturefill/%s/picturefill%s.js' ),
          ( is_ssl() ) ? 's' : '',
          self::PICTUREFILL_JS_VERSION,
          ( WP_DEBUG ) ? '' : '.min'
        );
        $version = null; // no need to append querystring if going to a CDN
      } else {
        $picturefill_url = sprintf(
          '%s/js/picturefill%s.js',
          $this->static_url,
          ( WP_DEBUG ) ? '' : '.min'
        );
        $version = self::PICTUREFILL_JS_VERSION;
      }

      wp_register_script( 'picturefill', $picturefill_url, array(), $version, true );
	}
	/**
	 * Inject the srcset and srcs for fmlmedia into attachment
	 * 
	 * 0. Only do this sort of work on Flickr Media
	 * 1. Figure out if we're an icon or an image
	 * 2. Generate srcset
	 * 3. Replace src with small image
	 * 4. Figure out sizes (maybe remove width and height)
	 * 5. Queue picturefill
	 * 6. Return image
	 * 
	 * @param  array   $attr the attributes to inject
	 * @param  WP_Post $post the fml media post
	 * @param  mixed   $size size string or array
	 * @return array         adjusted attribute array
	 */
	public function attachment_inject_srcset_srcs( $attr, $post, $size ) {
		// 0. Only do stuff for Flickr Media
		if ( !$post || ( $post->post_type != self::POST_TYPE ) ) {
			return $attr;
		}

		// 1. Figure out if we're an icon or an image
		$icon_sizes = array( 'Square', 'Large Square' );
		list ( $src, $width, $height, $is_intermediate ) = image_downsize( $post->ID, $size );
		$is_icon = ( ( $width == $height ) && ( $width <= 150 ) );


		// 2. Generate srcset
		$metadata = wp_get_attachment_metadata( $post->ID );
		$metadata['sizes'] = $this->maybe_http( $metadata['sizes'] );
		$srcsets = array();
		foreach ( $metadata['sizes'] as $sizename=>$size_data ) {
			if ( in_array( $sizename, $icon_sizes ) ) {
				if ( $is_icon ) {
					$srcsets[] = $size_data['src'] . ' ' . $size_data['width'].'w';
				}
			} else {
				if ( !$is_icon ) {
					$srcsets[] = $size_data['src'] . ' ' . $size_data['width'].'w';
				}
			}
		}
		$attr['srcset'] = implode( ', ', $srcsets );

		// 3. Replace src with small image
		if ( $is_icon ) {
			$attr['src'] = $metadata['sizes']['Square']['src'];
		} else {
			$attr['src'] = $metadata['sizes']['Thumbnail']['src'];
		}

		// 4. Figure out sizes if not set already
		$attr = $this->_image_inject_sizes( $attr, $post, $size );

		// Don't remove width and height since stylesheets will override as
		// these have priority 0. Keeping them will help with initial rendering
		// (even if it causes things to get a bit "jumpy" later).
		//unset($attr['width']);
		//unset($attr['height']);

		// 5. Queue picturefill
		// Will not grow image on older browsers if not in the_content().
		// For instance, if you create an article where only image is post
		// thumbnail
		wp_enqueue_script( 'picturefill' );

		// 6. Return image
		return $attr;
	}
	/**
	 * Add $attr['sizes'] to image when called
	 *
	 * 0. Only run if picturefill is active
	 * 1. Check to see if we're temporarily blocking it (will be called
	 * 2. Check to see if sizes is already applied
	 * 3. If sizes-* class is there, priortize that
	 * 4. If size-* or attachment-* is there (using more reliable $size passed in)
	 * 5. Make "best guess"
	 * 6. do filter and return
	 * 
	 * @param  [type] $attr           [description]
	 * @param  [type] $post           [description]
	 * @param  [type] $size           [description]
	 * @param  array  $shortcode_atts [description]
	 * @return array                  $attr with sizes added
	 */
	private function _image_inject_sizes( $attr, $post, $size, $shortcode_atts=array() ) {
		// 0. Only run if picturefill is active
		if ( !$this->use_picturefill ) { return $attr; }
		// 1. Check to see if we're temporarily blocking it (will be called
		//    again later)
		if ( $this->_picturefill_delay_sizes_transient ) { return $attr; }
		// 2. Check to see if sizes is already applied
		if ( !empty($attr['sizes'] ) ) {
			return apply_filters( 'fml_image_inject_sizes_fail', $attr, $post, $size, $shortcode_atts );
		}

		$picturefill_sizes = $this->_picturefill_sizes;
		// 3. If sizes-* class is there, priortize that
		$sizes_string = '';
		if ( !empty($attr['class'] ) ) {
			$sizes = '';
			$classes = explode(' ', $attr['class'] );
			foreach ( $classes as $class_name ) {
				if ( strpos( 'sizes-', $class_name ) === 0 ) {
					$sizes = substr( $class_name, 6 );
					break;
				}
			}
			if ( $sizes && array_key_exists( $sizes, $picturefill_sizes ) ) {
					$sizes_string= $picturefill_sizes[$sizes];
			}
		}

		// 4. If size-* or attachment-* is there (check size passed in)
		$size_string = ( is_array($size) ) ? implode( 'x', $size ) : $size;
		$picturefill_size_map = $this->_picturefill_size_map;
		if ( !$sizes_string && array_key_exists( $size_string, $picturefill_size_map ) ) {
			$sizes_string= $picturefill_sizes[ $picturefill_size_map[$size_string] ];
		}

		// 5. Make "best guess"
		if ( !$sizes_string && $post && $size ) {
			list ( $src, $width, $height, $is_intermediate ) = image_downsize( $post->ID, $size );
			$sizes_string = sprintf( '(max-width: %1$dpx) 100vw, %1$dpx', $width );
		}
		
		// 6. do filter and return
		if ( $sizes_string) {
			$attr['sizes'] = $sizes_string;
		}
		return apply_filters( 'fml_image_inject_sizes', $attr, $post, $size, $shortcode_atts );
	}
	//
	// CROP SUPPORT
	//
	/**
	 * Emulate cropping using css on the image.
	 * @param  array   $attr  attributes for img tag
	 * @param  WP_Post $post  the fml media post (or attachment)
	 * @param  mixed   $size  desired size string or array
	 * @return array
	 */
	public function attachment_inject_css_crop( $attr, $post, $size )  {
		$size_data = self::image_size( $size );
		list ( $src, $width, $height, $is_intermediate ) = image_downsize( $post->ID, $size );

		// Only run if its a crop and the post is the wrong size
		if ( is_array( $size_data ) ) {
			if ( !$size_data['crop'] ) { return $attr; }
			if ( ( $width == $size_data['width'] ) && ( $height == $size_data['height'] ) ) {return $attr; }
		} else {
			// handles the "full" case which is never cropped
			return $attr;
		}

		// hook for javascript to work
		$attr['class']               .= ' csscrop';

		// need to pass this to cropper
		$attr['data-csscrop-ratio-box']  = $size_data['width']/$size_data['height'];
		$attr['data-csscrop-ratio-img']  = $width/$height;

		// stub out width and height to be what the crop values were supposed to be
		$attr['width'] = $size_data['width'];
		$attr['height'] = $size_data['height'];

		// Assign crop method;	
		if ( empty( $attr['data-csscrop-method'] ) ) {
			$attr['data-csscrop-method'] = apply_filters( 'fml_image_css_crop_set_method', 'centercrop', $attr, $post, $size );
		}

		wp_enqueue_script( 'csscrop' );
		return $attr;
	}
	//
	// ATTACHMENT EMULATIONS
	//
	/**
	 * Filter to duplicate behavior of prepend_attachment but for flickr media.
	 *
	 * prepend_attachment is a filter run to inject the attachment image to the
	 * attachment page. This emulates that behavior but for flickr media.
	 * 
	 * This can run anytime as it directly calls the shortcode.
	 * 
	 * @param  string $content the_content
	 * @return string          the_content with fml shortcode prepend (if get_post() is flickr media)
	 */
	public function attachment_prepend_media( $content ) {
		$post = get_post();
		if ( empty($post->post_type) || $post->post_type != self::POST_TYPE ) { return $content; }

		// show the medium sized image representation of the attachment if available, and link to the raw file
		//$p .= wp_get_attachment_link(0, 'medium', false);
		/**
		 * Filter shortcode content for processing in prepend_media()
		 *
		 * Use this to inject parameters not handled by fml_prepend_media_shortcode_attrs.
		 * @since 1.0
		 * @see attachment_prepend_media()
		 * @param string $content shortcode content
		 */
		$shortcode_content = apply_filters('fml_attachment_prepend_media_shortcode_content', '' );
		/**
		 * Filter shortcode attributes for shortcode processing in prepend_media()
		 *
		 * @since 1.0
		 * @see attachment_prepend_media()
		 * @param array $attrs shortcode attributes
		 */
		$shortcode_attrs = apply_filters('fml_attachment_prepend_media_shortcode_attrs', array(
			'id'   => $post->ID,
			'size' => $this->settings['attachment_prepend_size'],
			'link' => $this->settings['attachment_prepend_link'],
		) );
		$p = $this->shortcode( $shortcode_attrs, $shortcode_content, 'attachment_prepend_media' );
		// append caption if available
		if ( $caption_text = $post->post_excerpt ) {
			list( $img_src, $width, $height ) = image_downsize( $post->ID, $shortcode_attrs['size'] );
			$attr = array(
				'id'      => self::SLUG.'-attachment',
				'width'   => $width,
				'caption' => $caption_text,
				'align'   => 'aligncenter',
			);
			$p = img_caption_shortcode( $attr, $p );
		}
		$p = apply_filters( 'prepend_attachment', $p );

		return "$p\n$content";
	}
	/**
	 * Emulates the behavior of core template-loader.php
	 *
	 * Core will remove the attachment content prepend if the WP_USE_THEMES
	 * is set and displaying the post page itself. The assumption is that
	 * the theme will handle it.
	 *
	 * Note that the only obvious hook to use (reliably) is actually a filter,
	 * not an action. Don't worry the variable would have been set correctly
	 * inside core, we just need to take advantage of a side-effect of the
	 * filter used in it.
	 * 
	 * @param  string $template pass-thru template
	 * @return string
	 */
	public function attachment_maybe_remove_prepend( $template ) {
		//// This is already true since the filter has been called
		//if ( defined('WP_USE_THEMES') && WP_USE_THEMES ) {}
		// CPT equivalent to is_attachment()
		if ( is_singular( self::POST_TYPE ) ) {
			remove_filter( 'the_content', array($this,'attachment_prepend_media') );
		}
		return $template;
	}
	/**
	 * Inject Flickr image downsize code if image is Flickr Media
	 * 
	 * @param  bool         $downsize current status of filter
	 * @param  int          $id       post ID of fml media ("attachment ID")
	 * @param  array|string $size     size of image (e.g. dimensions, 'medium')
	 * @return array|bool             do not short-circuit downsizing
	 *                                or array with url, width, height, is_intermediate
	 * @todo  untested
	 * @see https://developer.wordpress.org/reference/hooks/image_downsize/
	 */
	public function filter_image_downsize( $downsize, $id, $size ) {
		// Only operate on flickr media images
		$post = get_post( $id );
		if ( $post->post_type != self::POST_TYPE ) { return $downsize; }

		// Translate sizes to array (we'll get the "is_intermediate" info by
		// checking for the exact ratio below)
		$size = self::image_size( $size );

		// Special case: full
		// This could be replaced by wp_image_get_metadata except for the need
		// for the full image :-(
		$flickr_data = self::get_flickr_data( $id );
		if ( $size == 'full' ) {
			$img = self::_get_largest_image( $flickr_data );
			return array( $this->maybe_http( $img['source'] ), $img['width'], $img['height'], false );
		}

		$img_sizes = $flickr_data['sizes']['size'];
		// Find closest image size
		$img = array();
		if ( $size['crop'] ) {
			// If image is crop and we support some sort of crop handling
			// upstream then make sure we choose an image that is bigger than
			// the smallest dimension
			if ( apply_filters( 'fml_image_downsize_can_crop', false ) ) {
				foreach ( $img_sizes as $img ) {
					$max_ratio = max( $img['width']/$size['width'], $img['height']/$size['height'] );
					$min_ratio = min( $img['width']/$size['width'], $img['height']/$size['height'] );
					if ( $min_ratio >= 1 ) {
						if ( $max_ratio == 1 ) {
							// perfect match
							return array( $this->maybe_http( $img['source'] ), $img['width'], $img['height'], false );
						}
						// imperfect match
						return array( $this->maybe_http( $img['source'] ), intval($img['width']/$min_ratio), intval($img['height']/$min_ratio), true );
					}
				}
			} else {
				// Other version of crop makes is like a non-crop (below) except for the test for "intermediate"
				foreach ( $img_sizes as $img ) {
					$max_ratio = max( $img['width']/$size['width'], $img['height']/$size['height'] );
					if ( $max_ratio == 1 ) {
						$is_intermediate = ( min( $img['width']/$size['width'], $img['height']/$size['height'] ) == 1 );
						return array( $this->maybe_http( $img['source'] ), $img['width'], $img['height'], $is_intermediate );
					}
					if ( $max_ratio >= 1 ) {
						return array( $this->maybe_http( $img['source'] ), intval($img['width']/$max_ratio), intval($img['height']/$max_ratio), true );
					}
				}

			}
		} else {
			// If image is not crop (or no support for cropping), choose an
			// image where the object that is exactly or slightly larger than
			// the most constraining dimension
			foreach ( $img_sizes as $img ) {
				$max_ratio = max( $img['width']/$size['width'], $img['height']/$size['height'] );
				if ( $max_ratio == 1 ) {
					return array( $this->maybe_http( $img['source'] ), $img['width'], $img['height'], false );
				}
				if ( $max_ratio >= 1 ) {
					return array( $this->maybe_http( $img['source'] ), intval($img['width']/$max_ratio), intval($img['height']/$max_ratio), true );
				}
			}
		}
		// no image was big enough…
		$img = self::_get_largest_image( $flickr_data );
		if ( apply_filters( 'fml_image_downsize_can_crop', false ) )  {
			$ratio = min( $img['width']/$size['width'], $img['height']/$size['height'] );
		} else {
			$ratio = max( $img['width']/$size['width'], $img['height']/$size['height'] );
		}
		return array( $this->maybe_http( $img['source'] ), intval($img['width']/$ratio), intval($img['height']/$ratio), true );
	}
	/**
	 * This will strip the upload_dir() from the path by re-running the same
	 * work to find the file as used when saving the meta info
	 * 
	 * @param  string $file          the value of get_attached_file()
	 * @param  int    $post_id       post id of attachment
	 * @return string
	 */
	static public function filter_get_attached_file( $file, $post_id ) {
		$post = get_post( $post_id );
		// Only operate on flickr media images
		if ( $post && ( $post->post_type != self::POST_TYPE ) ) { return $file; }
		$flickr_data = self::get_flickr_data( $post_id );
		$img = self::_get_largest_image( $flickr_data );
		return $img['source'];
	}
	/**
	 * Inject response of wp_get_attachment_metadata() with emulated data
	 * so that we don't need to save in _wp_attachment_metadata post meta.
	 * 
	 * @param  array  $metadata metadata to return (empty array for fml media)
	 * @param  int    $post_id  post id of attachment
	 * @return array
	 */
	public function filter_wp_get_attachment_metadata( $metadata, $post_id) {
		$post = get_post( $post_id );
		// Only operate on flickr media images
		if ( $post->post_type != self::POST_TYPE ) { return $metadata; }
		$flickr_data = self::get_flickr_data( $post_id );

		$sizes = array();
		$full = self::_get_largest_image($flickr_data);
		$is_square = ( $full['width'] == $full['height'] );
		$metadata['width']  = intval( $full['width'] );
		$metadata['height'] = intval( $full['height'] );
		$metadata['file']   = $full['source'];
		foreach ( $flickr_data['sizes']['size'] as $size_data ) {
			// we'll use _get_largest_image() to get the original if possible
			$label = $size_data['label'];
			if ( $label == 'Original' ) { continue; }
			$sizes[$label] = array(
				'width'  => intval( $size_data['width'] ),
				'height' => intval( $size_data['height'] ),
				'crop'   => ( strpos($label, 'Square') !== false && !$is_square ),
				// not needed in core, but useful here
				'src'    => $size_data['source'],
			);
		}
		// inject back in original (or just overwrite next largest)
		$size_data = self::_get_largest_image($flickr_data);
		$sizes[$size_data['label']] = array(
			'width'  => intval( $size_data['width'] ),
			'height' => intval( $size_data['height'] ),
			'crop'   => ( strpos($size_data['label'], 'Square') !== false && !$is_square ),
			'src'    => $size_data['source'],
		);
		$metadata['sizes']  = $sizes;

		//$metadata['image_meta'] = self::_wp_read_image_metadata( $flickr_data );
		return $metadata;
	}
	/**
	 * Just like wp_prepare_attachment_for_js() but for media images.
	 * 
	 * @param  WP_Post $post 
	 * @return Array|null   hash for use in js
	 * @todo  support injecting sizes
	 */
	static public function wp_prepare_attachment_for_js( $post ) {
		$self = self::get_instance();

		if ( $post->post_type != self::POST_TYPE ) { return; }

		// "defaults" recovered from emulation
		$post->post_type = 'attachment';
		$response = wp_prepare_attachment_for_js($post);
		$post->post_type = self::POST_TYPE;

		$flickr_data = self::get_flickr_data( $post );

		// other emulated things
		$response[ 'authorName' ] = $flickr_data['owner']['realname'];
		$sizes = array();
		foreach ( $flickr_data['sizes']['size'] as $size_data ) {
			// use self::_get_largest_image() side effect to get this one
			if ( $size_data['label'] == 'Original' ) { continue; }
			$sizes[$size_data['label']] = array(
				'url'         => $size_data['source'],
				'width'       => intval($size_data['width']),
				'height'      => intval($size_data['height']),
				'orientation' => ( $size_data['height'] > $size_data['width'] ) ? 'portrait' : 'landscape',
			);
			$response['width']  = intval($size_data['width']);
			$response['height'] = intval($size_data['height']);
		}
		// worst case scenario, it overwrites the largest image
		$full = self::_get_largest_image( $flickr_data );
		$sizes[$full['label']] = array(
			'url'         => $full['source'],
			'width'       => intval($full['width']),
			'height'      => intval($full['height']),
			'orientation' => ( $full['height'] > $full['width'] ) ? 'portrait' : 'landscape',
		);
		$response['sizes'] = $sizes;
		// blocked by hard-coded attachment again
		$response['url'] =  $full['source'];
		// $response['link']: works right now, but it's using get_attachment_link()
		// and might not work down the road
		$resposne['link'] = get_permalink( $post->ID );

		// FML-specific
		$response['flickrId'] = get_post_meta( $post->ID, $self->post_metas['flickr_id'], true );
		$response['_flickrData'] = $flickr_data;
		//$response['photoUrl'] = $flickr_data['urls']['url'][0]['_content'];
		$response['photoUrl'] = self::get_flickr_link($post);
		// testing
		//$response['_file'] = get_attached_file( $post->ID );
		//$response['_metadata'] = wp_get_attachment_metadata( $post->ID );

		return $response;
	}
	/**
	 * Emulate wp_read_image_metadata() but for extracting from flickr API data
	 * instead of exif_read_data() etc.
	 *
	 * The way that the actual function does it is too involved, While this
	 * emulation isn't identical, it's probably good enough.
	 *
	 * 1. Make flickr data sane
	 * 2. Set default meta + cache flickr xform
	 * 3. Handle more complex default meta
	 * 4. Handle compatibility with exifography
	 * 5. TODO: Handle FML-specific meta
	 * 6. Make fields post safe
	 * 
	 * @param  array  $flickr_data flickr API data stored in meta
	 * @return array               image meta from EXIF
	 * @todo  think of moving this into the metadata field
	 */
	static private function _wp_read_image_metadata( $flickr_data ) {
		if ( empty( $flickr_data['exif'] ) ) {
			return array();
		}
		// prevent undefined functione error if using shortcode to generate this
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		// 1. Make flickr data sane
	    $exif = self::_xform_flickr_exif($flickr_data['exif']);

	    // 2. Set default meta + cache flickr xform
	    $meta = array(
	        'aperture'          => ( empty( $exif['Aperture']) ) ? 0 : wp_exif_frac2dec( $exif['Aperture']['raw'] ),
	        'credit'            => '',
	        'camera'            => ( empty( $exif['Model']) ) ? '' : $exif['Model']['raw'],
	        'caption'           => '',
	        'created_timestamp' => 0,
	        'copyright'         => '',
	        'focal_length'      => ( empty( $exif['Focal Length'] ) ) ? 0 : floatval( $exif['Focal Length']['raw'] ),
	        'iso'               => ( empty( $exif['ISO Speed'] ) ) ? 0 : intval($exif['ISO Speed']['raw']),
	        'shutter_speed'     => ( empty( $exif['Exposure'] ) ) ? 0 : wp_exif_frac2dec( $exif['Exposure']['raw'] ),
	        'title'             => '',
	        'orientation'       => ( empty( $exif['Orientation'] ) ) ? 0 : $exif['Orientation']['raw'],
	    );

	    // 3. Handle more complex default meta
	    if ( !empty( $exif['Credit'] ) )        { // IPTC credit
	    	$meta['credit'] = $exif['Credit']['raw'];
	    } elseif ( !empty( $exif['Creator'] ) ) { // ? IPTC legacy byline
	    	$meta['credit'] = $exif['Creator']['raw'];
	    } elseif ( !empty( $exif['Artist'] ) )  { // ? EXIF Artist
	    	$meta['credit'] = $exif['Artist']['raw'];
	    } elseif ( !empty( $exif['Author'] ) )  { // ? EXIF Author
	    	$meta['credit'] = $exif['Author']['raw'];
	    }
	    if ( !empty( $exif['Caption- Abstract'] ) ) { // IPTC Caption-Abstract
	    	$meta['caption'] = $exif['Caption- Abstract']['raw'];
	    } elseif ( !empty( $exif['Description'] ) ) { // ? IPTC legacy caption
	    	$meta['caption'] = $exif['Description']['raw'];
	    	                                          // ?? EXIF COMPUTED user comment
	    } elseif ( !empty( $exif['Image Description'] ) ) { // ? EXIF ImageDescription
	    	$meta['caption'] = $exif['Image Description']['raw'];
	    } elseif ( !empty( $exif['Comments'] ) )     { // ? EXIF Comments
	    	$meta['caption'] = $exif['Comments']['raw'];
	    }
	                                                          // ?? IPTC date and time
	    if ( !empty( $exif['Date and Time (Digitized)'] ) ) { // EXIF CreateDate
	    	$meta['created_timestamp'] = wp_exif_date2ts( $exif['Date and Time (Digitized)']['raw'] );
	    }
	    if ( !empty( $exif['Copyright Notice'] ) ) { // IPTC CopyrightNotice
	    	$meta['copyright'] = $exif['Copyright Notice']['raw'];
	    }                                            // ?? EXIF Copyright

	    if ( !empty( $exif['Headline'] ) )        { // ? IPTC Headline
	    	$meta['title'] = $exif['Headline']['raw'];
	    } elseif ( !empty( $exif['Title'] ) )     { // ? IPTC Title
	    	$meta['title'] = $exif['Title']['raw'];
	    }                                           // Do not support trimming captions

	    // 4. Handle compatibility with exifography
	    // GPS stuff
	    if ( !empty( $exif['GPS Latitude'] ) )      { // exif GPSLatitude
	    	$meta['latitude']      = $exif['GPS Latitude']['raw'];
	    }
	    if ( !empty( $exif['GPS Latitude Ref'] ) )  { // exif GPSLatitudeRef
	    	$meta['latitude_ref']  = $exif['GPS Latitude Ref']['raw'];
	    }
	    if ( !empty( $exif['GPS Longitude'] ) )     { //exif GPSLongitude
	    	$meta['longitude']     = $exif['GPS Longitude']['raw'];
	    }
	    if ( !empty( $exif['GPS Longitude Ref'] ) ) { //exif GPSLongitudeRef
	    	$meta['longitude_ref'] = $exif['GPS Longitude Ref']['raw'];
	    }
	    // Exposure Bias
	    if ( !empty( $exif['Exposure Bias'] ) )     { //exif ExposureBiasValue
	    	$meta['exposure_bias'] = $exif['Exposure Bias']['raw'];
	    }
	    // Flash
	    if ( !empty( $exif['Flash'] ) )             { //exif Flash
	    	$meta['flash']         = $exif['Flash']['raw'];
	    }
	    // Lens was commented out

	    // 5. Handle FML-specific meta
	    //    camera_clean = flickr data
	    if ( !empty( $flickr_data['camera'] ) ) {
	    	$meta['camera_clean'] = $flickr_data['camera'];
	    }
	    if ( !empty( $exif['Aperture'] ) ) {
	    	$meta['aperture_clean'] = $exif['Aperture']['clean'];
	    }
	    if ( !empty( $exif['Date and Time (Origninal)'] ) ) {
	    	$meta['created_timestamp_clean'] = $exif['Date and Time (Original)']['clean'];
	    }
	    if ( !empty( $exif['Exposure'] ) ) {
	    	$meta['exposure_clean'] = $exif['Exposure']['clean'];
	    }
	    if ( !empty( $exif['Focal Length'] ) ) {
	    	$meta['focal_length_clean'] = $exif['Focal Length']['clean'];
	    }
	    //    exposure_bias_clean = Exposure Bias Clean
	    if ( !empty( $exif['Exposure Bias'] ) ) {
	    	$meta['exposure_bias_clean'] = $exif['Exposure Bias']['clean'];
	    }
	    // ExposureMode
	    //    Flickr handles it better and will auto extract it from EXIF
	    //    so prefer that
	    if ( !empty( $flickr_data['location'] ) ) {
	    	if ( !empty( $flickr_data['location']['latitude'] ) ) {
	    		$meta['latitude_clean'] = $flickr_data['location']['latitude'];
	    	}
	    	if ( !empty( $flickr_data['location']['longitude'] ) ) {
	    		$meta['longitude_clean'] = $flickr_data['location']['longitude'];
	    	}
	    	if ( !empty( $flickr_data['location']['locality'] ) ) {
	    		$meta['locality'] = $flickr_data['location']['locality']['_content'];
	    	}
	    	if ( !empty( $flickr_data['location']['county'] ) ) {
	    		$meta['county'] = $flickr_data['location']['county']['_content'];
	    	}
	    	if ( !empty( $flickr_data['location']['region'] ) ) {
	    		$meta['region'] = $flickr_data['location']['region']['_content'];
	    	}
	    	if ( !empty( $flickr_data['location']['country'] ) ) {
	    		$meta['country'] = $flickr_data['location']['country']['_content'];
	    	}
	    }
	    //    exposure_program = Exposure Program 
	    if ( !empty( $exif['Exposure Program'] ) ) {
	    	$meta['exposure_program'] = $exif['Exposure Program']['raw'];
	    }
	    //    focal_length_35 = Focal Length (35mm format)
	    if ( !empty( $exif['Focal Length (35mm format)'] ) ) {
	    	$meta['focal_length_35'] = $exif['Focal Length (35mm format)']['raw'];
	    }
	    // GPS Altitiude
		// GPS Altitude Ref
		// GPS Date Stamp
		// GPS Speed
		// GPS SPeed Ref
		// GPS TIme Stamp
		//     lens = Lens || Lens Make + Model
		if ( !empty( $exif['Lens'] ) ) {
			$meta['lens'] = $exif['Lens']['raw'];
		}
	    if ( !empty( $exif['Lens Make'] ) ) {
	    	$meta['lens'] = $exif['Lens Make']['raw'];
	    }
		//     lens .= Lens Model
	    if ( !empty( $exif['Lens Model'] ) ) {
	    	if ( !empty( $meta['lens'] ) ) {
	    		$meta['lens'] .= ' ';
	    	} else {
	    		$meta['lens'] = '';
	    	}
	    	$meta['lens'] .= $exif['Lens Model']['raw'];
	    }
		//     lens_info = Lens Info
	    if ( !empty( $exif['Lens Info'] ) ) {
	    	$meta['lens_info'] = $exif['Lens Info']['raw'];
	    }
	    // Metering Mode
	    // City, Provice- State, Country- Primary Location Name
	    $meta['_flickr'] = $exif;

	    // 6. Make all fields post safe (esp description)
	    foreach ( $meta as $key=>$value ) {
	    	if ( is_string( $value ) ) {
	    		$meta[$key] = wp_kses_post($value);
	    	}
	    }

	    // DO NOT trigger wp_read_image_metadata as the image is unlikely to be
	    // readable
	    return $meta;
	}
	/**
	 * Turn EXIF data into a hash that's more accessible
	 * 
	 * @param  array  $exif_array Output from Flickr's EXIF API
	 * @return array  a hash
	 */
	static private function _xform_flickr_exif( $exif_array ) {
		$return = array();
		foreach ( $exif_array as $data ) {
			$exif_data = array(
				'tag'      => $data['tag'],
				'raw'      => $data['raw']['_content'],
				'tagspace' => $data['tagspace'],
				'label'    => $data['label'],
			);
			if ( !empty($data['clean'] ) ) {
				$exif_data['clean'] = $data['clean']['_content'];
			}
			$return[$data['label']] = $exif_data;
			/*
			if ( !array_key_exists( $data['tagspace'], $return ) ) {
				$return[$data['tagspace']] = array();
			}
			$return[$data['tagspace']][$data['tag']] = $exif_data;
			*/
		}
		return $return;
	}
	//
	// FLICKR UTILITY FUNCTIONS
	// 
	/**
	 * Attempt to find flickr_id from content (look for photo page)
	 * @param  string $html content to look for
	 * @return string|false the flickr id found or false
	 */
	static public function extract_flickr_id($html) {
		//e.g. https://www.flickr.com/photos/tychay/16452349917
		if ( preg_match( self::REGEX_FLICKR_PHOTO_URL, $html, $matches ) ) {
			return $matches[1];
		}
		return false;
	}
	/**
	 * This is needed because cropping doesn't exist in Flickr.
	 *
	 * Later we'll have a hook to override this
	 * @param  mixed  $size size string or array
	 * @return array        size array | 'full'
	 */
	static public function image_size( $size ) {
		global $_wp_additional_image_sizes;

		if ( is_array($size) ) {
			// turn an dimension size into the same format as
			// $wp_additional_image_sizes
			if ( !isset($size['crop']) ) {
				return array(
					'width'  => $size[0],
					'height' => $size[1],
					'crop'   => apply_filters( 'fml_image_size_array_is_crop', false ),
				);
			}
			return $size;
		}
		switch ( $size ) {
			// built in types
			case 'thumbnail':
				return array(
					'width'  => get_option( 'thumbnail_size_w', 150 ),
					'height' => get_option( 'thumbnail_size_h', 150 ),
					'crop'  => true,
				);
			case 'medium':
				return array(
					'width'  => get_option( 'medium_size_w', 300 ),
					'height' => get_option( 'medium_size_h', 300 ),
					'crop'  => false,
				);
			case 'large':
				return array(
					'width'  => get_option( 'large_size_w', 1024 ),
					'height' => get_option( 'large_size_h', 1024 ),
					'crop'  => false,
				);
			case 'full':
			// special case flickr size
			case 'Original': //flickr size, if it is available then this should be fine
				return 'full';
			default:
				// check if built-in flickr types
				$maybe_size = self::flickr_sizes_to_dims( $size );
				// it's either a flickr size or a WordPress size type
				return ( $maybe_size ) ? $maybe_size : $_wp_additional_image_sizes[$size];
		}
	}
	/**
	 * Recognize flickr size strings.
	 *
	 * Supports <size>, <size_in_classname>, and size-<size_in_classname>.
	 * 
	 * @param  string  $size_string the string (from a class) or transformed by
	 *                              class
	 * @return string|false         the Flickr size string
	 */
	static public function extract_flickr_sizes( $size_string, $is_class=false ) {
		// extract from class name if it is one
		if ( $is_class ) {
			if ( preg_match('!size-([a-z0-9_]+)!i', $size_string, $matches ) ) {
				$size_string = $matches[1];
			} elseif ( preg_match('!attachment-([a-z0-9_]+)!i', $size_string, $matches ) ) {
				$size_string = $matches[1];
			}
		}
		switch ( $size_string ) {
			case 'Original':
			case 'size-Original':
			case 'full': //handle "full" from emulated mode
			case 'size-full':
				return 'Original';
			case 'Square':
			case 'size-Square':
				return 'Square';
			case 'Large Square':
			case 'Large_Square':
			case 'size-Large_Square':
				return 'Large Square';
			case 'Thumbnail':
			case 'size-Thumbnail':
				return 'Thumbnail';
			case 'Small':
			case 'size-Small':
				return 'Small';
			case 'Small 320':
			case 'Small_320':
			case 'size-Small_320':
				return 'Small 320';
			case 'Medium':
			case 'size-Medium':
				return 'Medium';
			case 'Medium 640':
			case 'Medium_640':
			case 'size-Medium_640':
				return 'Medium 640';
			case 'Medium 800':
			case 'Medium_800':
			case 'size-Medium_800':
				return 'Medium 800';
			case 'Large':
			case 'size-Large':
				return 'Large';
			case 'Large 1600':
			case 'Large_1600':
			case 'size-Large_1600':
				return 'Large 1600';
			case 'Large 2048':
			case 'Large_2048':
			case 'size-Large_2048':
				return 'Large 2048';
		}
		return false;
	}
	/**
	 * Get (usable) dims from flickr sizes.
	 *
	 * By "usable", if Original, it will actually return dims for largest image
	 * @param  string $size_string {@see FML\FML::extract_flickr_sizes()}
	 * @return array|false         width, height, crop or false if no match
	 */
	static public function flickr_sizes_to_dims( $size_string ) {
		$size_string = self::extract_flickr_sizes( $size_string );
		if ( !$size_string ) { return false; }
		switch ( $size_string ) {
			case 'Original': //flickr size, if it is available then this shoudl be fine
				$img = self::_get_largest_image( $flickr_data );
				return array(
					'width'  => $img['width'],
					'height' => $img['height'],
					'crop'   => false,
				);
			case 'Square':
				return array(
					'width'  => 75,
					'height' => 75,
					'crop'  => true,
				);
			case 'Large Square':
				return array(
					'width'  => 150,
					'height' => 150,
					'crop'  => true,
				);
			case 'Thumbnail':
				return array(
					'width'  => 100,
					'height' => 100,
					'crop'  => false,
				);
			case 'Small':
				return array(
					'width'  => 240,
					'height' => 240,
					'crop'  => false,
				);
			case 'Small 320':
				return array(
					'width'  => 320,
					'height' => 320,
					'crop'  => false,
				);
			case 'Medium':
				return array(
					'width'  => 500,
					'height' => 500,
					'crop'  => false,
				);
			case 'Medium 640':
			case 'Medium_640':
				return array(
					'width'  => 640,
					'height' => 640,
					'crop'  => false,
				);
			case 'Medium 800':
			case 'Medium_800':
				return array(
					'width'  => 800,
					'height' => 800,
					'crop'  => false,
				);
			case 'Large':
				return array(
					'width'  => 1024,
					'height' => 1024,
					'crop'  => false,
				);
			case 'Large 1600':
				return array(
					'width'  => 1600,
					'height' => 1600,
					'crop'  => false,
				);
			case 'Large 2048':
				return array(
					'width'  => 2048,
					'height' => 2048,
					'crop'  => false,
				);
		}
	}
	//
	// FLICKR MEDIA POSTTYPE
	// 
	/**
	 * Adds an image from flickr into the flickr media library
	 * 
	 * @param  string $flickr_id the flickr ID of the image
	 * @return int|false     the post id created (or false if not)
	 */
	static public function create_media_from_flickr_id( $flickr_id ) {
		// Check to see it's not already added, if so return that
		if ( $post_already_added = self::get_media_by_flickr_id( $flickr_id ) ) {
			// update post and return it
			return self::update_flickr_post( $post_already_added );
		}
		$data = self::_get_data_from_flickr_id( $flickr_id );
		if ( empty( $data ) ) {
			return false;
		}
		$post_id = self::_new_post_from_flickr_data( $data );

		// you cannot create a capton until the post has been saved because
		// template variables are built from the post data
		self::caption_create( $post_id );
		return $post_id;
	}
	/**
	 * Attempts to get post stored by flickr_id
	 * 
	 * @param  string $flickr_id the flickr ID of the image
	 * @return WP_Post|false     the post found (or false if not)
	 * @todo   consider doing extra work
	 */
	static public function get_media_by_flickr_id( $flickr_id ) {
		$post_already_added = get_posts( array(
			'name'           => self::_flickr_id_to_name($flickr_id),
			'post_type'      => self::POST_TYPE,
			//'posts_per_page' => 1,
		) );
		if ( $post_already_added ) {
			return $post_already_added[0];
			// TODO: extra work
		}
		return false;
	}
	/**
	 * Update a post from either flickr cache or through API calls to flickr.
	 * 
	 * @param  WP_Post|integer $post      The post or its post ID.
	 * @param  bool            $force_api whether or force API call or use the
	 *                                    cache
	 * @return WP_Post|false              Updated content
	 */
	static function update_flickr_post( $post, $force_api=false ) {
		if ( !is_object( $post ) ) {
			$post = get_post( $post );
		}
		if ( $post->post_type !== self::POST_TYPE ) { return false; }
		$flickr_data = self::get_flickr_data( $post );
		if ( $force_api ) {
			$flickr_data = self::_update_data_from_flickr( $flickr_data );
		}
		$post_data = self::_post_data_from_flickr_data( $flickr_data );
		$post_data['ID'] = $post->ID;

		// update post
		$post_id = wp_update_post( $post_data );
		self::_update_flickr_post_meta( $post_id, $flickr_data );

		return $post_id;
	}
	/**
	 * Update post_meta fields managed by flickr (or emulated for attachments)
	 * from flickr data
	 * @param  int    $post_id     Flickr Media post id
	 * @param  array  $flickr_data The flickr data to extract and write
	 * @return void
	 */
	static private function _update_flickr_post_meta( $post_id, $flickr_data) {
		$self = self::get_instance();

		// flickr post metas
		update_post_meta( $post_id, wp_slash( $self->post_metas['api_data'] ), $flickr_data );
		update_post_meta( $post_id, $self->post_metas['flickr_id'], $flickr_data['id'] );

		// emulated post metas
		$img = self::_get_largest_image( $flickr_data );
		update_post_meta( $post_id, '_wp_attached_file', $img['source'] );
		// Let's store these because it is mildly "expensive" to compute
		$meta = array(
			'image_meta' => self::_wp_read_image_metadata( $flickr_data )
		);
		update_post_meta( $post_id, '_wp_attachment_metadata', wp_slash( $meta ) );
	}
	/**
	 * Accessor for getting image alt to deal with the confusion between where
	 * and when wp_slash()/wp_unslash() is done.
	 * 
	 * @param  mixed  $post can be post or post_id (post_id is preferrd)
	 * @return string       the alt attribute
	 */
	static public function get_image_alt($post) {
 		$post_id = ( is_object( $post ) ) ? $post->ID : $post;
		return get_post_meta( $post_id, '_wp_attachment_image_alt', true );
	}
	/**
	 * Accessor for setting image alt to deal with the fact update_post_meta
	 * has stripslashes() built in (yes, it does, can you beleive??)
	 * 
	 * @param  mixed  $post  can be post or post_id (post_id is preferrd)
	 * @param  string $value the value to store (unslashed).
	 */
	static public function set_image_alt( $post, $value ) {
 		$post_id = ( is_object( $post ) ) ? $post->ID : $post;
		update_post_meta( $post_id, '_wp_attachment_image_alt', wp_slash( $value ) );
	}
	/**
	 * Get the Flickr API data from post meta
	 * 
	 * @param  int|WP_Post $post id or post of a flickr media library attachment
	 * @return array       cached output from flickr API
	 */
	static public function get_flickr_data( $post ) {
		$self = self::get_instance();

		if ( is_object($post) ) {
			$post_id = $post->ID;
		} else {
			$post_id = $post;
		}
		return get_post_meta( $post_id, $self->post_metas['api_data'], true );
	}
	/**
	 * Get the flickr link to the photo page
	 *
	 * @todo  could add better checking and verify it's the photopage
	 */
	static public function get_flickr_link( $post ) {
		$flickr_data = self::get_flickr_data( $post );
		return $flickr_data['urls']['url'][0]['_content'];
	}
	// PRIVATE METHODS
	/**
	 * Generates a new post from the flickr data given.
	 *
	 * Also updates caption (to save an extra db call)
	 * @param  [type] $data [description]
	 * @return [type]       [description]
	 */
	static private function _new_post_from_flickr_data( $data ) {
		if ( empty($data ) ) { return 0; }
		$post_data = self::_post_data_from_flickr_data( $data );
		$post_id = wp_insert_post( $post_data );

		// add grabbed information into post_meta
		self::_update_flickr_post_meta( $post_id, $data );

		return $post_id;
	}
	static private function _update_data_from_flickr( $data ) {
		$return = self::_get_data_from_flickr_id( $data['id'], $data['dates']['lastupdate'] );
		if ( empty( $return ) ) {
			return $data;
		} else {
			return $return;
		}
	}
	/**
	 * Grab photo information from flickr API using flickr ID of photo
	 *
	 * Structure of information is an array with the following parameters:
	 *
	 * - id: flickr photo id
	 * - media: photo, ?
	 * - secret: secret
	 * - server: server #
	 * - farm: farm #
	 * - originalsecret: secret key
	 * - originalformat: "jpg" or wahtever
	 * - isfavorite: is favorited by self
	 * - license: license # it is under
	 * - safety_level: ?
	 * - rotation: degrees rotated from original?
	 * - owner: iconfarm:8, iconserver, location, nsid, path_alias, realname, username
	 * - title._content
	 * - description:_content: description file
	 * - dateuploaded: unix time of date uploaded
	 * - dates[lastupdate]: unix time of last update
	 * - dates[posted]: unix time of posted date
	 * - dates[taken]: UTC of date taken
	 * - dates[takengranularity]: ?
	 * - dates[takenunknown]: ?
	 * - visibility[ispublic, isfriend, isfamily]
	 * - permissions[permaddmeta,permcomment]: ???
	 * - editability[canaddmeta,cancomment]: 1/0 
	 * - publiceditability[canaddmeta,cancomment]: (0 or 1)
	 * - views: #
	 * - usage[candownload, canblog, canprint, canshare]
	 * - comments._content: # of comments
	 * - notes: ??
	 * - people.haspeople: (# of people linked)
	 * - tags[tag][#]: an array of _content objects with the tags applied has properties aid, author, raw and _content.
	 * - location:
	 * - geoperms:
	 * - urls[url[0]]: link to photo page
	 * - sizes[canblog, canprint, candownload]: permissions 1 or 0
	 * - sizes[size]: array with properties [label,width,height,source,url,media]
	 * - camera: exif extracted camer name
	 * - exif[]: array with properties [tagspace, tagspaceid, tag, label, raw._content]
	 * 
	 * @param  string  $flickr_id    flickr ID of photo
	 * @param  integer $last_updated the unix timestamp it was last updated
	 * @return array                 array of various raw flickr data merged, empty if no data to add
	 */
	static private function _get_data_from_flickr_id( $flickr_id, $last_updated=0 ) {
		$self = self::get_instance();
		$flickr_api = $self->flickr;

		$return = array();
		$params = array(
			'photo_id' => $flickr_id,
		);
		// https://www.flickr.com/services/api/flickr.photos.getInfo.html
		$result = $flickr_api->call('flickr.photos.getInfo', $params, $self->is_flickr_authenticated() );
		if ( !empty($result['stat']) && ($result['stat'] == 'ok') ) {
			$return = $result['photo'];
		}
		// don't refresh if up-to-date
		if ( $last_updated && ( $return['dates']['lastupdate'] <= $last_updated ) ) {
			return array();
		}
		/* */
		$result = $flickr_api->call('flickr.photos.getSizes', $params, $self->is_flickr_authenticated() );
		if ( !empty($result['stat']) && ($result['stat'] == 'ok') ) {
			$return['sizes'] = $result['sizes'];
		}
		$result = $flickr_api->call('flickr.photos.getExif', $params, $self->is_flickr_authenticated() );
		if ( !empty($result['stat']) && ($result['stat'] == 'ok') ) {
			$return = array_merge($return, $result['photo']);
		}
		return $return;
	}
	/**
	 * Turn flickr API data into post data
	 * @param  srray $data  The photo data extracted from the flickr API
	 * @return array        The data suitable for creating a post (or updating if you add the ID)
	 * @todo  validate media is a photo
	 * @todo  vary date based on configuration: date uploaded, date taken, date posted?
	 */
	static private function _post_data_from_flickr_data( $data ) {
		// generate list of tags
		$self = self::get_instance();
		$post_tags = [];
		if ( !empty( $data['tags']['tag'] ) ) {
			$tags = $data['tags']['tag'];
			foreach ( $tags as $idx=>$tag_data ) {
				$post_tags[] = $tag_data['raw'];
			}
		}
		// TODO handle this better
		//switch ( $data['media'] ) {}
		$mime_type = 'image/jpeg';
		// generate post array (from data)
		$post_data = array(
			//ID
			//'post_author'               // SEE BELOW
			//'post_date'                 // SEE BELOW
			//'post_date_gmt'             // SEE BELOW
			'post_content'   => $data['description']['_content'], // image content will be prepended using the_content hook a la attachments
			'post_title'     => $data['title']['_content'],
			//'post_excerpt'              // CAPTION. Set separately due to caption templates + not on flickr
			'post_status'    => ( $data['visibility']['ispublic'] ) ? 'publish' : 'private', 
			'comment_status' => 'closed', // comments should be on flickr page only
			'ping_status'    => 'closed', // no pingbacks
			//'post_password'
			'post_name'      => self::_flickr_id_to_name( $data['id'] ), // post slug used to do reverse lookups by flickr id
			//'to_ping'
			//'pinged'
			//'post_modified'
			'post_modified_gmt' => gmdate( 'Y m d H:i:s', $data['dates']['lastupdate'] ),
			//'post_content_filtered' => let wordpress handle?
			//'post_parent'    => 0,    // TODO
			//'guid' // Let wordpress handle
			//'menu_order'     => (for page ordering)
			'post_type'      => self::POST_TYPE,
			'post_mime_type' => $mime_type,
			//'comment_count' => TODO
			
			//'post_category' => array of cateogires
			'tags_input'     => $post_tags,
			//'tax_input' => other taxonomy
			//'page_template' (empty)
			//'file' (from attachment) @see https://codex.wordpress.org/Function_Reference/wp_insert_attachment
		);
		if  ( $self->settings['post_author_id_other'] ) {
			$post_data['post_author'] = ( $data['owner']['nsid'] == $self->settings[Flickr::USER_NSID] ) ? get_current_user_id() : $self->settings['post_author_id_other'];
		}
		// handle post_date
		switch ( $self->settings['post_date_map'] ) {
			case 'posted':
				$post_data['post_date']     = date( 'Y-m-d H:i:s', $data['dates']['posted']);
				$post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $data['dates']['posted']);
				break;
			case 'taken':
				// probably can refine this using date taken granularity
				$time = strtotime( $data['dates']['taken'] );
				$post_data['post_date']     = date( 'Y-m-d H:i:s', $time );
				$post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $time );
			case 'lastupdate':
				$post_data['post_date']     = date( 'Y-m-d H:i:s', $data['dates']['lastupdate']);
				$post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $data['dates']['lastupdate']);
				break;
			//default (case 'none'). When creating, WordPress uses current time,
			//and it maintains this on update so no worries on update
		}
		// make sure it has a title
		if ( empty($post_data['post_title']) ) {
			$post_data['post_title'] = $data['id'];
		}
		return $post_data;
	}
	/**
	 * Turns a flickr ID into a post slug
	 * 
	 * @param  string $flickr_id The number representing the flickr_id of the image
	 * @return string            post slug if it exists in the database
	 */
	static private function _flickr_id_to_name( $flickr_id ) {
		return self::SLUG.'-'.$flickr_id;
	}
	/**
	 * Get the largest usable flickr image.
	 *
	 * Return original image if possible, if not practical (rotated, a tiff),
	 * return the largest one available. This takes advantage of the fact that
	 * the flickr API orders its sizes.
	 *
	 * Because this can be used in different places, the https is not
	 * transformed to http
	 * 
	 * @param  int|array $flickr_data if integer, its the post_id of flickr
	 *         media, else it's the flickr_data
	 * @param  bool      $force_original whether to get original even if
	 *         WordPress doesn't recognize it as an image or can't rotate it.
	 * @return array     the sizes array element of the largest size
	 */
	static private function _get_largest_image( $flickr_data, $force_original = false ) {
		if ( !is_array( $flickr_data ) ) {
			$post = get_post( $flickr_data );
			// Only operate on flickr media images
			if ( $post->post_type != self::POST_TYPE ) {
				return false;
			}
			$flickr_data = self::get_flickr_data( $id );
		}
		$sizes = $flickr_data['sizes']['size'];
		$count_img_sizes = count($sizes);

		$img = $sizes[ $count_img_sizes-1 ];
		if ( !$force_original ) {
			return $img;
		}
		if ( $img['label'] != 'Original' ) {
			return $img;
		}
		// see https://developer.wordpress.org/reference/functions/wp_attachment_is/
		$check = wp_check_filetype( $img['source']);
		$image_exts = array( 'jpg', 'jpeg', 'jpe', 'gif', 'png' );
		if ( ( $flickr_data['rotation'] == 0 ) && in_array( $check['ext'], $image_exts ) ) {
			return $img;
		}
		// original is invalid
		return $sizes[ $count_img_sizes-2 ];
	}
	//
	// CLASS FUNCTIONS
	// 
	static function debug_filter_show_variables( $return )  {
		var_dump( func_get_args() );
		return $return;
	}
	static function https_to_http( $url ) {
		if ( is_array( $url ) ) {
			foreach ( $url as $key=>$value ) {
				$url[$key] = self::https_to_http( $value );
			}
			return $url;
		}
		if ( strpos( $url, 'https' ) === 0 ) {
			return 'http' . substr( $url, 5 );
		}
	}
	public function maybe_http( $variable ) {
		if ( is_ssl() || !$this->settings['image_prefer_http'] ) {
			return $variable;
		}
		return self::https_to_http( $variable );
	}
}
