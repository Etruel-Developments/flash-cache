<?php
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
		create_cache($request);
		
	}
} else {
	create_cache($request);
}
header('Content-type:'.file_get_contents($header_path));
echo file_get_contents($file_path);


function create_cache($request) {
	global $cache_path, $url_path;
	if (!file_exists($cache_path)) {
		mkdir($cache_path, 0777, true);
	}
	$request_url = flash_cache_get_content($url_path);
	file_put_contents($cache_path.$request.'.html', $request_url['response']);
	file_put_contents($cache_path.$request.'.header', $request_url['content_type']);
}
function flash_cache_request_curl($url) {
	$use_post = true;
	if (empty($_POST)) {
		$use_post = false;
	} else {
		$fields_string = http_build_query($_POST);
	}
	if (!empty($_GET)) {
		$url = $url.'?'.http_build_query($_GET);
	}
	
	$ch = curl_init($url);
	$headers   = array();
	$header = array();
	$header[] = "es-419,es;q=0.8";
	$header[] = "Accept-Charset: UTF-8,*;q=0.5";
	$header[] = "Cookie: flash_cache=cookie; flash_cache_backend=cookie;";

	$tmpfname = dirname(__FILE__).'/cookie.txt';
	curl_setopt($ch, CURLOPT_COOKIEJAR, $tmpfname);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $tmpfname);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $header); 
	if ($use_post) {
		curl_setopt($ch,CURLOPT_POST, count($_POST));
		curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
	}
	curl_setopt($ch, CURLOPT_TIMEOUT, 20); 
	curl_setopt($ch, CURLOPT_NOSIGNAL, 1); 
	$response = curl_exec($ch);
	$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
	$return = array();
	$return['response'] = $response;
	$return['content_type'] = $content_type;
	$curl_errno = curl_errno($ch);
	$curl_error = curl_error($ch);	
	if ($curl_errno > 0) {
		$return = flash_cache_request_curl($url);
    }
	return $return;
}

?>