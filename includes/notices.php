<?php
/**
* @package         etruel\Flash Cache
* @subpackage 	   Notices
* @author          Sebastian Robles
* @author          Esteban Truelsegaard
* @copyright       Copyright (c) 2017
*/
// Exit if accessed directly
if (!defined('ABSPATH'))  {
	exit;
}

if ( ! class_exists( 'flash_cache_notices' ) ) :
/**
* flash_cache_notices class
* @since 1.0.0
*/
class flash_cache_notices {
	
	public static $option_notices = 'flash_cache_notices';
	/**
	* Static function hooks
	* @access public
	* @return void
	* @since 1.0.0
	*/
	public static function hooks() {
		add_action( 'admin_notices', array(__CLASS__, 'show'));
	}
	/**
	* Static function add
	* @access public
	* @param $new_notice Array|String of a new notice to add.
	* @return void
	* @since 1.0.0
	*/
	public static function add($new_notice) {
		if(is_string($new_notice)) {
			$adm_notice['text'] = $new_notice;
		} else {
			$adm_notice['text'] = (!isset($new_notice['text'])) ? '' : $new_notice['text'];
		}
		$adm_notice['screen'] = (!isset($new_notice['screen'])) ? 'all' : $new_notice['screen']; 
		$adm_notice['error'] = (!isset($new_notice['error'])) ? false : $new_notice['error'];
		$adm_notice['below-h2'] = (!isset($new_notice['below-h2'])) ? true : $new_notice['below-h2'];
		$adm_notice['is-dismissible'] = (!isset($new_notice['is-dismissible'])) ? true : $new_notice['is-dismissible'];
		$adm_notice['user_ID'] = (!isset($new_notice['user_ID'])) ? get_current_user_id() : $new_notice['user_ID'];
		
		$notice = get_option(self::$option_notices, array());
		$notice[] = $adm_notice;
		update_option(self::$option_notices, $notice);
	}
	/**
	* Static function show
	* @access public
	* @return void
	* @since 1.0.0
	*/
	public static function show() {
		$screen = get_current_screen();
		$notice = get_option(self::$option_notices, array());

		$admin_message = '';
		if (!empty($notice)) {
			foreach($notice as $key => $mess) {
				if($mess['user_ID'] == get_current_user_id()) {
					if ($mess['screen'] == 'all' || $mess['screen'] == $screen->id) {
						$class = ($mess['error']) ? "notice notice-error" : "notice notice-success";
						$class .= ($mess['is-dismissible']) ? " is-dismissible" : "";
						$class .= ($mess['below-h2']) ? " below-h2" : "";
						$admin_message .= '<div id="message" class="'.$class.'"><p>'.$mess['text'].'</p></div>';
						unset( $notice[$key] );
					}	
				}
			}
			update_option(self::$option_notices, $notice);
		}
		
		echo $admin_message;
	}
	
}

endif; // End if class_exists check
flash_cache_notices::hooks();

?>
