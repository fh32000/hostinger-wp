<?php

if (!defined('ABSPATH')) exit;

class WLH_Cache
{
    public static function flush()
    {
        if (function_exists('do_action')) {
            do_action('litespeed_purge_all');
            do_action('cache_enabler_clear_complete_cache');
            do_action('swift_performance_clear_all_cache');
            do_action('breeze_clear_all_cache');
        }
        if (function_exists('rocket_clean_domain')) {
            @rocket_clean_domain();
        }
        if (function_exists('wp_cache_clear_cache')) {
            @wp_cache_clear_cache();
        }
        if (function_exists('w3tc_flush_all')) {
            @w3tc_flush_all();
        }
        if (function_exists('sg_cachepress_purge_cache')) {
            @sg_cachepress_purge_cache();
        }
        if (function_exists('wpfc_clear_all_cache')) {
            @wpfc_clear_all_cache(true);
        }
        if (function_exists('wp_cache_flush')) {
            @wp_cache_flush();
        }
    }
}
