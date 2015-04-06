<?php
namespace FML;
/**
 * A place to store constants (inspired by wp-flickr-embed)
 */
interface FMLConstants
{
    /**
     * Used for ids and also the text domain of plugin
     */
    const SLUG = 'flickr-media-library';

    /**
     * The default flickr API key of this plugin. This can be changed at runtime.
     */
    const _FLICKR_API_KEY = '0907a4f16d388c3f1520b76cc8b2f465';
    /**
     * The default flickr API secret of this plugin. This can be changed at runtime
     */
    const _FLICKR_SECRET = '02bf5327389eca85';

    const DISABLED_REASON_PHP_VERSION = 'php_version';

/*
    const OPTION_PHOTO_LINK = 'photo_link';
    const OPTION_LINK_REL = 'link_rel';
    const OPTION_LINK_CLASS = 'link_class';

    // WORDPRESS DOESN'T "NEED" CURL
    const DISABLED_REASON_CURL_FOPEN = 'curl_fopen';
    
    // SOMETHING TO DO WITH AUTH BECAUSE THE RETURN URL IS ON HOPEAGE
    const SIGN_URL_PARAM_NAME = '__wpfe_sign';
    const FLICKR_AUTH_URL_PARAM_NAME = '__wpfe_flickr';
    
    // THESE ARE IN DPZ FLICKR ALREADY, USE THOSE
    const FLICKR_USER_FULLNAME = 'flickr_user_fullname';
    const FLICKR_USER_NAME = 'flickr_username';
    const FLICKR_USER_NSID = 'flickr_user_id';
    const FLICKR_OAUTH_TOKEN = 'flickr_oauth_token';
    const FLICKR_OAUTH_TOKEN_SECRET = 'flickr_oauth_token_secret';

    */
}