<?php
/*
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
/**
 * Check the WordPress & PHP version, deactivate plugin if too low, also refresh rewrite rules
 *
 * Called on plugin activation.
 * 
 * @return void
 */
function flickr_media_library_activate() {
	global $wp_version;

	if ( version_compare( $wp_version, '3.5', '<' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( printf(
			__( 'Sorry, but because Flickr Media Library uses the updated Media Manager, <a href="https://codex.wordpress.org/Version_3.5">WordPress 3.5</a> or later is required. Your version of WordPress, <strong>%s</strong>, does not meet required version of <strong>3.5</strong> to run properly.','flickr-media-library')
			. __(' The plugin has been deactivated. <a href="%s">Click here to return to the Dashboard</a>', 'flickr-media-library' ),
			$wp_version,
			admin_url()
		) );
	}

	if ( version_compare( PHP_VERSION, '5.3', '<') ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( printf(
			__( 'Sorry, but because Flickr Media Library uses PHP namespaces, PHP 5.3 or later is required. Your version of PHP, <strong>%s</strong>, does not meet required version of <strong>5.3</strong> to run properly. Note that <a href="https://wordpress.org/about/requirements/">PHP %s is recommended for use in WordPress</a>.','flickr-media-library')
			. __(' The plugin has been deactivated. <a href="%s">Click here to return to the Dashboard</a>', 'flickr-media-library' ),
			PHP_VERSION,
			'5.4',
			admin_url()
		) );
	}

	spl_autoload_register( 'flickr_media_library_class_loader' );
	$fml_plugin_file = __FILE__;
	include dirname(__FILE__).'/include/load.flush_rewrite.php';
}
register_activation_hook( __FILE__, 'flickr_media_library_activate' );

/**
 * Class loader for the FML namespace.
 *
 * Called by {@link flickr_media_library_bootstrap()}.
 * 
 * (Remember this has to be static method because they are used in autoloaders.)
 * 
 * @param  string The name of the class to load
 * @return void 
 */
/* static public */ function flickr_media_library_class_loader($class) {
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

/**
 * Code used to bootstrap plugin and bury namespace related stuff.
 *
 * (Triggered by plugins_loaded to ensure it is run AFTER an activation
 * hook is called.) The actual code execution is buried inside another
 * piece of code because namespacing is used in the library.
 * 
 * @return void
 */
function flickr_media_library_bootstrap() {
	// Register namespace autloader
	spl_autoload_register( 'flickr_media_library_class_loader' );

	$fml_plugin_file = __FILE__;
	// bury object creation in here because they are namespaced...
	require_once dirname(__FILE__).'/include/load.fml.php';
};


add_action('plugins_loaded','flickr_media_library_bootstrap');
