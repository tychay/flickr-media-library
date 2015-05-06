<?php 
namespace FML;

/**
 * Flush rewrite rules on activation
 *
 * @see  https://codex.wordpress.org/Function_Reference/register_post_type#Flushing_Rewrite_on_Activation
 */

$fml = FML::get_instance($fml_plugin_file);

// First, we "add" the custom post type via the above written function.
// Note: "add" is written with quotes, as CPTs don't get added to the DB,
// They are only referenced in the post_type column with a post entry, 
// when you add a post of this CPT.
$fml->register_post_type();

// ATTENTION: This is *only* done during plugin activation hook in this example!
// You should *NEVER EVER* do this on every page load!!
flush_rewrite_rules();
