<?php 

namespace FML;

/**
 * Namespace for the Flickr Media Library
 * 
 */
class FML implements FMLConstants
{
	//
	// "STATIC" PROPERTIES
	//
	/**
	 * @var string Version number of plugin
	 */
	public $version = '0.1';
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

	//
	// CONSTRUCTORS AND DESTRUCTORS
	//
	/**
	 * Plugin Initialization
	 *
	 * Note that this plugin is not even initialized until WordPress init has
	 * been fired.
	 *
	 * This will set the static variables and register the custom post type
	 * used for storing flickr photos
	 *
	 * @param  string $pluginFile __FILE__ for the plugin file
	 */
	function __construct($pluginFile)
	{
		$this->_set_statics($pluginFile);
		// settings and flickr are lazy loaded
		register_post_type('fml_photo', array(
			'labels'      => array( //name of post type in plural and singualr form
				'name'          => _x('Flickr Media', 'plural', self::SLUG),
				'singular_name' => _x('Flickr Media', 'singular', self::SLUG),
			),
			'public'      => true, // display on admin screen and site content
			'has_archive' => true, // can have archive template
			'rewrite'     => array('slug' => $this->permalink_slug),
		));
	}
	/**
	 * Set up "static" properties
	 */
	private function _set_statics($pluginFile)
	{
		$this->plugin_dir = dirname($pluginFile);
		$this->template_dir = $this->plugin_dir . '/templates';
		$this->static_url = plugins_url('static',$pluginFile);
		$this->plugin_basename = plugin_basename($pluginFile);
		$this->_permalink_slug_id = str_replace('-','_',self::SLUG).'_base';
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
	// CLASS FUNCTIONS
	// 
}

/*
 * Remember, this code is executed in the _init_ hook of WordPress.
 *
 * Note that $fml_plugin_file is passed in from the bootstrap code
 */
// Initialize plugin
$fml = new FML($fml_plugin_file);
// Load admin page functions if in the admin page
if (is_admin()) {
	$fmla = new FMLAdmin($fml);
}