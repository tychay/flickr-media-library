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
	 * @var string Cache the location of plugin templates directory
	 */
	public $template_dir;
	/**
	 * @var string Cache the url location of plugin statics directory
	 */
	public $static_url;
	/**
	 * @var string Cache the plugin basename
	 */
	public $plugin_basename;
	/**
	 * @var string the option name for the permalink base (access through _get)
	 */
	private $_permalink_slug_id;
	private $_post_metas = array();
	//
	// CONSTRUCTORS AND DESTRUCTORS
	//
	/**
	 * Plugin Initialization
	 *
	 * Note that currently this is not called until `plugins_loaded` has been
	 * fired, but in the future, it'd be created directly on the main page.
	 *
	 * This will set the static variables used by the plugin.
	 * 
	 * @param  string $pluginFile __FILE__ for the plugin file
	 */
	function __construct($pluginFile) {
		$this->plugin_dir = dirname($pluginFile);
		$this->template_dir = $this->plugin_dir . '/templates';
		$this->static_url = plugins_url('static',$pluginFile);
		$this->plugin_basename = plugin_basename($pluginFile);
		$this->_permalink_slug_id = str_replace('-','_',self::SLUG).'_base';
		$this->_post_metas = array(
			'api_data' => '_'.str_replace('-','_',self::SLUG).'_api_data',
			'flickr_id' => '_flickr_id',
		);
		// settings and flickr are lazy loaded
	}
	/**
	 * Returns instance.
	 *
	 * Used for static method calls
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
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'init', array( $this, 'init' ) );
		add_shortcode( self::SHORTCODE, array( $this, 'shortcode') );
	}
	/**
	 * Stuff to run on `init`
	 * 
	 * - Register the custom post type used for storing flickr photos. If the
	 *   register is called earlier, it won't trigger due to missing object on
	 *   rewrite rule. {@see https://codex.wordpress.org/Function_Reference/register_post_type}
	 * - register filter image_downsize to add fmlmedia image_downsize() support
	 * - register filter media_send_to_editor to wrap shortcode around fmlmedia
	 * 
	 * @return void
	 */
	public function init() {
		// https://codex.wordpress.org/Function_Reference/register_post_type
		register_post_type(self::POST_TYPE, array(
			'labels'              => array(
				// name of post type in plural and singualr form
				'name'               => _x( 'Flickr Media', 'plural', self::SLUG ),
				'singular_name'      => _x( 'Flickr Media', 'singular', self::SLUG ),
				//'menu_name'          => __( 'menu_name', self::SLUG ), //name that appears in custom menu and listing
				//'name_admin_bar'     => __( 'name_admin_bar', self::SLUG ), //as post appears in the + New part of the admin bar
				'not_found_in_trash' => __( 'not_found_in_trash', self::SLUG ),
				'add_new'            => __( 'Import Flickr', self::SLUG ), // name for "add new" menu and button using lang space, so no need to use context
				'add_new_item'       => __( 'add_new_item', self::SLUG ),
				'edit_item'          => __( 'edit_item', self::SLUG ),
				'new_items'          => __( 'new_items', self::SLUG ),
				'search_items'       => __( 'search_items', self::SLUG ),
				'not_found'          => __( 'not_found', self::SLUG ),
				'not_found_in_trash' => __( 'not_found_in_trash', self::SLUG ),
				'parent_item_colon'  => __( 'parent_item_colon', self::SLUG ),
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
				'editor',
				//'author', //can have author
				'thumbnail', // TODO: can have featured image?
				'excerpt', //caption text
				//'trackbacks',
				//'custom-fields',
				'comments',
				//'revisions', //TODO
				//'page-attributes', //menu order if hierarchical is true
				//'post-formats', //TODO
			),
			//'register_meta_box_cb'=> //TODO: callback function when setting up metaboxes
			'taxonomies'          => array( 'post_tag' ), // support post tags taxonom
			'has_archive'         => true, // can have archive template
			//'permalink_epmask'    => // endpoint bitmask?
			'rewrite'             => array(
				'slug' => $this->permalink_slug,
				'with_front' => false, //make it a root level, just like categories & tags
				//'feeds' //defaults to 'has_archive' value
				//'pages' //allow pagination?
				////ep_mask
			),
			//'query_var'           => '', default query var
			//'can_export'          => true, // can be exported
		));
		add_filter( 'image_downsize', array( $this, 'filter_image_downsize'), 10, 3 );
		add_filter( 'media_send_to_editor', array( $this, 'filter_media_send_to_editor'), 10, 3);
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
	 * @param  string $name the property to get
	 * @return mixed the thing to be gotten
	 */
	public function __get($name) {
		switch( $name ) {
			case 'settings':
				if ( empty( $this->_settings ) ) {
					$this->_load_settings();
				}
				return $this->_settings;
			case 'flickr':
				return $this->_get_flickr();
			case 'flickr_callback':
				return $this->_flickr_callback;
			case 'permalink_slug_id':
				return $this->_permalink_slug_id;
			case 'permalink_slug':
				return get_option( $this->permalink_slug_id, self::_DEFAULT_BASE );
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
				trigger_error( 'Set plugin settings through update_settings()' );
				break;
			case 'flickr':
				trigger_error( sprintf( 'Not allowed to externally set flickr API.', $name ) );
				break;
			case 'flickr_callback':
				$this->_flickr_callback = $value;
				break;
			case 'permalink_slug':
				update_option( $this->permalink_slug_id, $value );
			default:
				trigger_error( sprintf( 'Property %s is not settable', $name ) );
			break;
		}
	}
	/**
	 * @var array Plugin blog options
	 */
	private $_settings = array();
	//
	// PROPERTIES: Settings
	// 
	/**
	 * Load options array into settings variable
	 * 
	 * (and assign anything missing so it upgrades transparently).
	 *
	 * The options array is stored in the blog options using the SLUG as the
	 * key
	 */
	private function _load_settings()
	{
		$settings_changed = false;
		$settings = get_option(self::SLUG, array());
		$_default_settings = array(
			'flickr_api_key'                  => self::_FLICKR_API_KEY,
			'flickr_api_secret'               => self::_FLICKR_SECRET,
			Flickr::USER_FULL_NAME            => '',
			Flickr::USER_NAME                 => '',
			Flickr::USER_NSID                 => '',
			Flickr::OAUTH_ACCESS_TOKEN        => '',
			Flickr::OAUTH_ACCESS_TOKEN_SECRET => '',
			//photo link option
			//link rel option
			//link class option
		);
		// upgrade missing parameters (or initialize defaults if none)
		foreach ($_default_settings as $option=>$value) {
			if ( !array_key_exists($option, $settings) ) {
				$settings[$option] = $value;
			}
			$settings_changed = true;
		}
		$this->_settings = $settings;
		if ( $settings_changed ) {
			$this->update_settings();
		}
	}
	/**
	 * Change any settings and save to blog options
	 * 
	 * @param array $settings options to modify/save. If you jsut want to save
	 *        the existing options, just don't provide any settings here.
	 * @return void
	 */
	public function update_settings( $settings=array() )
	{
		foreach ($settings as $key=>$value) {
			$this->_settings[$key] = $value;
		}
		update_option(self::SLUG, $this->_settings);
	}
	//
	// PROPERTES: Flickr
	// 
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
	// PUBLIC METHODS
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
		$this->update_settings(array(
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
		$this->update_settings(array(
			Flickr::USER_FULL_NAME            => '',
			Flickr::USER_NAME                 => '',
			Flickr::USER_NSID                 => '',
			Flickr::OAUTH_ACCESS_TOKEN        => '',
			Flickr::OAUTH_ACCESS_TOKEN_SECRET => '',
		));
	}
	//
	// SHORTCODE HANDLING
	//
	/**
	 * Process shortcode for $content
	 * 
	 * @param  [type] $attrs   [description]
	 * @param  string $content [description]
	 * @return [type]          [description]
	 */
	public function shortcode( $attrs, $content='' ) {
		//TODO
		return $content;	
	}
	//
	// ATTACHEMENT EMULATIONS
	// 
	/**
	 * Inject Flickr image downsize code if image is Flickr Media
	 * 
	 * @param  bool         $downsize current status of filter
	 * @param  int          $id       attachement ID of image
	 * @param  array|string $size     size of image (e.g. dimensions, 'medium')
	 * @return array|bool             do not short-circuit downsizing
	 *                                or array with url, width, height, is_intermediate
	 * @todo  untested
	 * @see https://developer.wordpress.org/reference/hooks/image_downsize/
	 */
	public function filter_image_downsize($downsize, $id, $size) {
		global $_wp_additional_image_sizes;

		$post = get_post( $id );
		// Only operate on flickr media images
		if ( $post->post_type != self::POST_TYPE ) {
			return $downsize;
		}

		$flickr_data = self::get_flickr_data( $id );
		$img_sizes = $flickr_data['sizes']['size'];
		if ( is_string($size) ) {
			switch ( $size ) {
				case 'thumbnail':
					$size = array(
						'width'  => get_option( 'thumbnail_size_w', 150 ),
						'height' => get_option( 'thumbnail_size_h', 150 ),
						'crop'  => true,
					);
					break;
				case 'medium':
					$size = array(
						'width'  => get_option( 'medium_size_w', 300 ),
						'height' => get_option( 'medium_size_h', 300 ),
						'crop'  => false,
					);
					break;
				case 'large':
					$size = array(
						'width'  => get_option( 'large_size_w', 1024 ),
						'height' => get_option( 'large_size_h', 1024 ),
						'crop'  => false,
					);
					break;
				case 'full':
				case 'Original': //flickr size, if it is available then this shoudl be fine
					$img = self::_get_largest_image( $flickr_data );
					return array( $img['source'], $img['width'], $img['height'], false );
				// Flickr built-in types
				case 'Square':
					$size = array(
						'width'  => 75,
						'height' => 75,
						'crop'  => true,
					);
					break;
				case 'Large Square':
				case 'Large_Square':
					$size = array(
						'width'  => 150,
						'height' => 150,
						'crop'  => true,
					);
					break;
				case 'Thumbnail':
					$size = array(
						'width'  => 100,
						'height' => 100,
						'crop'  => false,
					);
					break;
				case 'Small':
					$size = array(
						'width'  => 240,
						'height' => 240,
						'crop'  => false,
					);
					break;
				case 'Small 320':
				case 'Small_320':
					$size = array(
						'width'  => 320,
						'height' => 320,
						'crop'  => false,
					);
					break;
				case 'Medium':
					$size = array(
						'width'  => 500,
						'height' => 500,
						'crop'  => false,
					);
					break;
				case 'Medium 640':
				case 'Medium_640':
					$size = array(
						'width'  => 640,
						'height' => 640,
						'crop'  => false,
					);
					break;
				case 'Medium 800':
				case 'Medium_800':
					$size = array(
						'width'  => 800,
						'height' => 800,
						'crop'  => false,
					);
					break;
				case 'Large':
					$size = array(
						'width'  => 1024,
						'height' => 1024,
						'crop'  => false,
					);
					break;
				case 'Large 1600':
				case 'Large_1600':
					$size = array(
						'width'  => 1600,
						'height' => 1600,
						'crop'  => false,
					);
					break;
				case 'Large 2048':
				case 'Large_2048':
					$size = array(
						'width'  => 2048,
						'height' => 2048,
						'crop'  => false,
					);
					break;
				default:
					$size = $_wp_additional_image_sizes[$size];
			}
		}
		// Find closest image size
		$img = array();
		if ( $size['crop'] ) {
			// If image is crop, then make sure we choose an image that is 
			// bigger than the smallest dimension
			foreach ( $img_sizes as $img ) {
				$max_ratio = max( $img['width']/$size['width'], $img['height']/$size['height'] );
				$min_ratio = min( $img['width']/$size['width'], $img['height']/$size['height'] );
				if ( $min_ratio >= 1 ) {
					if ( $max_ratio == 1 ) {
						// perfect match
						return array( $img['source'], $img['width'], $img['height'], false );
					}
					// imperfect match
					return array( $img['source'], intval($img['width']/$min_ratio), intval($img['height']/$min_ratio), true );
				}
			}
		} else {
			// If image is not crop, choose an image where the object that is
			// exactly or slightly larger than the most constraining dimension
			foreach ( $img_sizes as $img ) {
				$max_ratio = max( $img['width']/$size['width'], $img['height']/$size['height'] );
				if ( $max_ratio == 1 ) {
					return array( $img['source'], $img['width'], $img['height'], false );
				}
				if ( $max_ratio >= 1 ) {
					return array( $img['source'], intval($img['width']/$max_ratio), intval($img['height']/$max_ratio), true );
				}
			}
		}
		$img = self::_get_largest_image( $flickr_data );
		$max_ratio = max( $img['width']/$size['width'], $img['height']/$size['height'] );
		return array( $img['source'], intval($img['width']/$max_ratio), intval($img['height']/$max_ratio), true );
	}
	/**
	 * Modify HTML attachment to add shortcode for flickr media when inserting
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
			$attr_string .=  sprintf(' %s="%s"', $key, esc_attr($value));
		}
		return sprintf( '[%1$s%2$s]%3$s[/%1$s]', self::SHORTCODE, $attr_string, $html );
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
		foreach ( $flickr_data['sizes']['size'] as $size_data ) {
			if ( ( $size_data['label'] == 'Original' )  && ( $flickr_data['rotation'] != 0 ) ) {
				continue;
			}
			$sizes[$size_data['label']] = array(
				'url'         => $size_data['source'],
				'width'       => intval($size_data['width']),
				'height'      => intval($size_data['height']),
				'orientation' => ( $size_data['height'] > $size_data['width'] ) ? 'portrait' : 'landscape',
			);
			$response['width']  = intval($size_data['width']);
			$response['height'] = intval($size_data['height']);
		}
		$response['sizes'] = $sizes;
		
		// FML-specific
		$response['flickrId'] = get_post_meta( $post->ID, $self->post_metas['flickr_id'], true );
		$response['_flickrData'] = $flickr_data;

		return $response;
	}
	//
	// FLICKR MEDIA POSTTYPE
	// 
	/**
	 * Adds an image from flickr into the flickr media library
	 * 
	 * @param  string $flickr_id the flickr ID of the image
	 * @return WP_Post|false     the post created (or false if not)
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
		return get_post($post_id);
	}
	/**
	 * Attempts to get post stored by flickr_id
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
	 *
	 * @param  WP_Post|integer $post The post or its post ID.
	 * @return WP_Post               Updated content
	 */
	static function update_flickr_post( $post ) {
		if ( !is_object( $post ) ) {
			$post = get_post( $post );
		}
		$flickr_data = self::get_flickr_data( $post );
		$flickr_data = self::_update_data_from_flickr( $flickr_data );
		$post_data = self::_post_data_from_flickr_data( $flickr_data );
		$post_data['ID'] = $post->ID;

		// update post
		$post_id = wp_update_post( $post_data );
		self::_update_flickr_post_meta( $post->ID, $flickr_data );

		return get_post( $post->ID );
	}
	static private function _update_flickr_post_meta( $post_id, $flickr_data) {
		$self = self::get_instance();

		update_post_meta( $post_id, $self->post_metas['api_data'], $flickr_data );
		update_post_meta( $post_id, $self->post_metas['flickr_id'], $flickr_data['id'] );
		$img = self::_get_largest_image( $flickr_data );
		update_post_meta( $post_id, '_wp_attached_file', $img['source'] );
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
	public function get_flickr_link( $post ) {
		$flickr_data = self::get_flickr_data( $post );
		return $flickr_data['urls']['url'][0]['_content'];
	}
	// PRIVATE METHODS
	/**
	 * Generates a new post from the flickr data given
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
		$self == self::get_instance();
		$flickr_api = $self->flickr;

		$return = array();
		$params = array(
			'photo_id' => $flickr_id,
		);
		// https://www.flickr.com/services/api/flickr.photos.getInfo.html
		$result = $flickr_api->call('flickr.photos.getInfo', $params);
		if ( !empty($result['stat']) && ($result['stat'] == 'ok') ) {
			$return = $result['photo'];
		}
		// don't refresh if up-to-date
		if ( $last_updated && ( $return['dates']['lastupdate'] <= $last_updated ) ) {
			return array();
		}
		$result = $flickr_api->call('flickr.photos.getSizes', $params);
		if ( !empty($result['stat']) && ($result['stat'] == 'ok') ) {
			$return['sizes'] = $result['sizes'];
		}
		$result = $flickr_api->call('flickr.photos.getExif', $params);
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
			//'post_author'    => 0,//userid
			'post_date'      => $data['dates']['taken'], //TODO: consider varying date
			//'post_date_gmt'  => above in GMT
			'post_content'   => self::_img_from_flickr_data( $data ). '<br />' . $data['description']['_content'],
			'post_title'     => $data['title']['_content'],
			//'post_excerpt'   => //ALT TEXT
			'post_status'    => ( $data['visibility']['ispublic'] ) ? 'publish' : 'private', 
			'comment_status' => 'closed', //comments should be on flickr page only
			'ping_status'    => 'closed', //no pingbacks
			//'post_password'
			'post_name'      => self::_flickr_id_to_name( $data['id'] ), //post slug
			//'to_ping'
			//'pinged'
			//'post_modified'
			'post_modified_gmt' => gmdate( 'Y m d H:i:s', $data['dates']['lastupdate'] ),
			//'post_content_filtered' => let wordpress handle?
			//'post_parent'    => 0, // TODO
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
	 * Return original image if possible, if not return the largest one
	 * available. This takes advantage of the fact that the flickr API orders
	 * its sizes.
	 * 
	 * @param  int|array $flickr_data if integer, its the post_id of flickr media,
	 *                                else it's the flickr_data
	 * @return array     the sizes array element of the largest size
	 */
	static private function _get_largest_image( $flickr_data ) {
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
		if ( ( $flickr_data['rotation'] != 0 ) && ( $img['label'] == 'Original' ) ) {
			return $sizes[ $count_img_sizes-2 ];
		} else {
			return $img;
		}
	}
	/**
	 * Turn a flickr photo into an image tag.
	 *
	 * This adds the responsive images in too.
	 * 
	 * @deprecated Getting rid of this function
	 */
	static private function _img_from_flickr_data( $data, $default_size='Medium', $include_original=false ) {
		$sizes = $data['sizes']['size'];
		$src = '';
		$size_offset = 1000000;
		if ( !is_numeric( $default_size ) ) {
			switch ( strtolower($default_size) ) {
				case 'square': $default_size=75; break;
				case 'large square': $default_size=150;break;
				case 'thumbnail': $default_size=100; break;
				case 'small': $default_size=240; break;
				case 'small 320': $default_size=320; break;
				case 'medium': $default_size=500; break;
				case 'medium 640': $default_size=640; break;
				case 'medium 800': $default_size=800; break;
				case 'large': $default_size=1024; break;
				case 'large 1600': $default_size=1600; break;
				case 'large 2048': $default_size=2048; break;
				default: $default_size=500; break;
			}
		}
		foreach ( $sizes as $size ) {
			// handle original
			if ( $size['label'] == 'Original' ) {
				if ( $include_original ) {
					// TODO code to include the original if no rotation
				} else {
					// don't include original
					continue;
				}
			}
			// TODO: better handling of squares
			if ( ( $size['label'] == 'Square' ) || ( $size['label'] == 'Large Square' ) ) { continue; }
			$srcset[] = sprintf( '%s %dw', $size['source'], $size['width'] );

			$this_size = max( $size['width'], $size['height'] );
			$this_size_offset = abs( $this_size - $default_size ) / $this_size;
			if ( $this_size_offset < $size_offset ) {
				$size_offset = $this_size_offset;
				$src = $size['source'];
			}
		}
		return sprintf(
			'<img src="%s" srcset="%s" alt="%s" />',
			$src,
			implode( ', ', $srcset ),
			esc_attr( $data['title']['_content'] )
		);
	}
	//
	// CLASS FUNCTIONS
	// 
}
