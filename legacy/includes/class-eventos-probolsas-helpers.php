<?php
/**
 * Clase de funciones auxiliares - ACTUALIZADA
 */

if (!defined('ABSPATH')) {
    exit;
}

class Eventos_Probolsas_Helpers {
    
    /**
     * Obtener tipos de eventos disponibles
     */
    public static function get_event_types() {
        return array(
            'cumpleanos' => array(
                'label' => __('Cumpleaños', 'eventos-probolsas'),
                'color' => '#FFE082',
                'icon' => 'fas fa-birthday-cake',
                'requires_image' => true
            ),
            'capacitacion' => array(
                'label' => __('Capacitaciones', 'eventos-probolsas'),
                'color' => '#A5D6A7',
                'icon' => 'fas fa-graduation-cap',
                'requires_image' => true
            ),
            'reunion_especial' => array(
                'label' => __('Reuniones Especiales', 'eventos-probolsas'),
                'color' => '#81D4FA',
                'icon' => 'fas fa-star',
                'requires_image' => true
            ),
            'reunion_laboral' => array(
                'label' => __('Reuniones Laborales', 'eventos-probolsas'),
                'color' => '#CE93D8',
                'icon' => 'fas fa-briefcase',
                'requires_image' => false
            )
        );
    }
    
    /**
     * Obtener configuración de un tipo de evento
     */
    public static function get_event_type_config($type) {
        $types = self::get_event_types();
        return isset($types[$type]) ? $types[$type] : null;
    }
    
    /**
     * Validar formato de imagen
     */
    public static function is_valid_image($file_path) {
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
        $file_info = wp_check_filetype($file_path);
        
        return in_array($file_info['type'], $allowed_types);
    }
    
    /**
     * Subir imagen de evento
     */
    public static function upload_event_image($file) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        // Verificar que es una imagen válida
        $check = getimagesize($file['tmp_name']);
        if ($check === false) {
            return new WP_Error('invalid_image', __('El archivo no es una imagen válida.', 'eventos-probolsas'));
        }
        
