<?php

/**
 * @package         etruel\Flash Cache
 * @subpackage 	    Enviroment
 * @author          Sebastian Robles
 * @author          Esteban Truelsegaard
 * @copyright       Copyright (c) 2017
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;

if (!class_exists('flash_cache_enviroment')) :

	class flash_cache_enviroment {
        /**
         * Returns true if server is Apache.
         *
         * @static
         *
         * @return bool
         */
        public static function is_apache() {
            if ( empty( $_SERVER['SERVER_SOFTWARE'] ) ) {
                return true;
            }
            return isset( $_SERVER['SERVER_SOFTWARE'] ) && stristr( htmlspecialchars( stripslashes( $_SERVER['SERVER_SOFTWARE'] ) ), 'Apache' ) !== false; 
        }
        /**
         * Check whether server is LiteSpeed.
         *
         * @static
         *
         * @return bool
         */
        public static function is_litespeed() {
            return isset( $_SERVER['SERVER_SOFTWARE'] ) && stristr( htmlspecialchars( stripslashes( $_SERVER['SERVER_SOFTWARE'] ) ), 'LiteSpeed' ) !== false;
        }
        /**
         * Returns true if server is nginx.
         *
         * @static
         *
         * @return bool
         */
        public static function is_nginx() {
            return isset( $_SERVER['SERVER_SOFTWARE'] ) && stristr( htmlspecialchars( stripslashes( $_SERVER['SERVER_SOFTWARE'] ) ), 'nginx' ) !== false;
        }
    }

endif;