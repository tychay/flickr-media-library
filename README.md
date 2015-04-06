# Flickr Media Library #
**Contributors:** tychay
**Donate link:** http://www.kiva.org/lender/tychay
**Tags:** flickr, media library, media manager
**Requires at least:** 3.5
**Tested up to:** 4.1
**Stable tag:** trunk

Extend WordPress's built-in media library with your Flickr account.
 
## Description ##

Please visit the [plugin homepage](http://terrychay.com/wordpress-plugins/flickr-media-library).

The goal here is to integrate Flickr into WordPress's media library in order
to keep and serve your image assets on Flickr instead of locally in from your
server filesystem.

Other plugins embed html necessary to include a Flickr image in a post, but
don't take advantage of WordPress's media library and thus have issues such
as: adding featured images (post thumbnails), tracking and updating images
where URL has changed, etc.

Because I don't want to be destructive except on user action, this will not
actually modify your existing WordPress media library, instead this tracks
the Flickr media using a WordPress custom post type that is then injected
into the media library.

## Installation ##

###Installing The Plugin###

**This requires PHP Version 5.3 or later!**
([Note that currently WordPress requires 5.2.4 or later and 5.4 is recommended.](https://wordpress.org/about/requirements/)).

Extract all files from the ZIP file, making sure to keep the file structure
intact, and then upload it to `/wp-content/plugins/`. Then just visit your
admin area and activate the plugin. That's it!

**See Also:** ["Installing Plugins" article on the WP Codex](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins)

###Using the plugin###

[TODO: Write up on how to use plugin.]

## Screenshots ##

![](screenshot-1.png)
TODO add a screenshot

## Frequently Asked Questions ##

### Why doesn't this work? ###

Because this plugin is in development.

### Why write another Flickr plugin? ###

## TODO List ##

- I'll keep this list somewhere else for instance https://github.com/tychay/one-click-child-theme/issues

## ChangeLog ##

**Version 1.0**

* Initial release

**Version 0.1**

* In development.
