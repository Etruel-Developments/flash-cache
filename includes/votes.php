<?php

/**
 * @package         etruel\Flash Cache
 * @subpackage 	    Votes
 * @author          Esteban Truelsegaard
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;

/**
 * "I'm interested" votes for what is being evaluated, sent to flashcache.net only when an
 * administrator clicks the button: the choice, whether the site runs that server, the plugin and
 * PHP versions, the site language and a random anonymous ID. No email unless it is offered with
 * its own consent, and no site address.
 */
class flash_cache_votes {

	const FEATURE	 = 'lsws-enterprise';
	const NONCE		 = 'flash_cache_votes';
	const OPTION	 = 'flash_cache_votes';

	public static function hooks() {
		if (!is_admin()) {
			return;
		}
		add_action('wp_ajax_flash_cache_vote', array(__CLASS__, 'ajax'));
		add_action('admin_notices', array(__CLASS__, 'notice'));
	}

	public static function endpoint() {
		if (defined('FLASH_CACHE_VOTES_ENDPOINT')) {
			return FLASH_CACHE_VOTES_ENDPOINT;
		}
		return apply_filters('flash_cache_votes_endpoint', 'https://flashcache.net/wp-json/flashcache/v1/vote');
	}

	/**
	 * Whether this site runs LiteSpeed Enterprise, which OpenLiteSpeed reports as well.
	 */
	public static function detected() {
		return flash_cache_enviroment::is_litespeed() && !flash_cache_enviroment::is_openlitespeed();
	}

	public static function state() {
		$state = get_option(self::OPTION, array());
		return is_array($state) ? $state : array();
	}

	private static function save_state($state) {
		update_option(self::OPTION, $state, false);
	}

	private static function site_id() {
		$id = get_option('flash_cache_site_id');
		if (!is_string($id) || !preg_match('/^[a-f0-9]{64}$/', $id)) {
			$id = hash('sha256', wp_generate_uuid4() . wp_generate_password(32, true, true));
			update_option('flash_cache_site_id', $id, false);
		}
		return $id;
	}

