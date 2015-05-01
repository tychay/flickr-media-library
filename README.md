# Flickr Media Library #
**Contributors:** tychay  
**Donate link:** http://www.kiva.org/lender/tychay  
**Tags:** flickr, media library, media manager  
**Requires at least:** 3.5  
**Tested up to:** 4.2  
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

Because this plugin is in development. It works enough for me to use on my
personal blog. :-D

### Why write another Flickr plugin? ###

A survey showed me that there are three sorts of flickr plugins out there:

1. Hack TinyMCE to make it easier to embed a flickr image;
2. include an album as a gallery; or
3. publish an album or stream as a widget.

I wanted to something different. Basically, I want to use Flickr as both the
media library for my WordPress install as well the content delivery network to
offload both diskspace and bandwiddth away from my blog.

Even if I use an plugin that embeds a flickr image (which I don't because I
edit in markdown on a local application when I blog), it runs into a number of
issues including generating broken images if I replace the image and not playing
nice with captions and post thumbnails.

Using oEmbed URLs generate code that isn't responsive nor does it co-operate
with picturefill to be retina-friendly. Furthermore, there is information like
alt text and titles that goes unsupported. (Yes, alt and title attributes are
different, WordPress core attachment handling is actually wrong.)

The goal (not achieved) is for this plugin to allow you me transparently inject
flickr images as a first-class citizen in the media library.

### Why isn't the goal achieved? ###

There are three reasons.

The first is that the new media library code since version 2.9 is built on
backbone.js, requirejs, underscores.js, etc. The core developers' js-kung-fu
is stronger than mine (and there is almost no documentation of it). I've decided
to study under a different god in the javascript data-binding framework wars,
so until I figure out things out (or all the media-*.js code gets raptured away
because clearly (anything-other-than) backboneJS **is** the antichrist), I took
advantage (or emulated) the legacy thickbox code to achieve the funcionality
needed.

The second reason (and a showstopper) is that WordPress core is written with
tight integration between the media library and the native 'attachment' post
type. There are places where key image rendering code assumes in a hard-coded
manner that the post is an 'attachment' and not something that emulates an
attachment and quits before any hook gets called. This is an unintended coding
error probably because no core developer considered that you might want to
use media that isn't an attachment. If I have time, I will submit a core patch
to fix this, but it is a large patch and cannot use any existing hooks/filters
since current plugin developers may be making assumptions about them that would
break their plugins. :-(

The third reason is simply that the model WordPress has for media embedding
is wrong, wrong, wrong. In particular, WordPress, at various points, writes
the HTML asset in directly with no flexibility once it has been written beyond
some "rel" attributes that might-or-might-not point to the asset and size (it
does size in an inconsistent manner depending on if you inserted the image
via plugin or via the visual editor). This means that WordPress is not forward
thinking (for example, captions are not responsive, images are not
retina-ready, and attachments improperly interchange the title and alt
attributes throughout the code base). This plugin gets around that by choosing
strategic areas to insert a shortcode that is processed at runtime to add
correct functionality in. FML's shortcode implementation also has the benefit of
allowing you to easily "fall back" to legacy if this plugin is uninstalled.

### Why not just make your plugin write attachments ###

It's not the recommended method. When you have a new asset of a different type,
you are supposed to use a custom post type. The issue we're running into is
that WordPress assumes that all custom post types are customizations of native
"posts" or "pages", but not of native "attachments" (or menus or revisions).
There are other parts of the codebase (like the capabilities field) where core
has a misunderstanding of how post types interact with CMS intention. This is
because real support for this feature was added very late (with version 3.0)
and a lot of legacy got built up.

Furthermore inserting flickr as attachments (but without files) would cause a
lot of breakages in a manner that can't be fixed. For instance, WordPress
assumes it can use gd to resize images, but Flickr images are only available at
fixed sizes. Safe sites block acces to allow_url_fopen which would be needed to
get image metadata from Flickr's site (and only if "original" is accessible).

Furthermore, if you uninstall or deactivate this plugin, the attachment and
flickr emulated data would be forever intermingled in a manner that can't
operate correctly without the plugin installed (and modifying attachment
functions for compatibility)

### How can I pay you gobs of money? ###

Three years ago, I was an employee of Automattic so WordPress has already been
good to me. I don't need the money and my rate now (as an engineering executive)
would be too high to be practical to be paid for this hobby. My donate link is
for Kiva and I have this code mirrored on github where it is easy to fork and
keep up-to-date.

Instead, pay it forward by donating money to charity or contributing to
open-source projects like WordPress core or plugin development.

## TODO List ##

I currently keep this in a private taskpaper (it's too large to list out).
Sometime after release, I'll move this list to [github](https://github.com/tychay/one-click-child-theme/issues).

## ChangeLog ##

**Version 1.0**

* Initial release

**Version 0.1**

* In development.
