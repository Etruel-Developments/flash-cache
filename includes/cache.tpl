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
$advanced_settings = wp_parse_args(get_option('flash_cache_advanced_settings', array()), flash_cache_settings::default_advanced_options());

// Eliminar cualquier función previa de render_block si existe
if (function_exists('render_block')) {
    remove_all_filters('render_block');
}

function render_widgets($widgetName) {
    $clean_widget_name = sanitize_title($widgetName);
    
    $widget_args = array(
        'widget_id' => $clean_widget_name,
        'before_widget' => sprintf('<section id="%s" class="widget %s">', $clean_widget_name, $clean_widget_name),
        'after_widget' => '</section>',
        'before_title' => '<h2 class="widget-title">',
        'after_title' => '</h2>'
    );
    
    // Add error handling and logging
    try {
        ob_start();
        the_widget($widgetName, array(), $widget_args);
        $widget_content = ob_get_clean();
        
        // Check if widget content is empty
        if (empty(trim($widget_content))) {
            return sprintf(
                '<section id="%s" class="widget %s widget-rendering-error">%s</section>', 
                $clean_widget_name, 
                $clean_widget_name, 
                __('Widget failed to load', 'flash-cache-pro')
            );
        }
        
        return $widget_content;
    } catch (Exception $e) {
        return sprintf(
            '<section id="%s" class="widget %s widget-rendering-error">%s</section>', 
            $clean_widget_name, 
            $clean_widget_name, 
            __('Widget failed to load', 'flash-cache-pro')
        );
    }
}

function replace_disabled_elements($content) {
    $widget_list_path = WP_CONTENT_DIR . '/widget_classes_disabled_cache.txt';
    $block_list_path = WP_CONTENT_DIR . '/block_types_disabled_cache.txt';
   
    $widget_names = [];
    $block_names = [];

    if (file_exists($widget_list_path)) {
        $widget_names = file($widget_list_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    if (file_exists($block_list_path)) {
        $block_names = file($block_list_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8"?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    
    $xpath = new DOMXPath($dom);
    
    // Replace Widgets
    $footer_widgets = $xpath->query('//div[contains(@class, "widget-column") and contains(@class, "footer-widget-1")]');
    
    if ($footer_widgets->length > 0) {
        $footer = $footer_widgets->item(0);
        
        $matching_widget_ids = [];
        $widget_sections_to_remove = [];
        $widget_name_mapping = []; 

        foreach ($footer->getElementsByTagName('section') as $section) {
            if ($section->hasAttribute('id')) {
                $section_id = $section->getAttribute('id');
                
                foreach ($widget_names as $widget_name) {
                    $similarity = 0;
                    similar_text(strtolower($section_id), strtolower($widget_name), $similarity);
                    if ($similarity > 65) {
                        $matching_widget_ids[] = $section_id;
                        $widget_sections_to_remove[] = $section;
                        $widget_name_mapping[$section_id] = $widget_name;
                        break;
                    }
                }
            }
        }

        // Add a flag to track if any widget replacements occurred
        $widget_replacements_made = false;

        foreach ($widget_sections_to_remove as $section) {
            $section->parentNode->removeChild($section);
            $widget_replacements_made = true;
        }
        
        foreach ($matching_widget_ids as $old_widget_id) {
            $widget_html = render_widgets($widget_name_mapping[$old_widget_id]);
            $temp_dom = new DOMDocument();
            @$temp_dom->loadHTML('<?xml encoding="utf-8"?>' . $widget_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            
            $widget_node = $dom->importNode($temp_dom->documentElement, true);
            $footer->appendChild($widget_node);
        }
        
        // If no widget replacements were made, return original content
        if (!$widget_replacements_made) {
            return $content;
        }
    }
    
    // Replace Blocks
    $blocks_to_replace = $xpath->query('//*[contains(@class, "flash-cache-disabled")]');

    foreach ($blocks_to_replace as $block_element) {
        // Buscar el elemento span con los parámetros
        $param_elements = $xpath->query('.//span[contains(@class, "flash-cache-block-param")]', $block_element);
        
        if ($param_elements->length > 0) {
            $block_param_element = $param_elements->item(0);
            $encoded_block_param = $block_param_element->textContent;
            $params_pieces = explode('.', $encoded_block_param);
            
            if (count($params_pieces) == 2) {
                $decoded = base64_decode($params_pieces[1]);
                $block_parsed = json_decode($decoded, true);
                
                if ($block_parsed && isset($block_parsed['blockName'])) {
// Eliminar solo el span de parámetros
                    $block_element->removeChild($block_param_element);
                    
                    // Definir constante para evitar recursión
                    if (!defined('FLASH_CACHE_RENDER_BLOCK')) {
                        define('FLASH_CACHE_RENDER_BLOCK', true);
                    }
                    
                    // Usar el renderizador de bloques de WordPress
                    $block_html = render_block($block_parsed);
                    
                    if (!empty($block_html)) {
                        $temp_dom = new DOMDocument();
                        @$temp_dom->loadHTML('<?xml encoding="utf-8"?>' . $block_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                        
                        $block_node = $dom->importNode($temp_dom->documentElement, true);
                        $block_element->parentNode->replaceChild($block_node, $block_element);
                    }
                }
            }
        }
    }

    return $dom->saveHTML();
}
// Resto del código de caché sigue igual
if (file_exists($file_path)) {
    if (time()-filemtime($file_path) < $minimum_ttl) {
        header('Content-type:'.file_get_contents($header_path));
        $content = file_get_contents($file_path);
        if ($advanced_settings['disable_widget_cache'] == 1){
            echo replace_disabled_elements($content);
        } else{
            echo $content;
        }
         
        exit();
    } else {
        run_site($request);
    }
} else {
    run_site($request);
}
header('Content-type:'.@file_get_contents($header_path));
$content = @file_get_contents($file_path);
echo replace_disabled_elements($content);

function run_site($request) {
    global $home_path;
    $_SERVER['SCRIPT_FILENAME'] = $home_path.'index.php';
    include $home_path.'index.php';
}
?>
