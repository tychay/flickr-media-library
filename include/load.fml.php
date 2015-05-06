<?php 
namespace FML;

/**
 * Code to initialize and run everything.
 * 
 * Remember, this code is executed in the `plugins_loaded` hook of WordPress
 * in order to deal with lack of namespace support in older versions of PHP.
 * Normally run() this would be in plugins_loaded and constructor would be
 * in the plugin.php file.
 *
 * Note that `$fml_plugin_file` is "passed in" from the bootstrap code and
 * is the absolute path to the plugin php file.
 */

$fml = FML::get_instance($fml_plugin_file);
$fml->run();
// Load admin page functions if in the admin page
if (is_admin()) {
	$fmla = new FMLAdmin($fml);
	$fmla->run();
}
include_once dirname( $fml_plugin_file ) . '/include/functions.fml.php';