<?php
/**
 * Flash Cache, PHP mode. Serves the stored response of this request while it is younger than
 * the pattern's minimum TTL, and hands every other request to WordPress, which stores a fresh one.
 * The key below must match flash_cache_process::request_key().
 */
$flash_cache_home_path	 = {home_path};
$flash_cache_minimum_ttl = {minimum_ttl};

if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', array('GET', 'HEAD'), true)) {
	$flash_cache_query = $_GET;
	foreach (array_keys($flash_cache_query) as $flash_cache_name) {
		if (preg_match('/^(utm_[a-z]+|fbclid|gclid|gclsrc|dclid|msclkid|mc_cid|mc_eid|_ga|_gl)$/i', (string) $flash_cache_name)) {
			unset($flash_cache_query[$flash_cache_name]);
		}
	}
	ksort($flash_cache_query);
	$flash_cache_file = __DIR__ . '/requests/' . hash('sha256', http_build_query($flash_cache_query));

	if (is_file($flash_cache_file . '.html') && time() - filemtime($flash_cache_file . '.html') < $flash_cache_minimum_ttl) {
		if (is_file($flash_cache_file . '.php')) {
			// A handler stored with the response serves it and exits, or returns to let WordPress build it.
			include $flash_cache_file . '.php';
		} else {
			$flash_cache_type = @file_get_contents($flash_cache_file . '.header');
			header('Content-Type: ' . ($flash_cache_type ? $flash_cache_type : 'text/html; charset=UTF-8'));
			readfile($flash_cache_file . '.html');
			exit;
		}
	}
}

$_SERVER['SCRIPT_FILENAME'] = $flash_cache_home_path . 'index.php';
require $flash_cache_home_path . 'index.php';
