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
	 * Needed to inject into link in plugins directory.
	 * @var string Cache the plugin basename
	 */
	public $plugin_basename;
	/**
	 * access through _get() to make it read-only
	 * 
	 * @var string the option name for the permalink base
	 */
	private $_permalink_slug_id;
	/**
	 * @var array store the post_meta names of FML-specific metadata. This is
	 * accessible publicly (but not writeable).
	 */
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
	 * - register prepend_media to the_content to trigger shortcode processing on attachment page
	 * - add shortcode handler
	 * - register filter_image_downsize to add fmlmedia image_downsize() support
	 * - register filter_get_attached_file to make get_attached_file() return
	 *   proper url for flickr media
	 * - register filter_wp_get_attachment_metadata to inject metadata results
	 *   without having to clutter up post_meta with more stuff
	 * - register filter media_send_to_editor to wrap shortcode around fmlmedia
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'init', array( $this, 'init' ) );

		// run [fmlmedia] shortcode before wpautop (and other shortcodes)
		add_filter( 'the_content', array( $this, 'prepend_media') ); //can run anytime
		add_filter( 'the_content', array( $this, 'run_shortcode' ), 8);
		// placeholder for strip_shortcodes() to work
		//add_shortcode( self::SHORTCODE, array( $this, 'shortcode') );
		add_shortcode( self::SHORTCODE, '__return_false' );

		add_filter( 'image_downsize', array( $this, 'filter_image_downsize'), 10, 3 );
		add_filter( 'get_attached_file', array( get_class($this), 'filter_get_attached_file'), 10, 2 );
		add_filter( 'wp_get_attachment_metadata', array( $this, 'filter_wp_get_attachment_metadata'), 10, 2 );
		// TODO make this optional depending on the style of handling shortcode injection
		add_filter( 'media_send_to_editor', array( $this, 'filter_media_send_to_editor'), 10, 3);
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
	}
	/**
	 * Actually register the flickr media custom post type
	 *
	 * This is separate from init because it can be called from the activation hook
	 * @return void
	 */
	public function register_post_type() {
		// https://codex.wordpress.org/Function_Reference/register_post_type
		register_post_type(self::POST_TYPE, array(
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
				//'post-formats',    // TODO
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
	// PROPERTIES: Settings
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
	// PROPERTES: Flickr
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
	public function prepend_media( $content ) {
		$post = get_post();
		if ( empty($post->post_type) || $post->post_type != self::POST_TYPE ) { return $content; }

		// show the medium sized image representation of the attachment if available, and link to the raw file
		//$p .= wp_get_attachment_link(0, 'medium', false);
		/**
		 * Filter shortcode content for processing in prepend_media()
		 *
		 * Use this to inject parameters not handled by fml_prepend_media_shortcode_attrs.
		 * @since 1.0
		 * @see prepend_media()
		 * @param string $content shortcode content
		 */
		$shortcode_content = apply_filters('fml_prepend_media_shortcode_content', '' );
		/**
		 * Filter shortcode attributes for shortcode processing in prepend_media()
		 *
		 * @since 1.0
		 * @see prepend_media()
		 * @param array $attrs shortcode attributes
		 */
		$shortcode_attrs = apply_filters('fml_prepend_media_shortcode_attrs', array(
			'id'   => $post->ID,
			'size' => 'Medium',
			'link' => 'flickr',
		) );
		$p = $this->shortcode( $shortcode_attrs, $shortcode_content );
		// append caption if available
		if ( $caption_text = $post->post_excerpt ) {
			list( $img_src, $width, $height ) = image_downsize( $post->ID, $shortcode_attrs['img_size'] );
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
				case 'link': 
				case 'url': 
				break;
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
		return sprintf( '[%1$s%2$s]%3$s[/%1$s]', self::SHORTCODE, $attr_string, $html );
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
	 * @see  FML\FML::run_shortcode() Process handler to run shortcode earlier
	 * @param  array  $atts    raw shortcode attributes
	 *   - id: The post ID of the flickr Media
	 *   - flickr_id: if ID is not provided, this is the flickr ID of the image
	 *   - alt: The alt tag to use in the image
	 *   - title: image title
	 *   - size: the size of the image to use
	 *   - align: alignment (not really used except in class names)
	 *   - link: the link type (or 'custom')
	 *   - url: the url to link (if link is custom)
	 * @param  string $content content shortcode wrapss
	 * @return string          HTML output corrected to embed FML asset correctly
	 * @todo   inject plugin defaults for attributes
	 * @todo  add setting for extraacting content to flickrid
	 * @todo  add option for auto-adding missing media
	 */
	public function shortcode( $raw_atts, $content='' ) {
		// 1. Process shortcode attributes against defaults
		$atts = shortcode_atts( array(
			'id'        => 0,
			'flickr_id' => 0,
			'alt'       => '',
			'title'     => '',
			'size'      => 'Medium', // transformed from image-size
			'align'     => '',       // editor default may be none, but ours is
			                         // no attribute/class
			'link'      => 'flickr', // because of TOS
			'url'       => '',
			//'post_excerpt' => '', // caption
		), $raw_atts, 'fmlmedia' );
		// 2. Verify post is FML media first.
		//    To do this, we must have either the id or flickr_id set, prefering
		//    id, optionally this can extract or generate
		if ( $atts['id'] == 0 && $atts['flickr_id'] == 0 ) {
			if (true) {
				$atts['flickr_id'] = self::extract_flickr_id( $content );
			}
		}
		if ( $atts['id'] == 0 && $atts['flickr_id'] == 0 ) {
			return $content;
		}
		if ( $atts['id'] == 0 ) {
			$post = self::get_media_by_flickr_id( $atts['flickr_id'] );
			if ( !$post && true ) {
				// generate FML media automatically
				$post = self::create_media_from_flickr_id( $atts['flickr_id'] )	;
			}
		} else {
			$post = get_post( $atts['id'] );
		}
		if ( !$post ) {
			return $content;
		}
		// 3. Process other attributes
		//    Inject title if missing/not provided
		if ( !$atts['title'] ) {
			$atts['title'] = trim( $post->post_title );
		}
		//    Inject alt if missing/not provided
		if ( !$atts['alt'] ) {
			$atts['alt'] = trim( get_post_meta( $post->ID, '_wp_attachment_image_alt', true ) );
		}
		//    Find url to link if any
		$rel = '';
		if ( $atts['link'] ) {
			switch ( $atts['link'] ) {
				case 'file':
				//$url = get_attached_file( $id );
				//Flickr community guidelines: link the download page
				$atts['url'] = self::get_flickr_link( $post ).'sizes/';
				$rel = 'flickr';
				break;
				case 'post':
				$atts['url'] = get_permalink( $post );
				$rel = 'attachment-flickr wp-att-'.$post->ID;
				break;
				case 'flickr':
				$atts['url'] = self::get_flickr_link( $post );
				$rel = 'flickr';
				break;
				case 'custom': //if 'custom' with no URL, then it means flickr link
				if ( !$atts['url'] ) {
					$atts['url'] = self::get_flickr_link( $post );
					//$atts['link'] = 'flickr';
					$rel = 'flickr';
				}
				default: // unknown
				$atts['link'] = '';
			}
		}
		// 3. Run attachment processing on the code.
		$id    = $post->ID;
		$alt   = $atts['alt'];
		$title = $atts['title'];
		$align = $atts['align'];
		$size  = $atts['size'];
		//     This is basically the corrected get_image_send_to_editor()
		//     without any caption content (which would trigger caption handling)
		//     Remember the real get_image_send_to_editor and it's hooks are
		//     not available.
		/* // DO NOT RUN IT THIS WAY: Reason: this does not trigger wp_get_attachment_image_attributes which is needed for things like post thumbnails, etc.
		$html = get_image_tag($id, $alt, $title, $align, $size);
		*/
		//    Emulate the output of get_image_tag() in wp_get_attachment_image()
		// TODO: Add support for configuring which of these classes get written
		// by default (and how). For instance, size-Large instead of attachment-Large
		// or map sizes to internal strings.
		$iatts = array(
			'class' => 'attachment-'.$size.' wp-image-'.$id,
		);
		// This seems weird but is correct (WordPress is wrong). Title should be
		// the title of the image, and alt should be a description provided for
		// accessibility (screen readers). You can/should have both.
		if ( $alt )   { $iatts['alt']   = $alt; }
		if ( $title ) { $iatts['title'] = $title; }
		if ( $align ) { $iatts['class'] = 'align'.$align.' '.$iatts['class']; }
		$html = wp_get_attachment_image( $id, $size, false, $iatts);
		// TODO: Add code to strip out image_hwstring if running with scissors (picturefill.wp)
		if ( $atts['url'] ) {
			$html = sprintf(
				'<a href="%s"%s>%s</a>',
				esc_attr( $atts['url'] ),
				( $rel ) ? ' rel="'.esc_attr($rel).'"' : '',
				$html
			);
		}
		if ( !$content ) {
			return $html;
		}

		// 4. If there is content, merge unique things from that into our 
		//    processed output.
		//    a. run regex processing on output
		if ( $atts['url'] ) {
			$a_gen = self::extract_html_attributes( $html );
			$img_gen = self::extract_html_attributes( $a_gen['content'] );
		} else {
			$a_gen = false;
			$img_gen = self::extract_html_attributes( $content );
		}
		//    b. run regex processing on content (save the match). Must be
		//       and <img> or a <a><img></a>.
		/* if ( !preg_match( '!<([a-z0-9\-._:]+).*?>[\s\S]*?(<\/\1>)|\s*\/?>!im', $content, $matches ) ) { */
		if ( !preg_match( '!(<a\s[^>]*>)?<img\s[^>]*>(</a>)?!im', $content, $matches ) ) {
			// no html in content so just prepend shortcode injection
			return $html.$content;
		}
		$needle = $matches[0]; //save for later for insertion
		$extract = self::extract_html_attributes( $needle );
		if ( $extract['element'] == 'a' ) {
			$img = self::extract_html_attributes ( $extract['content'] );
			if ( $img['element'] != 'img' ) {
				// bare a tag in content? Prepend injection
				return  $html.$content;
			}
			$a = $extract;
		} elseif ( $extract['element'] == 'img' ) {
			$a = false;
			$img = $extract;
		} else {
			// first tag is not media, just prepend shortcode injection
			return $html.$content;
		}
		//    c. iterate through img tag of content injecting the generated attrs
		foreach ( $img_gen['attributes'] as $key=>$value ) {
			// special case, class should be merged, not overwritten
			if ( $key == 'class' ) {
				$img['attributes'][$key] = implode( ' ', array_unique( array_merge(
					explode( ' ', $value ),
					explode( ' ', $img['attributes'][$key] )
				) ) );
				continue;
			}
			// overwrite
			$img['attributes'][$key] = $value;
		}
		$replace = self::build_html_attributes( $img );

		//    d. iterate through a tag (if so) injecting that stuff inside
		if ( $a && $a_gen ) {
			// nothing special, just merge
			$a['attributes'] = array_merge( $a['attributes'], $a_gen['attributes'] );
			// and then insert img content above
			$a['content'] = $replace;
			$replace = self::build_html_attributes( $a );
		} elseif ( $a ) {
			// just the a tag in content
			$a['content'] = $replace;
			$replace = self::build_html_attributes( $a );
		} elseif ( $a_gen ) {
			// just the a tag in generated injection
			$a_gen['content'] = $replace;
			$replace = self::build_html_attributes( $a_gen );
		}// else $replace has been set properly if just img tag
		
		//    e. restore and return
		$start = strpos( $content, $needle );
		$end   = $start + strlen( $needle );
		return substr( $content, 0, $start ) . $replace . substr( $content, $end );
		/*
		$return = substr( $content, 0, $start ) . $replace . substr( $content, $end );

		
		ob_start();
		echo $return;
		var_dump($atts,$html,$content,$return);die;
		$return = ob_get_clean();
		return $return;	
		/* */
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
	    if( ! preg_match('#^(<)([a-z0-9\-._:]+)((\s)+(.*?))?((>)([\s\S]*?)((<)\/\2(>))|(\s)*\/?(>))$#im', $input, $matches)) return false;
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
	//
	// ATTACHEMENT EMULATIONS
	// 
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
		global $_wp_additional_image_sizes;

		$post = get_post( $id );
		// Only operate on flickr media images
		if ( $post->post_type != self::POST_TYPE ) { return $downsize; }

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
			);
		}
		$metadata['sizes']      = $sizes;
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
		
		// FML-specific
		$response['flickrId'] = get_post_meta( $post->ID, $self->post_metas['flickr_id'], true );
		$response['_flickrData'] = $flickr_data;
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
	        'focal_length'      => ( empty( $exif['Focal Length'] ) ) ? 0 : $exif['Focal Length']['raw'],
	        'iso'               => ( empty( $exif['ISO Speed'] ) ) ? 0 : intval($exif['ISO Speed']['raw']),
	        'shutter_speed'     => ( empty( $exif['Exposure'] ) ) ? 0 : wp_exif_frac2dec( $exif['Exposure']['raw'] ),
	        'title'             => '',
	        'orientation'       => ( empty( $exif['Orientation'] ) ) ? 0 : $exif['Orientation']['raw'],
	        '_flickr'           => $exif
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
	    //    focal_length_35 = Focal Length (35mm format)
	    if ( !empty( $exif['Focal Length (35mm format)'] ) ) {
	    	$meta['focal_length_35'] = $exif['Focal Length (35mm format)']['raw'];
	    }
		//     lens = Lens Make
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
	    // GPS Altitiude
		// GPS Altitude Ref
		// GPS Date Stamp
		// GPS Speed
		// GPS SPeed Ref
		// GPS TIme Stamp
	    // Aperture clean
	    // Metering Mode
	    // ExposureMode
	    // Exposure Program
	    // Exposure Bias Clean
	    // Exposure clean
	    // City, Provice- State, Country- Primary Location Name

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
		self::_update_flickr_post_meta( $post->ID, $flickr_data );

		return get_post( $post->ID );
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
		update_post_meta( $post_id, $self->post_metas['api_data'], $flickr_data );
		update_post_meta( $post_id, $self->post_metas['flickr_id'], $flickr_data['id'] );

		// emulated post metas
		$img = self::_get_largest_image( $flickr_data );
		update_post_meta( $post_id, '_wp_attached_file', $img['source'] );
		// Let's store these because it is mildly "expensive" to compute
		$meta = array(
			'image_meta' => self::_wp_read_image_metadata( $flickr_data )
		);
		update_post_meta( $post_id, '_wp_attachment_metadata', $meta );
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
		$self = self::get_instance();
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
			//'post_content'   => self::_img_from_flickr_data( $data ). '<br />' . $data['description']['_content'],
			'post_content'   => $data['description']['_content'],
			'post_title'     => $data['title']['_content'],
			//'post_excerpt'   => // CAPTION
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
	 * Return original image if possible, if not practical (rotated, a tiff),
	 * return the largest one available. This takes advantage of the fact that
	 * the flickr API orders its sizes.
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
	/**
	 * @deprecated
	 * @param  [type] $size_string [description]
	 * @return [type]              [description]
	 */
	static private function _get_width_from_flickr_size( $size_string ) {
		switch ( strtolower($size_string) ) {
			case 'square': return 75;
			case 'large square': return 150;
			case 'thumbnail': return 100;
			case 'small': return 240;
			case 'small 320': return 320;
			case 'medium': return 500;
			case 'medium 640': return 640;
			case 'medium 800': return 800;
			case 'large': return 1024;
			case 'large 1600': return 1600;
			case 'large 2048': return 2048;
			default: return 500;
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
			$default_size = self::_get_width_from_flickr_size( $dwefault_size );
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
