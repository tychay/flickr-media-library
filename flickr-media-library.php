<?php /*
**************************************************************************

Plugin Name:  Flickr Media Library
Plugin URI:   http://terrychay.com/wordpress-plugins/flickr-media-library
Version:      0.1
Description:  Extend WordPress's built-in media library with your Flickr account.
Author:       tychay
Author URI:   http://terrychay.com/
License:	  GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  flickr-media-library
//Domain Path:

**************************************************************************/
/*  Copyright 2015  terry chay  (email : tychay@php.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

namespace FML;

/**
 * The autoloader hasn't laoaded yet.
 */
require_once dirname(__FILE__).'/include/class.fmlconstants.php';
/**
 * Namespace for the Flickr Media Library
 * 
 * @todo disable if PHP version_compare is not 5.3 or later
 * @todo  allow you to customize flickr api key and secret
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
	//
	// CONSTRUCTORS AND DESTRUCTORS
	//
	/**
	 * Plugin Initialization
	 */
	function __construct()
	{
		$this->_set_statics();
		// settings and flickr are lazy loaded
	}
	/**
	 * Set up "static" properties
	 */
	private function _set_statics()
	{
		$this->plugin_dir = dirname(__FILE__);
		$this->template_dir = $this->plugin_dir . '/templates';
		$this->plugin_basename = plugin_basename(__FILE__);
		$this->static_url = plugins_url('static',__FILE__);
	}
	//
	// OVERLOAD PROPERTIES
	// 
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
		}
	}
	/**
	 * __setter() override
	 *
	 * @todo  probably need to throw instead of return errors
	 * @param string $name the property to set
	 * @param mixed $value the value to set it to.
	 */
	public function __set($name, $value)
	{
		switch( $name ) {
			case 'settings':
				return WP_Error(
					self::SLUG.'-incorrect-setting',
					'Set plugin settings through update_settings()'
				 );
			case 'flickr':
				return WP_Error(
					self::SLUG.'-api-locked',
					'Not allowed to externally set flickr API'
				 );
			case 'flickr_callback':
				$this->_flickr_callback = $value;
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
	 * @return null
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
        // are we authenticated with flickr? if so then set up auth param
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
	/**
	 * Class loader for the FML namespace.
	 *
	 * Remember these have to be static classes because they are used in autoloaders
	 * @param string The name of the class to load
	 */
	static public function class_loader($class) {
		static $_plugin_dir = '';
		if (empty($_plugin_dir)) {
			$_plugin_dir = dirname(__FILE__);
		}
		if (strpos($class, 'FML\\') !== 0) {
			return;
		}
		$parts = explode('\\', $class);
		include $_plugin_dir.'/include/class.'.strtolower(end($parts)).'.php';
	}
}

// Register namespace autloader
spl_autoload_register(__NAMESPACE__.'\FML::class_loader');
// Initialize plugin
$fml = new FML();
// Load admin page functions if in the admin page
if (is_admin()) {
	$fmla = new FMLAdmin($fml);
}
