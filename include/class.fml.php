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
		);
		// settings and flickr are lazy loaded
	}
	/**
	 * Stuff to run on `plugins_loaded`
	 *
	 * - register `init` handler
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'init', array( $this, 'init' ) );
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
				'author', //can have author
				'thumbnail', // can have featured image?
				//'excerpt',
				//'trackbacks',
				'custom-fields',
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
				'slug' => $this->permalink_slug
				//'with_front'
				//'feeds'
				//'pages'
				////ep_mask
			),
			//'query_var'           => '', default query var
			//'can_export'          => true, // can be exported
		));
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
	public function __get($name)
	{
		switch( $name ) {
			case 'settings':
				if ( empty($this->_settings) ) {
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
				return get_option($this->permalink_slug_id, self::_DEFAULT_BASE);
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
	public function __set($name, $value)
	{
		switch( $name ) {
			case 'settings':
				trigger_error('Set plugin settings through update_settings()');
				break;
			case 'flickr':
				trigger_error(sprintf('Not allowed to externally set flickr API.', $name));
				break;
			case 'flickr_callback':
				$this->_flickr_callback = $value;
				break;
			case 'permalink_slug':
				update_option($this->permalink_slug_id, $value);
			default:
				trigger_error(sprintf('Property %s is not settable', $name));
			break;
		}
	}
	/**
	 * @var array Plugin blog options
	 */
	private $_settings = array();
	//
	// Properties
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
	// FLICKR MEDIA
	// 
	/**
	 * Adds an image from flickr into the flickr media library
	 * 
	 * @param  string $flickr_id the flickr ID of the image
	 * @return WP_Post|false     the post created (or false if not)
	 */
	public function add_flickr( $flickr_id ) {
		// Check to see it's not already added, if so return that
		if ( $post_already_added = $this->get_media_by_flickr_id( $flickr_id ) ) {
			// update post and return it
			return $this->_update_flickr_post( $post_already_added );
		}
		$data = $this->_get_data_from_flickr_id( $flickr_id );
		if ( empty( $data ) ) {
			return false;
		}
		$post_id = $this->_new_post_from_flickr_data( $data );
		return get_post($post_id);

	}
	/**
	 * Attempts to get post stored by flickr_id
	 * @param  string $flickr_id the flickr ID of the image
	 * @return WP_Post|false     the post found (or false if not)
	 * @todo   consider doing extra work
	 */
	public function get_media_by_flickr_id( $flickr_id ) {
		$post_already_added = get_posts( array(
			'name'           => $this->_flickr_id_to_name($flickr_id),
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
	 * Generates a new post from the flickr data given
	 * @param  [type] $data [description]
	 * @return [type]       [description]
	 */
	private function _new_post_from_flickr_data( $data ) {
		if ( empty($data ) ) { return 0; }
		$post_data = $this->_post_data_from_flickr_data( $data );
		$post_id = wp_insert_post( $post_data );

		// add grabbed information into post_meta
		// no need to update_post_meta as we know it can't exist
		add_post_meta( $post_id,  $this->_post_metas['api_data'], $data, true );

		return $post_id;
	}
	/**
	 *
	 * @param  WP_Post|integer $post The post or its post ID.
	 * @return WP_Post               Updated content
	 */
	private function _update_flickr_post( $post ) {
		if ( !is_object( $post ) ) {
			$post = get_post( $post );
		}
		$flickr_data = get_post_meta( $post->ID, $this->_post_metas['api_data'], true );
		$flickr_data = $this->_update_data_from_flickr( $flickr_data );
		$post_data = $this->_post_data_from_flickr_data( $flickr_data );
		$post_data['ID'] = $post->ID;

		// update post
		$post_id = wp_update_post( $post_data );
		update_post_meta( $post->ID, $this->_post_metas['api_data'], $flickr_data );

		return get_post( $post->ID );
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
	private function _get_data_from_flickr_id( $flickr_id, $last_updated=0 ) {
		$return = array();
		$params = array(
			'photo_id' => $flickr_id,
		);
		// https://www.flickr.com/services/api/flickr.photos.getInfo.html
		$result = $this->flickr->call('flickr.photos.getInfo', $params);
		if ( !empty($result['stat']) && ($result['stat'] == 'ok') ) {
			$return = $result['photo'];
		}
		// don't refresh if up-to-date
		if ( $last_updated && ( $return['dates']['lastupdate'] <= $last_updated ) ) {
			return array();
		}
		$result = $this->flickr->call('flickr.photos.getSizes', $params);
		if ( !empty($result['stat']) && ($result['stat'] == 'ok') ) {
			$return['sizes'] = $result['sizes'];
		}
		$result = $this->flickr->call('flickr.photos.getExif', $params);
		if ( !empty($result['stat']) && ($result['stat'] == 'ok') ) {
			$return = array_merge($return, $result['photo']);
		}
		//print_r($return);
		return $return;
	}
	private function _update_data_from_flickr( $data ) {
		$return = $this->_get_data_from_flickr_id( $data['id'], $data['dates']['lastupdate'] );
		if ( empty( $return ) ) {
			return $data;
		} else {
			return $return;
		}
	}
	/**
	 * Turns a flickr ID into a post slug
	 * 
	 * @param  string $flickr_id The number representing the flickr_id of the image
	 * @return string            post slug if it exists in the database
	 */
	private function _flickr_id_to_name( $flickr_id ) {
		return self::SLUG.'-'.$flickr_id;
	}
	/**
	 * Turn flickr API data into post data
	 * @param  srray $data  The photo data extracted from the flickr API
	 * @return array        The data suitable for creating a post (or updating if you add the ID)
	 * @todo  validate media is a photo
	 * @todo  vary date based on configuration: date uploaded, date taken, date posted?
	 */
	private function _post_data_from_flickr_data( $data ) {
		// generate list of tags
		$post_tags = [];
		if ( !empty( $data['tags']['tag'] ) ) {
			$tags = $data['tags']['tag'];
			foreach ( $tags as $idx=>$tag_data ) {
				$post_tags[] = $tag_data['raw'];
			}
		}
		// generate post array (from data)
		$post_data = array(
			'post_content'   => $this->_img_from_flickr_data( $data ). '<br />' . $data['description']['_content'],
			'post_name'      => $this->_flickr_id_to_name( $data['id'] ), //post slug
			'post_title'     => $data['title']['_content'],
			'post_status'    => ( $data['visibility']['ispublic'] ) ? 'publish' : 'private', 
			'post_type'      => self::POST_TYPE,
			//'post_author'    => 0,//userid
			//'ping_status'    => 'closed', //no pingbacks
			//'post_parent'    => 0, // TODO
			//'menu_order'     => (for page ordering)
			//'to_ping'
			//'pinged'
			//'post_password'
			//'guid' // Let wordpress handle
			//'post_content_filtered' => let wordpress handle?
			//'post_excerpt' 
			'post_date'      => $data['dates']['taken'],
			//'post_date_gmt' => above in GMT
			'comment_status' => 'closed',
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
	 * Turn a flickr photo into an image tag.
	 *
	 * This adds the responsive images in too.
	 */
	private function _img_from_flickr_data( $data, $default_size='Medium', $include_original=false ) {
		$sizes = $data['sizes']['size'];
		$src = '';
		$size_offset = 1000000;
		if ( !is_numeric( $default_size ) ) {
			switch ( strtolower($default_size) ) {
				case 'square': $default_size=75; break;
				case 'large square': $default_size=150;break;
				case 'thumbnail': $default_size=150; break;
				case 'small': $default_size=240; break;
				case 'small 320': $default_size=320; break;
				case 'medium': $default_size=500; break;
				case 'medium 640': $default_size=640; break;
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