	private static function post($extra) {
		$body = array_merge(array(
			'feature'	 => self::FEATURE,
			'site_id'	 => self::site_id(),
			'detected'	 => self::detected() ? 1 : 0,
			'version'	 => FLASH_CACHE_VERSION,
			'php'		 => PHP_VERSION,
			'locale'	 => get_locale(),
		), $extra);
		$response = wp_remote_post(self::endpoint(), array('timeout' => 10, 'body' => $body));
		if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
			return false;
		}
		$data = json_decode(wp_remote_retrieve_body($response), true);
		return ( is_array($data) && !empty($data['ok']) ) ? $data : false;
	}

	private static function total_label($total) {
		if ($total < 1) {
			return '';
		}
		/* translators: %d: number of people who asked for it. */
		return sprintf(_n('%d person has asked for it.', '%d people have asked for it.', (int) $total, 'flash-cache'), (int) $total);
	}

	public static function ajax() {
		if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE, 'nonce', false)) {
			wp_send_json_error(array('message' => __('You are not allowed to do this.', 'flash-cache')), 403);
		}
		$do		 = isset($_POST['do']) ? sanitize_key(wp_unslash($_POST['do'])) : '';
		$state	 = self::state();
		if ($do === 'dismiss') {
			$state['dismissed'] = true;
			self::save_state($state);
			wp_send_json_success();
		}
		$failed = array('message' => __('Could not reach flashcache.net. Try again in a moment.', 'flash-cache'));
		if ($do === 'vote') {
			$data = self::post(array());
			if (!$data) {
				wp_send_json_error($failed);
			}
			$state['voted']	 = time();
			$state['total']	 = isset($data['total']) ? (int) $data['total'] : 0;
			self::save_state($state);
			wp_send_json_success(array('label' => self::total_label($state['total'])));
		}
		if ($do === 'details' && !empty($state['voted'])) {
			$note	 = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';
			$email	 = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
			$consent = !empty($_POST['consent']) && is_email($email);
			if (!self::post(array('note' => substr($note, 0, 500), 'email' => $consent ? $email : '', 'consent' => $consent ? 1 : 0))) {
				wp_send_json_error($failed);
			}
			$state['detailed'] = true;
			self::save_state($state);
			wp_send_json_success();
		}
		wp_send_json_error(array('message' => __('Invalid request.', 'flash-cache')), 400);
	}

	/**
	 * On the plugin's screens, for the sites that really run the server and have not answered.
	 */
	public static function notice() {
		$screen = get_current_screen();
		if (!$screen || strpos($screen->id, 'flash_cache') === false || $screen->id === 'flash-cache_page_flash_cache_about') {
			return;
		}
		$state = self::state();
		if (!self::detected() || !empty($state['voted']) || !empty($state['dismissed']) || !current_user_can('manage_options')) {
			return;
		}
		echo '<div class="notice notice-info is-dismissible flash-cache-vote-notice"><p><strong>' . esc_html__('Using LiteSpeed Enterprise?', 'flash-cache') . '</strong> '
			. esc_html__('We are evaluating a faster mode for it. Tell us if you would use it.', 'flash-cache')
			. ' <a href="' . esc_url(admin_url('admin.php?page=flash_cache_about#servers')) . '">' . esc_html__('Tell us', 'flash-cache') . '</a></p></div>';
		echo '<script>jQuery(function($){$(document).on("click",".flash-cache-vote-notice .notice-dismiss",function(){$.post(ajaxurl,{action:"flash_cache_vote","do":"dismiss",nonce:' . wp_json_encode(wp_create_nonce(self::NONCE)) . '});});});</script>';
	}

	/**
	 * The card of the Servers tab of About.
	 */
	public static function render_card() {
		$state	 = self::state();
		$voted	 = !empty($state['voted']);
		?>
		<div class="fc-vote" data-voted="<?php echo $voted ? '1' : '0'; ?>">
			<h3><?php esc_html_e('Are you using or want to use LiteSpeed Enterprise?', 'flash-cache'); ?></h3>
			<p><?php esc_html_e('We are evaluating a faster mode for LiteSpeed Enterprise. If enough people ask for it, we will build it. Press the button to let us know you are interested and add one more to the list.', 'flash-cache'); ?></p>
			<div class="fc-vote-ask"<?php echo $voted ? ' hidden' : ''; ?>>
				<button type="button" class="fc-btn solid" data-vote="vote"><?php esc_html_e("I'm interested in LiteSpeed Enterprise", 'flash-cache'); ?></button>
				<small><?php esc_html_e('It sends your choice, whether this site runs LiteSpeed Enterprise, the Flash Cache and PHP versions, the site language and a random anonymous ID to flashcache.net: no email and no site address. One vote per site.', 'flash-cache'); ?></small>
			</div>
			<div class="fc-vote-thanks"<?php echo $voted ? '' : ' hidden'; ?>>
				<p><strong><?php esc_html_e("Thanks, you're on the list.", 'flash-cache'); ?></strong> <span class="fc-vote-total"><?php
					echo esc_html(self::total_label(isset($state['total']) ? (int) $state['total'] : 0));
				?></span></p>
				<div class="fc-vote-details"<?php echo !empty($state['detailed']) ? ' hidden' : ''; ?>>
					<label><?php esc_html_e('How would you use it? (optional)', 'flash-cache'); ?>
						<textarea rows="2" maxlength="500" class="fc-vote-note"></textarea></label>
					<label class="fc-vote-consent"><input type="checkbox" class="fc-vote-consent-box"> <?php esc_html_e('Email me when it is ready. I agree to share my email address for that.', 'flash-cache'); ?></label>
					<input type="email" class="fc-vote-email" placeholder="<?php esc_attr_e('Your email', 'flash-cache'); ?>" hidden>
					<button type="button" class="fc-btn" data-vote="details"><?php esc_html_e('Send', 'flash-cache'); ?></button>
				</div>
			</div>
			<p class="fc-vote-msg" role="status"></p>
		</div>
		<?php
	}

}

flash_cache_votes::hooks();
