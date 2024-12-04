<?php
$home_path = {home_path};
require_once($home_path . 'wp-load.php');
$url_path = {url_path};
$minimum_ttl = {minimum_ttl};
$maximum_ttl = {maximum_ttl};
$request = hash('sha256', http_build_query($_REQUEST));
$cache_path = 'requests/';
$file_path = $cache_path.$request.'.html';
$header_path = $cache_path.$request.'.header';
function render_hora_actual_widget($widgetName) {
    
    $clean_widget_name = sanitize_title($widgetName);
    
    $widget_args = array(
        'widget_id' => $clean_widget_name,
        'before_widget' => sprintf('<section id="%s" class="widget %s">', $clean_widget_name, $clean_widget_name),
        'after_widget' => '</section>',
        'before_title' => '<h2 class="widget-title">',
        'after_title' => '</h2>'
    );
    
    ob_start();
    the_widget($widgetName, array(), $widget_args);
    return ob_get_clean();
}


function replace_footer_widgets($content) {
    $widget_list_path = WP_CONTENT_DIR . '/widget_classes_disabled_cache.txt';
   
    $widget_names = [];
    if (file_exists($widget_list_path)) {
        $widget_names = file($widget_list_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    
    $dom = new DOMDocument();
    
    
    @$dom->loadHTML('<?xml encoding="utf-8"?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    
    
    $xpath = new DOMXPath($dom);
    $footer_widgets = $xpath->query('//div[contains(@class, "widget-column") and contains(@class, "footer-widget-1")]');
    
    if ($footer_widgets->length > 0) {
        
        $footer = $footer_widgets->item(0);
        
        
        $matching_ids = [];
        $sections_to_remove = [];
        $widget_name_mapping = []; 

       
        foreach ($footer->getElementsByTagName('section') as $section) {
            if ($section->hasAttribute('id')) {
                $section_id = $section->getAttribute('id');
                
               
                foreach ($widget_names as $widget_name) {
                    $similarity = 0;
                    similar_text(strtolower($section_id), strtolower($widget_name), $similarity);
                    if ($similarity > 65) {
                        $matching_ids[] = $section_id;
                        $sections_to_remove[] = $section;
                        $widget_name_mapping[$section_id] = $widget_name;
                        break;
                    }
                }
            }
        }


        
        foreach ($sections_to_remove as $section) {
            $section->parentNode->removeChild($section);
        }
        
        
        foreach ($matching_ids as $old_widget_id) {
            
            $widget_html = render_hora_actual_widget($widget_name_mapping[$old_widget_id]);
            $temp_dom = new DOMDocument();
            @$temp_dom->loadHTML('<?xml encoding="utf-8"?>' . $widget_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            
            
            $widget_node = $dom->importNode($temp_dom->documentElement, true);
            $footer->appendChild($widget_node);
        }
    }
    
    
    return $dom->saveHTML();
}

if (file_exists($file_path)) {
    if (time()-filemtime($file_path) < $minimum_ttl) {
        header('Content-type:'.file_get_contents($header_path));
        $content = file_get_contents($file_path);
        echo replace_footer_widgets($content);
        exit();
    } else {
        run_site($request);
    }
} else {
    run_site($request);
}
header('Content-type:'.@file_get_contents($header_path));
$content = @file_get_contents($file_path);
echo replace_footer_widgets($content);

function run_site($request) {
    global $home_path;
    $_SERVER['SCRIPT_FILENAME'] = $home_path.'index.php';
    include $home_path.'index.php';
}
?>
