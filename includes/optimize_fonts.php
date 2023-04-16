<?php

/**
 * @package         etruel\Flash Cache
 * @subpackage 	    Optimize Fonts
 * @author          Gerardo Medina
 * @author          Esteban Truelsegaard
 * @copyright       Copyright (c) 2017
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

class flash_cache_optimize_fonts
{
    public static function hooks()
    {
        add_filter('flash_cache_save_fonts', array(__CLASS__, 'flash_cache_link_fonts_from_url'), 1, 2);
    }

    public static function flash_cache_link_fonts_from_url($url, $cache_dir)
    {

        // Get CSS content from URL
        $all_css_code = wp_safe_remote_get($url, array('sslverify' => false));
        if (is_wp_error($all_css_code)) {
            return '';
        }
        $all_css_code = wp_remote_retrieve_body($all_css_code);

        // Get all font URLs in content
        $font_urls = array();
        if (preg_match_all('/url\((["\']?)([^)]+)\1\)/i', $all_css_code, $matches)) {
            $font_urls = $matches[2];
        }

        // Create folder for fonts if it doesn't exist
        $font_folder_path = $cache_dir . flash_cache_get_server_name() . '/webfonts/';
        wp_mkdir_p($font_folder_path);

        $font_folder_path_images = $cache_dir . flash_cache_get_server_name() . '/images/';
        wp_mkdir_p($font_folder_path_images);

        // Replace font URLs with cached URLs
        foreach ($font_urls as $font_url) {
            // Get full path of font
            $font_path = self::get_full_font_path($font_url, $url);
            // Only continue if font is on the same WordPress instance
            $wordpress_host = parse_url(get_home_url(), PHP_URL_HOST);
            $font_host = parse_url($font_url, PHP_URL_HOST);
            if ($font_host === null || $font_host === $wordpress_host && $font_path) {
                // Font is on same WordPress instance
                $font_cached_path = '';
                if (preg_match('/\.(woff2|woff|woff2?|eot|ttf|otf|svg)(\?[\w-]*)?(#[\w-]*)?/i', $font_url)) {
                    $font_cached_path = $font_folder_path . basename($font_path);
                } elseif (preg_match('/\.(jpg|jpeg|png|gif|bmp|webp)([\?\#].*)?$/i', $font_url)) {
                    $font_cached_path = $font_folder_path_images . basename($font_path);
                }
                if ($font_cached_path !== '') {
                    if (!file_exists($font_cached_path)) {
                        // The file doesn't exist yet.
                        $file_content = wp_safe_remote_get($font_path, array('sslverify' => false));
                        if (is_wp_error($file_content)) {
                            continue;
                        }
                        $file_content = wp_remote_retrieve_body($file_content);
                        if (!empty($file_content)) {
                            file_put_contents($font_cached_path, $file_content);
                        }
                    }
                    // Replace URL from file in the css with the URL with the cached file 
                    $file_cached_url = str_replace(flash_cache_get_home_path(), get_home_url(null, '/'), $font_cached_path);
                    $all_css_code = str_replace($font_url, $file_cached_url, $all_css_code);
                }
            }
        }
        return $all_css_code;
    }

    public static function get_full_font_path($font_url, $url)
    {
        // Determine the full path of the font
        $font_url = str_replace(['"', '\''], '', $font_url);
        $base_url_parts = wp_parse_url($url);
        $font_url_parts = wp_parse_url($font_url);
        $font_path = $font_url_parts['path'];
        if (substr($font_path, 0, 1) === '/') {
            $full_font_path = $base_url_parts['scheme'] . '://' . $base_url_parts['host'] . $font_path;
        } else {
            $path_parts = pathinfo($base_url_parts['path']);
            $full_font_path = $base_url_parts['scheme'] . '://' . $base_url_parts['host'] . $path_parts['dirname'] . '/' . $font_path;
        }

        return $full_font_path;
    }
}

flash_cache_optimize_fonts::hooks();
