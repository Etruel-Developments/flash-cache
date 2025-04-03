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

// Eliminar cualquier función previa de render_block si existe, EXCEPTO nuestro filtro optimizado
if (function_exists('render_block')) {
    // Mantener solo nuestro filtro específico
    $has_filters = has_filter('render_block', array('flash_cache_pro_blocks', 'render_blocks'));
    if (!$has_filters) {
        remove_all_filters('render_block');
    } else {
        $all_filters = $GLOBALS['wp_filter']['render_block']->callbacks;
        foreach ($all_filters as $priority => $callbacks) {
            foreach ($callbacks as $id => $callback_data) {
                if (!is_array($callback_data['function']) || 
                    $callback_data['function'][0] !== 'flash_cache_pro_blocks' || 
                    $callback_data['function'][1] !== 'render_blocks') {
                    remove_filter('render_block', $callback_data['function'], $priority);
                }
            }
        }
    }
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
        error_log('Flash Cache Replace: Encontrados nombres de bloques: ' . print_r($block_names, true));
    } else {
        error_log('Flash Cache Replace: Archivo de lista de bloques no encontrado en: ' . $block_list_path);
    }

    // Verificar si hay contenido para procesar
    if (empty($content)) {
        error_log('Flash Cache Replace: Contenido vacío proporcionado a replace_disabled_elements');
        return $content;
    }

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8"?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    
    $xpath = new DOMXPath($dom);
    
    // Reemplazar Widgets - SOLO los que tienen disableCache=true
    $widget_path= apply_filters(‘flashcache_widgets_xpath’, '//aside[contains(@class, "widget-area")]//section');

    $footer_widgets = $xpath->query( $widget_path);
    
    if ($footer_widgets->length > 0) {
        $widget_replacements = [];
    
        foreach ($footer_widgets as $section) {
            $section_id = $section->hasAttribute('id') ? $section->getAttribute('id') : '';
            $should_process = false;
    
            // Verificar si el widget tiene los marcadores de desactivación de caché
            if ($section->hasAttribute('data-disable-cache') || preg_match('/\bflash-cache-disabled\b/', $section->getAttribute('class'))) {
                $should_process = true;
            }
    
            // Verificar si el widget está en la lista de deshabilitados
            if (!empty($section_id)) {
                foreach ($widget_names as $widget_name) {
                    $similarity = 0;
                    similar_text(strtolower($section_id), strtolower($widget_name), $similarity);
                    if ($similarity > 65) {
                        $should_process = true;
                        break;
                    }
                }
            }
    
            if ($should_process && !empty($section_id)) {
                foreach ($widget_names as $widget_name) {
                    $similarity = 0;
                    similar_text(strtolower($section_id), strtolower($widget_name), $similarity);
                    if ($similarity > 65) {
                        $widget_replacements[] = [
                            'old_section' => $section,
                            'new_widget_html' => render_widgets($widget_name)
                        ];
                        break;
                    }
                }
            }
        }
    
        // Reemplazar widgets en su misma posición
        foreach ($widget_replacements as $replacement) {
            $old_section = $replacement['old_section'];
            $new_widget_html = $replacement['new_widget_html'];
    
            if (empty(trim($new_widget_html))) {
                continue; // Evita reemplazos con contenido vacío
            }
    
            $temp_dom = new DOMDocument();
            @$temp_dom->loadHTML('<?xml encoding="utf-8"?><div>' . $new_widget_html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            
            $new_widget_node = $temp_dom->getElementsByTagName('div')->item(0);
            if ($new_widget_node) {
                $imported_node = $old_section->ownerDocument->importNode($new_widget_node, true);
    
                // Reemplazar el viejo widget por el nuevo en la misma posición
                $old_section->parentNode->replaceChild($imported_node, $old_section);
            }
        }
    }
    
    // Buscar TODOS los bloques que tienen el span de parámetros (sin importar si tienen la clase flash-cache-disabled)
    $blocks_with_params = $xpath->query('//*[.//span[contains(@class, "flash-cache-block-param")]]');
    error_log('Flash Cache Replace: Encontrados ' . $blocks_with_params->length . ' bloques con parámetros');

    $replacements_made = false;
    $processed_blocks = [];
    
    foreach ($blocks_with_params as $block_element) {
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
                    $context_type = $block_parsed['context_type'] ?? 'unknown';
                    $block_name = $block_parsed['blockName'];
                    $disable_cache = isset($block_parsed['attrs']['disableCache']) ? (bool) $block_parsed['attrs']['disableCache'] : false;
                    $in_disabled_list = in_array($block_name, $block_names);
                    $has_disabled_class = strpos($block_element->getAttribute('class'), 'flash-cache-disabled') !== false;
                    
                    error_log('Flash Cache Replace: Analizando bloque: ' . $block_name . 
                              ', contexto: ' . $context_type . 
                              ', disableCache: ' . ($disable_cache ? 'true' : 'false') . 
                              ', en lista deshabilitada: ' . ($in_disabled_list ? 'true' : 'false') . 
                              ', tiene clase disabled: ' . ($has_disabled_class ? 'true' : 'false'));
                    
                    // SOLO procesar el bloque si:
                    // 1. Tiene la clase flash-cache-disabled Y
                    // 2. disableCache es true en sus atributos O está en la lista de bloques deshabilitados
                    $should_process = $has_disabled_class && ($disable_cache || $in_disabled_list);
                    
                    // Almacenar el bloque y su parámetro para procesamiento posterior
                    $processed_blocks[] = [
                        'block_element' => $block_element,
                        'param_element' => $block_param_element,
                        'should_process' => $should_process,
                        'block_parsed' => $block_parsed,
                        'block_name' => $block_name
                    ];
                }
            }
        }
    }
    
    // Procesar los bloques después de recopilar toda la información
    foreach ($processed_blocks as $block_data) {
        $block_element = $block_data['block_element'];
        $block_param_element = $block_data['param_element'];
        $should_process = $block_data['should_process'];
        $block_parsed = $block_data['block_parsed'];
        $block_name = $block_data['block_name'];
        
        // Verificar si el parámetro sigue siendo hijo del bloque
        // y si el bloque sigue siendo parte del DOM
        $still_child = false;
        
        if ($block_param_element->parentNode && $block_element->parentNode) {
            foreach ($block_element->childNodes as $child) {
                if ($child === $block_param_element) {
                    $still_child = true;
                    break;
                }
            }
        }
        
        if ($still_child) {
            // Eliminar el span de parámetros de manera segura
            $block_element->removeChild($block_param_element);
            
            if ($should_process) {
                error_log('Flash Cache Replace: El bloque será reemplazado: ' . $block_name);
                
                // Definir constante para evitar recursión
                if (!defined('FLASH_CACHE_RENDER_BLOCK')) {
                    define('FLASH_CACHE_RENDER_BLOCK', true);
                }
                
                // Usar el renderizador de bloques de WordPress
                if (function_exists('render_block')) {
                    $block_html = render_block($block_parsed);
                } else {
                    error_log('Flash Cache Replace: ¡Función render_block no encontrada!');
                    continue;
                }
                
                if (!empty($block_html) && $block_element->parentNode) {
                    $temp_dom = new DOMDocument();
                    @$temp_dom->loadHTML('<?xml encoding="utf-8"?>' . $block_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                    
                    $block_node = $dom->importNode($temp_dom->documentElement, true);
                    $block_element->parentNode->replaceChild($block_node, $block_element);
                    $replacements_made = true;
                    error_log('Flash Cache Replace: Bloque reemplazado con éxito: ' . $block_name);
                } else {
                    error_log('Flash Cache Replace: El HTML del bloque está vacío o el nodo padre no existe para: ' . $block_name);
                }
            } else {
                error_log('Flash Cache Replace: Bloque no marcado para reemplazo: ' . $block_name . 
                         ', se mantendrá en caché');
            }
        } else {
            error_log('Flash Cache Replace: El elemento de parámetros ya no es hijo del bloque o el bloque ya fue eliminado: ' . $block_name);
        }
    }

    if ($replacements_made) {
        error_log('Flash Cache Replace: Reemplazos completados');
        return $dom->saveHTML();
    } else {
        error_log('Flash Cache Replace: No se realizaron reemplazos');
        return $content;
    }
}

// Resto del código de caché sigue igual
if (file_exists($file_path)) {
    if (time()-filemtime($file_path) < $minimum_ttl) {
        header('Content-type:'.file_get_contents($header_path));
        $content = file_get_contents($file_path);
        if ($advanced_settings['disable_widget_cache'] == 1){
            $replaced_content = replace_disabled_elements($content);
            echo $replaced_content;
            error_log('Flash Cache: Served cached content with replacements');
        } else{
            echo $content;
            error_log('Flash Cache: Served cached content without replacements');
        }
         
        exit();
    } else {
        error_log('Flash Cache: Cache expired, running site');
        run_site($request);
    }
} else {
    error_log('Flash Cache: No cache file, running site');
    run_site($request);
}

header('Content-type:'.@file_get_contents($header_path));
$content = @file_get_contents($file_path);
$replaced_content = replace_disabled_elements($content);
echo $replaced_content;
error_log('Flash Cache: Served fresh content with replacements');

function run_site($request) {
    global $home_path;
    $_SERVER['SCRIPT_FILENAME'] = $home_path.'index.php';
    include $home_path.'index.php';
}
