<?php
$home_path = {home_path};
$url_path = {url_path};
$minimum_ttl = {minimum_ttl};
$maximum_ttl = {maximum_ttl};
$request = hash('sha256', http_build_query($_REQUEST));
$cache_path = 'requests/';
$file_path = $cache_path.$request.'.html';
$header_path = $cache_path.$request.'.header';
if (file_exists($file_path)) {
	if (time()-filemtime($file_path) < $minimum_ttl) {
		header('Content-type:'.file_get_contents($header_path));
		echo file_get_contents($file_path);
		exit();
	} else {
		run_site($request);
	}
} else {
	run_site($request);
}
header('Content-type:'.@file_get_contents($header_path));
echo @file_get_contents($file_path);

function run_site($request) {
	global $home_path;
	$_SERVER['SCRIPT_FILENAME'] = $home_path.'index.php';
	include $home_path.'index.php';
}

?>