        // Verificar tamaño máximo (5MB)
        $max_size = 5 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', __('El archivo es demasiado grande. Máximo 5MB.', 'eventos-probolsas'));
        }
        
        // Configurar upload
        $upload_overrides = array(
            'test_form' => false,
            'upload_error_handler' => array('Eventos_Probolsas_Helpers', 'handle_upload_error')
        );
        
        // Crear directorio personalizado
        add_filter('upload_dir', array('Eventos_Probolsas_Helpers', 'custom_upload_dir'));
        
        $uploaded_file = wp_handle_upload($file, $upload_overrides);
        
        // Remover filtro
        remove_filter('upload_dir', array('Eventos_Probolsas_Helpers', 'custom_upload_dir'));
        
        if (isset($uploaded_file['error'])) {
            return new WP_Error('upload_error', $uploaded_file['error']);
        }
        
        return $uploaded_file['url'];
    }
    
    /**
     * Directorio personalizado para uploads
     */
    public static function custom_upload_dir($dirs) {
        $dirs['subdir'] = '/eventos-probolsas' . $dirs['subdir'];
        $dirs['path'] = $dirs['basedir'] . $dirs['subdir'];
        $dirs['url'] = $dirs['baseurl'] . $dirs['subdir'];
        
        return $dirs;
    }
    
    /**
     * Manejar errores de upload
     */
    public static function handle_upload_error($file, $message) {
        return array('error' => $message);
    }
    
    /**
     * Formatear fecha para mostrar
     */
    public static function format_date($date, $format = null) {
        if (empty($date)) return '';
        
        // Usar formato de WordPress si no se especifica
        if ($format === null) {
            $format = get_option('date_format', 'd/m/Y');
        }
        
        $datetime = DateTime::createFromFormat('Y-m-d', $date);
        if (!$datetime) {
            $datetime = new DateTime($date);
        }
        
        return $datetime->format($format);
    }
    
    /**
     * Formatear hora para mostrar
     */
    public static function format_time($time, $format = null) {
        if (empty($time)) return '';
        
        // Usar formato de WordPress si no se especifica
        if ($format === null) {
            $format = get_option('time_format', 'H:i');
        }
        
        $datetime = DateTime::createFromFormat('H:i:s', $time);
        if (!$datetime) {
            $datetime = new DateTime($time);
        }
        
        return $datetime->format($format);
    }
    
    /**
     * Formatear fecha y hora combinadas
     */
    public static function format_datetime($date, $time = null) {
        if (empty($date)) return '';
        
        $datetime_str = $date;
        if (!empty($time)) {
            $datetime_str .= ' ' . $time;
        }
        
        $datetime = new DateTime($datetime_str);
        
        $date_format = get_option('date_format', 'd/m/Y');
        $time_format = get_option('time_format', 'H:i');
        
        $format = $date_format;
        if (!empty($time)) {
            $format .= ' ' . $time_format;
        }
        
        return $datetime->format($format);
    }
    
    /**
     * Generar nonce para AJAX
     */
    public static function get_ajax_nonce() {
        return wp_create_nonce('eventos_probolsas_ajax');
    }
    
    /**
     * Verificar nonce AJAX
     */
    public static function verify_ajax_nonce($nonce) {
        return wp_verify_nonce($nonce, 'eventos_probolsas_ajax');
    }
    
    /**
     * Sanitizar datos de evento
     */
    public static function sanitize_event_data($data) {
        $sanitized = array();
        
        if (isset($data['title'])) {
            $sanitized['title'] = sanitize_text_field($data['title']);
        }
        
        if (isset($data['type'])) {
            $sanitized['type'] = sanitize_text_field($data['type']);
        }
        
        if (isset($data['description'])) {
            $sanitized['description'] = sanitize_textarea_field($data['description']);
        }
        
        if (isset($data['event_date'])) {
            $sanitized['event_date'] = sanitize_text_field($data['event_date']);
        }
        
        if (isset($data['event_time'])) {
            $sanitized['event_time'] = sanitize_text_field($data['event_time']);
        }
        
        if (isset($data['image_url'])) {
            $sanitized['image_url'] = esc_url_raw($data['image_url']);
        }
        
        if (isset($data['color'])) {
            $sanitized['color'] = sanitize_hex_color($data['color']);
        }
        
        if (isset($data['icon'])) {
            $sanitized['icon'] = sanitize_text_field($data['icon']);
        }
        
        return $sanitized;
    }
    
    /**
     * Validar datos de evento
     */
    public static function validate_event_data($data) {
        $errors = array();
        
        // Título requerido
        if (empty($data['title'])) {
            $errors[] = __('El título es requerido.', 'eventos-probolsas');
        } elseif (strlen($data['title']) < 3) {
            $errors[] = __('El título debe tener al menos 3 caracteres.', 'eventos-probolsas');
        } elseif (strlen($data['title']) > 255) {
            $errors[] = __('El título no puede exceder 255 caracteres.', 'eventos-probolsas');
        }
        
        // Tipo requerido
        if (empty($data['type'])) {
            $errors[] = __('El tipo de evento es requerido.', 'eventos-probolsas');
        } else {
            $types = self::get_event_types();
            if (!isset($types[$data['type']])) {
                $errors[] = __('Tipo de evento no válido.', 'eventos-probolsas');
            }
        }
        
        // Fecha requerida
        if (empty($data['event_date'])) {
            $errors[] = __('La fecha del evento es requerida.', 'eventos-probolsas');
        } else {
            $date = DateTime::createFromFormat('Y-m-d', $data['event_date']);
            if (!$date || $date->format('Y-m-d') !== $data['event_date']) {
                $errors[] = __('Formato de fecha no válido.', 'eventos-probolsas');
            } else {
                // Verificar que la fecha no sea muy antigua (más de 1 año atrás)
                $one_year_ago = new DateTime('-1 year');
                if ($date < $one_year_ago) {
                    $errors[] = __('La fecha del evento no puede ser anterior a un año.', 'eventos-probolsas');
                }
            }
        }
        
        // Validar hora si está presente
        if (!empty($data['event_time'])) {
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $data['event_time'])) {
                $errors[] = __('Formato de hora no válido (HH:MM).', 'eventos-probolsas');
            }
        }
        
        // Validar descripción
        if (!empty($data['description']) && strlen($data['description']) > 1000) {
            $errors[] = __('La descripción no puede exceder 1000 caracteres.', 'eventos-probolsas');
        }
        
        // Validar imagen requerida para ciertos tipos
        if (!empty($data['type'])) {
            $type_config = self::get_event_type_config($data['type']);
            if ($type_config && $type_config['requires_image'] && empty($data['image_url'])) {
                $errors[] = __('Este tipo de evento requiere una imagen.', 'eventos-probolsas');
            }
        }
        
        // Validar URL de imagen si está presente
        if (!empty($data['image_url'])) {
            if (!filter_var($data['image_url'], FILTER_VALIDATE_URL)) {
                $errors[] = __('URL de imagen no válida.', 'eventos-probolsas');
            }
        }
        
        return $errors;
    }
    
    /**
     * Obtener nombres de meses en español
     */
    public static function get_month_names() {
        return array(
            1 => __('Enero', 'eventos-probolsas'),
            2 => __('Febrero', 'eventos-probolsas'),
            3 => __('Marzo', 'eventos-probolsas'),
            4 => __('Abril', 'eventos-probolsas'),
            5 => __('Mayo', 'eventos-probolsas'),
            6 => __('Junio', 'eventos-probolsas'),
            7 => __('Julio', 'eventos-probolsas'),
            8 => __('Agosto', 'eventos-probolsas'),
            9 => __('Septiembre', 'eventos-probolsas'),
            10 => __('Octubre', 'eventos-probolsas'),
            11 => __('Noviembre', 'eventos-probolsas'),
            12 => __('Diciembre', 'eventos-probolsas')
        );
    }
    
    /**
     * Obtener nombres de días en español
     */
    public static function get_day_names() {
        return array(
            0 => __('Domingo', 'eventos-probolsas'),
            1 => __('Lunes', 'eventos-probolsas'),
            2 => __('Martes', 'eventos-probolsas'),
            3 => __('Miércoles', 'eventos-probolsas'),
            4 => __('Jueves', 'eventos-probolsas'),
            5 => __('Viernes', 'eventos-probolsas'),
            6 => __('Sábado', 'eventos-probolsas')
        );
    }
    
    /**
     * Obtener nombres de días abreviados
     */
    public static function get_day_names_short() {
        return array(
            0 => __('Dom', 'eventos-probolsas'),
            1 => __('Lun', 'eventos-probolsas'),
            2 => __('Mar', 'eventos-probolsas'),
            3 => __('Mié', 'eventos-probolsas'),
            4 => __('Jue', 'eventos-probolsas'),
            5 => __('Vie', 'eventos-probolsas'),
            6 => __('Sáb', 'eventos-probolsas')
        );
    }
    
    /**
     * Obtener color por defecto para un tipo de evento
     */
    public static function get_default_color($type) {
        $types = self::get_event_types();
        return isset($types[$type]['color']) ? $types[$type]['color'] : '#007bff';
    }
    
    /**
     * Obtener icono por defecto para un tipo de evento
     */
    public static function get_default_icon($type) {
        $types = self::get_event_types();
        return isset($types[$type]['icon']) ? $types[$type]['icon'] : 'fas fa-calendar';
    }
    
    /**
     * Generar slug único para evento
     */
    public static function generate_event_slug($title, $id = null) {
        $slug = sanitize_title($title);
        
        if ($id) {
            $slug .= '-' . $id;
        }
        
        return $slug;
    }
    
    /**
     * Calcular días hasta un evento
     */
    public static function days_until_event($event_date) {
        $today = new DateTime();
        $event = new DateTime($event_date);
        
        $today->setTime(0, 0, 0);
        $event->setTime(0, 0, 0);
        
        $diff = $today->diff($event);
        
        if ($event < $today) {
            return -$diff->days; // Evento pasado
        }
        
        return $diff->days;
    }
    
    /**
     * Verificar si un evento es de hoy
     */
    public static function is_today($event_date) {
        return $event_date === current_time('Y-m-d');
    }
    
    /**
     * Verificar si un evento está en el futuro
     */
    public static function is_future_event($event_date) {
        return $event_date > current_time('Y-m-d');
    }
    
    /**
     * Verificar si un evento está en el pasado
     */
    public static function is_past_event($event_date) {
        return $event_date < current_time('Y-m-d');
    }
    
    /**
     * Generar un color aleatorio en formato hexadecimal
     */
    public static function generate_random_color() {
        return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Convertir color hex a RGB
     */
    public static function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) == 6) {
            return array(
                'r' => hexdec(substr($hex, 0, 2)),
                'g' => hexdec(substr($hex, 2, 2)),
                'b' => hexdec(substr($hex, 4, 2))
            );
        }
        
        return array('r' => 0, 'g' => 0, 'b' => 0);
    }
    
    /**
     * Obtener contraste apropiado para un color (blanco o negro)
     */
    public static function get_text_color_for_background($hex_color) {
        $rgb = self::hex_to_rgb($hex_color);
        
        // Calcular luminancia
        $luminance = (0.299 * $rgb['r'] + 0.587 * $rgb['g'] + 0.114 * $rgb['b']) / 255;
        
        return $luminance > 0.5 ? '#000000' : '#ffffff';
    }
    
    /**
     * Aclarar un color hexadecimal
     */
    public static function lighten_color($hex, $percent) {
        $rgb = self::hex_to_rgb($hex);
        
        $rgb['r'] = min(255, $rgb['r'] + ($percent * 255 / 100));
        $rgb['g'] = min(255, $rgb['g'] + ($percent * 255 / 100));
        $rgb['b'] = min(255, $rgb['b'] + ($percent * 255 / 100));
        
        return sprintf('#%02x%02x%02x', $rgb['r'], $rgb['g'], $rgb['b']);
    }
    
    /**
     * Oscurecer un color hexadecimal
     */
    public static function darken_color($hex, $percent) {
        $rgb = self::hex_to_rgb($hex);
        
        $rgb['r'] = max(0, $rgb['r'] - ($percent * 255 / 100));
        $rgb['g'] = max(0, $rgb['g'] - ($percent * 255 / 100));
        $rgb['b'] = max(0, $rgb['b'] - ($percent * 255 / 100));
        
        return sprintf('#%02x%02x%02x', $rgb['r'], $rgb['g'], $rgb['b']);
    }
    
    /**
     * Limpiar y optimizar texto para búsqueda
     */
    public static function normalize_search_text($text) {
        // Remover acentos
        $text = remove_accents($text);
        
        // Convertir a minúsculas
        $text = strtolower($text);
        
        // Remover caracteres especiales pero mantener espacios
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        
        // Remover espacios múltiples
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    /**
     * Generar breadcrumbs para navegación
     */
    public static function generate_breadcrumbs($current_page = '', $event = null) {
        $breadcrumbs = array();
        
        $breadcrumbs[] = array(
            'title' => __('Inicio', 'eventos-probolsas'),
            'url' => home_url(),
            'current' => false
        );
        
        $breadcrumbs[] = array(
            'title' => __('Eventos', 'eventos-probolsas'),
            'url' => admin_url('admin.php?page=eventos-probolsas'),
            'current' => empty($current_page)
        );
        
        if (!empty($current_page)) {
            $page_titles = array(
                'add' => __('Agregar Evento', 'eventos-probolsas'),
                'edit' => __('Editar Evento', 'eventos-probolsas'),
                'view' => __('Ver Evento', 'eventos-probolsas'),
                'settings' => __('Configuración', 'eventos-probolsas')
            );
            
            if (isset($page_titles[$current_page])) {
                $title = $page_titles[$current_page];
                
                if ($event && in_array($current_page, array('edit', 'view'))) {
                    $title .= ': ' . $event['title'];
                }
                
                $breadcrumbs[] = array(
                    'title' => $title,
                    'url' => '',
                    'current' => true
                );
            }
        }
        
        return $breadcrumbs;
    }
    
    /**
     * Verificar si el usuario actual puede gestionar eventos
     */
    public static function current_user_can_manage_events() {
        return current_user_can('manage_options') || current_user_can('edit_posts');
    }
    
    /**
     * Obtener URL del avatar del usuario actual
     */
    public static function get_current_user_avatar($size = 32) {
        $current_user = wp_get_current_user();
        return get_avatar_url($current_user->ID, array('size' => $size));
    }
    
    /**
     * Log de actividad del plugin
     */
    public static function log_activity($action, $details = array()) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'action' => $action,
            'user_id' => get_current_user_id(),
            'user_name' => wp_get_current_user()->display_name,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'details' => $details
        );
        
        error_log('Eventos Probolsas Activity: ' . json_encode($log_data));
    }
    
    /**
     * Obtener estadísticas rápidas
     */
    public static function get_quick_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'eventos_probolsas';
        $today = current_time('Y-m-d');
        
        $stats = array(
            'total' => 0,
            'today' => 0,
            'upcoming' => 0,
            'past' => 0
        );
        
        // Total de eventos
        $stats['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        // Eventos de hoy
        $stats['today'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE event_date = %s",
            $today
        ));
        
        // Eventos futuros
        $stats['upcoming'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE event_date > %s",
            $today
        ));
        
        // Eventos pasados
        $stats['past'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE event_date < %s",
            $today
        ));
        
        return $stats;
    }
}