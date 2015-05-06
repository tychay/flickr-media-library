<?php
namespace FML;
/**
 * A place to store constants (inspired by wp-flickr-embed)
 */
interface FMLConstants
{
    /**
     * The version of the plugin
     */
    const VERSION = '0.1';
    /**
     * The version of picturefill.js bundled
     */
    const PICTUREFILL_JS_VERSION = '2.3.1';
    /**
     * Used for ids and also the text domain of plugin
     */
    const SLUG = 'flickr-media-library';
    /**
     * The name of the post type this registeres
     */
    const POST_TYPE = 'fml_photo';
    /**
     * Shortcode
     */
    const SHORTCODE = 'fmlmedia';

    /**
     * The default flickr API key of this plugin. This can be changed at runtime.
     */
    const _FLICKR_API_KEY = '0907a4f16d388c3f1520b76cc8b2f465';
    /**
     * The default flickr API secret of this plugin. This can be changed at runtime
     */
    const _FLICKR_SECRET = '02bf5327389eca85';
    /**
     * The default "base" (permalink slug) for the custom post type
     */
    const _DEFAULT_BASE = 'flickr_media';
    /**
     * Regex to extract flickr id from photo url
     * 
     * e.g. https://www.flickr.com/photos/tychay/16452349917
     * or https://www.flickr.com/photos/21673573@N06/16526529331/
     */
    const REGEX_FLICKR_PHOTO_URL = '!https?://(?:www.)?flickr.com/photos/[a-z0-9_@]+/(\d+)!i';
/*
    const OPTION_PHOTO_LINK = 'photo_link';
    const OPTION_LINK_REL = 'link_rel';
    const OPTION_LINK_CLASS = 'link_class';
    */
}