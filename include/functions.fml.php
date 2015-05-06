<?php
/**
 * Functions to access things in Flickr Media Library.
 * 
 * Note that the namespace is not in here so that the function name can be
 * called in the WordPress namespace (\) and they will make calls into the
 * FML namespace.
 *
 * Remember these functions will not be available until after plugins_loaded!
 * (In order to protect namespacing), so register on init or plugins_loaded:6+
 * (Luckily theme developers don't load functions.php until before
 * after_setup_theme which is well after plugins_loaded)
 */
/**
 * Emulate picturefill_wp_register_sizes()
 *
 * This will make calls into the emulated if it exists. The reason we cannot
 * just use picturefill_wp_register_sizes() is because picturefill_wp saves
 * stuff to an internal application model that is not accessible outside
 * picturefill_wp! :-(
 * 
 * @param  string $handle       name of the class "sizes-$handle" to attach to
 * @param  string $sizes_string the sizes attribute to write out
 * @param  mixed  $attach_to    single image size (or list of images) to auto apply to
 * @return void
 */
function fml_register_sizes( $handle, $sizes_string, $attach_to='' ) {
    $fml = \FML\FML::get_instance();
    $fml->register_sizes( $handle, $sizes_string, $attach_to );

    if ( function_exists( 'picturefill_wp_register_sizes') ) {
        picturefill_wp_register_sizes( $handle, $sizes_string, $attach_to );
    }
}