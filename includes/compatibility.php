<?php
/**
* Static function amp_cache
* Compability to cache_response_html object to AMP pages.
* @return void
* @since 1.0
*/
function amp_cache($response, $url_to_cache) {
	if (strpos($url_to_cache, '/amp/') !==false) {
		remove_filter('flash_cache_response_html', array('flash_cache_process', 'cache_response_html'), 100, 2);
		$response = str_replace(
			'<script src="https://cdn.ampproject.org/v0.js" async></script>', 
			'<script async custom-element="amp-iframe" src="https://cdn.ampproject.org/v0/amp-iframe-0.1.js"></script><script src="https://cdn.ampproject.org/v0.js" async></script>', 
 			$response);

		$defer_flash_cache_js = '
			<!--
			var pattern_minimum = '.flash_cache_process::$pattern['ttl_minimum'].';
			var current_query = "'.base64_encode(json_encode(flash_cache_process::$current_query)).'";
			var ss_optional_post_id = '.flash_cache_process::$optional_post_id.';
			-->
			<amp-iframe width="0" height="0"
			    sandbox="allow-scripts allow-same-origin"
			    layout="responsive"
			    frameborder="0"
			    src="https://cloud.enciclopedismo.com/embed.php?u='.base64_encode(admin_url('admin-post.php?action=onload_flash_cache&p='.urlencode(base64_encode(flash_cache_process::$url_to_cache)).'')).'">
			</amp-iframe>
			';
		$response = str_replace('</body>', $defer_flash_cache_js.'</body>', $response);
	}
	return $response;
}
add_filter('flash_cache_response_html','amp_cache', 99, 2);
?>