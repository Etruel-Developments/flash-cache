<?php
/**
* @package         etruel\Flash Cache
* @subpackage 	   Optimize Scripts
* @author          Sebastian Robles
* @author          Esteban Truelsegaard
* @copyright       Copyright (c) 2017
*/
// Exit if accessed directly
if (!defined('ABSPATH')) exit;


class flash_cache_optimize_scripts {
    public static $js_tags = array();

    
    public static function hooks() {
        add_filter('flash_cache_response_html', array(__CLASS__, 'init_process'), 2, 2);
    }
    
    public static function init_process($content, $url_to_cache) {
        if (empty(flash_cache_process::$advanced_settings)) {
            flash_cache_process::$advanced_settings = wp_parse_args(get_option('flash_cache_advanced_settings', array()), flash_cache_settings::default_advanced_options());
        }
        if (empty(flash_cache_process::$advanced_settings['optimize_scripts'])) {
            return $content;
        }
        $noptimize_scripts = apply_filters( 'flash_cache_scripts_noptimize', false, $content, $url_to_cache );
        if ( $noptimize_scripts ) {
            return $content;
        }
        
        // Get script files.
        if ( preg_match_all( '#<script.*</script>#Usmi', $content, $matches ) ) {
            foreach( $matches[0] as $tag ) {
                
                $should_aggregate = self::should_aggregate($tag);
                if ( ! $should_aggregate ) {
                    $tag = '';
                    continue;
                }
                
                if ( preg_match( '#<script[^>]*src=("|\')([^>]*)("|\')#Usmi', $tag, $source ) ) {
                    $url = current( explode( '?', $source[2], 2 ) );
                    self::$js_tags[] = flash_cache_get_path( $url );
                } else {
                        $tag = '';
                }
                
                
                
                $content = str_replace( $tag, '', $content );
                // $content .= print_r($tag, true);
            }
        }
        
        $content = self::end_process($content);
        return $content;  
    }
    
    public static function end_process($content) {

        $arrContextOptions=array(
            "ssl"=>array(
                    "verify_peer"=>false,
                    "verify_peer_name"=>false,
            ),
        );

        if (empty(flash_cache_process::$advanced_settings)) {
            flash_cache_process::$advanced_settings = wp_parse_args(get_option('flash_cache_advanced_settings', array()), flash_cache_settings::default_advanced_options());
        }
        if (is_null(flash_cache_process::$origin_url)) {
            flash_cache_process::$origin_url = get_site_url(null, '/');
        }
        
        $all_js_code = '';
        $basename_js = '';
        foreach (self::$js_tags as $path) {
            
                if (!empty($path)) {
                $code = file_get_contents( $path, false, stream_context_create($arrContextOptions) );
                $all_js_code .= $code;
                $basename_js = md5($basename_js . $path);
            }
            
        }
        if ( ! class_exists('JSMin') ) {
            require_once FLASH_CACHE_PLUGIN_DIR . 'includes/lib/jsmin.php';
        }
        $all_js_code = trim( JSMin::minify( $all_js_code ) );
        
        
        $cache_dir = flash_cache_get_home_path(). flash_cache_process::$advanced_settings['cache_dir'];
        $cache_path = $cache_dir.$_SERVER['SERVER_NAME'].'/scripts/';
        if (!file_exists($cache_path)) {
            @mkdir($cache_path, 0777, true);
        }
        
        $full_path_file_js = $cache_path.$basename_js.'.js';
        $url_file_js = str_replace(flash_cache_get_home_path(), flash_cache_process::$origin_url, $full_path_file_js);
        
        file_put_contents($full_path_file_js, $all_js_code);
        
        $content = self::insert_before_of($content, 'body', '<script type="text/javascript" src="'.$url_file_js .'"></script>');
        
        return $content;
    }
    public static function insert_before_of($content, $element = 'body', $code = '') {
        $content = str_replace('</'.$element.'>', $code .'</'.$element.'>', $content);
        return $content;
    }
    public static function should_aggregate($tag) {
        // We're only interested in the type attribute of the <script> tag itself, not any possible
        // inline code that might just contain the 'type=' string...
        $tag_parts = array();
        preg_match( '#<(script[^>]*)>#i', $tag, $tag_parts);
        $tag_without_contents = null;
        if ( ! empty( $tag_parts[1] ) ) {
            $tag_without_contents = $tag_parts[1];
        }

        $has_type = ( strpos( $tag_without_contents, 'type' ) !== false );

        $type_valid = false;
        if ( $has_type ) {
            $type_valid = (bool) preg_match( '/type\s*=\s*[\'"]?(?:text|application)\/(?:javascript|ecmascript)[\'"]?/i', $tag_without_contents );
        }

        $should_aggregate = false;
        if ( ! $has_type || $type_valid ) {
            $should_aggregate = true;
        }

        return $should_aggregate;
    }
    
}
    

flash_cache_optimize_scripts::hooks();