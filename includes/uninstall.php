<?php
/**
 * @package         etruel\Flash Cache
 * @subpackage 	   Settings
 * @author          Sebastian Robles
 * @author          Esteban Truelsegaard
 * @copyright       Copyright (c) 2017
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;

if (!class_exists('flash_cache_uninstall')) :

	class flash_cache_uninstall {

		/**
		 * Static function hooks
		 * @access public
		 * @return void
		 * @since 1.0.0
		 */
		public static function hooks() {
			
		
		}
        /**
		 * Static function uninstall
		 * @access public
		 * @return void
		 * @since 1.0.0
		 */
        public static function uninstall() {
            
        }
    }

	endif;
flash_cache_settings::hooks();
?>