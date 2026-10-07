<?php
/**
 * Clase para manejo de base de datos
 */

if (!defined('ABSPATH')) {
    exit;
}

class Eventos_Probolsas_DB {
    
    private $table_name;
    private $charset_collate;
    private $cache_group = 'eventos_probolsas_db';
    private $cache_expiration = 300; // 5 minutos
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'eventos_probolsas';
        $this->charset_collate = $wpdb->get_charset_collate();
    }
    
    /**
     * Obtener todos los eventos
     */
    public function get_events($filters = array()) {
        global $wpdb;
        
        // Generar clave de cache basada en filtros
        $cache_key = 'events_' . md5(serialize($filters));
        $cached_result = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $where_clauses = array('1=1'); // Base para facilitar concatenación
        $where_values = array();
        
        // Filtro por fecha desde
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "event_date >= %s";
            $where_values[] = $filters['date_from'];
        }
        
        // Filtro por fecha hasta
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "event_date <= %s";
            $where_values[] = $filters['date_to'];
        }
        
        // Filtro por tipo
        if (!empty($filters['type'])) {
            $where_clauses[] = "type = %s";
            $where_values[] = $filters['type'];
        }
        
        // Filtro por múltiples tipos
        if (!empty($filters['types']) && is_array($filters['types'])) {
            $placeholders = implode(',', array_fill(0, count($filters['types']), '%s'));
            $where_clauses[] = "type IN ($placeholders)";
            $where_values = array_merge($where_values, $filters['types']);
        }
        
        // Filtro de búsqueda mejorado
        if (!empty($filters['search'])) {
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_clauses[] = "(title LIKE %s OR description LIKE %s)";
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        // Filtro por estado de imagen
        if (isset($filters['has_image'])) {
            if ($filters['has_image']) {
                $where_clauses[] = "image_url IS NOT NULL AND image_url != ''";
            } else {
                $where_clauses[] = "(image_url IS NULL OR image_url = '')";
            }
        }
        
        // Filtro por mes específico
        if (!empty($filters['month']) && !empty($filters['year'])) {
            $where_clauses[] = "YEAR(event_date) = %d AND MONTH(event_date) = %d";
            $where_values[] = intval($filters['year']);
            $where_values[] = intval($filters['month']);
        }
        
        // Filtro por año específico
        if (!empty($filters['year']) && empty($filters['month'])) {
            $where_clauses[] = "YEAR(event_date) = %d";
            $where_values[] = intval($filters['year']);
        }
        
        // Construir consulta base
        $sql = "SELECT * FROM {$this->table_name}";
        
        // Agregar WHERE
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }
        
        // Ordenamiento
        $orderby = isset($filters['orderby']) ? $filters['orderby'] : 'event_date';
        $order = isset($filters['order']) ? strtoupper($filters['order']) : 'ASC';
        
        // Validar campos de ordenamiento
        $valid_orderby = array('event_date', 'title', 'type', 'created_at', 'updated_at', 'id');
        if (!in_array($orderby, $valid_orderby)) {
            $orderby = 'event_date';
        }
        
        // Validar dirección de ordenamiento
        if (!in_array($order, array('ASC', 'DESC'))) {
            $order = 'ASC';
        }
        
        $sql .= " ORDER BY {$orderby} {$order}";
        
        // Agregar ordenamiento secundario para consistencia
        if ($orderby !== 'event_date') {
            $sql .= ", event_date ASC";
        }
        if ($orderby !== 'id') {
            $sql .= ", id ASC";
        }
        
        // Paginación
        if (!empty($filters['limit'])) {
            $limit = intval($filters['limit']);
            $offset = !empty($filters['offset']) ? intval($filters['offset']) : 0;
            $sql .= " LIMIT {$offset}, {$limit}";
        }
        
        // Preparar y ejecutar consulta
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        if ($results === null) {
            // Error en la consulta
            $this->log_db_error('get_events', $wpdb->last_error, $sql);
            return array();
        }
        
        // Procesar resultados
        $processed_results = $this->process_event_results($results);
        
        // Guardar en cache solo si no hay paginación
        if (empty($filters['limit'])) {
            wp_cache_set($cache_key, $processed_results, $this->cache_group, $this->cache_expiration);
        }
        
        return $processed_results;
    }
    
    /**
     * Obtener conteo de eventos para paginación
     */
    public function get_events_count($filters = array()) {
        global $wpdb;
        
        // Cache key específico para conteos
        $cache_key = 'events_count_' . md5(serialize($filters));
        $cached_count = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached_count !== false) {
            return intval($cached_count);
        }
        
        $where_clauses = array('1=1');
        $where_values = array();
        
        // Reutilizar lógica de filtros de get_events
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "event_date >= %s";
            $where_values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "event_date <= %s";
            $where_values[] = $filters['date_to'];
        }
        
        if (!empty($filters['type'])) {
            $where_clauses[] = "type = %s";
            $where_values[] = $filters['type'];
        }
        
        if (!empty($filters['search'])) {
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_clauses[] = "(title LIKE %s OR description LIKE %s)";
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        // Construir consulta de conteo
        $sql = "SELECT COUNT(*) FROM {$this->table_name}";
        
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        $count = $wpdb->get_var($sql);
        
        if ($count === null) {
            $this->log_db_error('get_events_count', $wpdb->last_error, $sql);
            return 0;
        }
        
        $count = intval($count);
        
        // Cache por más tiempo (10 minutos) ya que los conteos cambian menos
        wp_cache_set($cache_key, $count, $this->cache_group, 600);
        
        return $count;
    }
    
    /**
     * Obtener evento por ID
     */
    public function get_event($id) {
        global $wpdb;
        
        $id = intval($id);
        if (!$id) {
            return false;
        }
        
        // Cache individual por evento
        $cache_key = "event_{$id}";
        $cached_event = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached_event !== false) {
            return $cached_event;
        }
        
        $sql = $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id);
        $result = $wpdb->get_row($sql, ARRAY_A);
        
        if ($result === null) {
            $this->log_db_error('get_event', $wpdb->last_error, $sql);
            return false;
        }
        
        if (!$result) {
            // Evento no encontrado
            return false;
        }
        
        $processed_result = $this->process_single_event($result);
        
        // Cache por 10 minutos para eventos individuales
        wp_cache_set($cache_key, $processed_result, $this->cache_group, 600);
        
        return $processed_result;
    }
    
    /**
     * Crear nuevo evento
     */
    public function create_event($data) {
        global $wpdb;
        
        // Validar datos requeridos
        if (empty($data['title']) || empty($data['type']) || empty($data['event_date'])) {
            return false;
        }
        
        // Preparar datos con valores por defecto
        $defaults = array(
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'description' => '',
            'event_time' => null,
            'image_url' => null,
            'image_attachment_id' => null
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Asegurar formato correcto de tiempo
        if (!empty($data['event_time']) && !preg_match('/^\d{2}:\d{2}:\d{2}$/', $data['event_time'])) {
            $data['event_time'] .= ':00';
        }
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'title' => $data['title'],
                'type' => $data['type'],
                'description' => $data['description'],
                'event_date' => $data['event_date'],
                'event_time' => $data['event_time'],
                'image_url' => $data['image_url'],
                'image_attachment_id' => $data['image_attachment_id'],
                'color' => $data['color'],
                'icon' => $data['icon'],
                'created_at' => $data['created_at'],
                'updated_at' => $data['updated_at']
            ),
            array(
                '%s', // title
                '%s', // type
                '%s', // description
                '%s', // event_date
                '%s', // event_time
                '%s', // image_url
                '%d', // image_attachment_id
                '%s', // color
                '%s', // icon
                '%s', // created_at
                '%s'  // updated_at
            )
        );
        
        if ($result === false) {
            $this->log_db_error('create_event', $wpdb->last_error, $wpdb->last_query);
            return false;
        }
        
        $event_id = $wpdb->insert_id;
        
        // Invalidar cache relacionado
        $this->invalidate_cache_for_event_changes();
        
        // Hook para extensiones
        do_action('eventos_probolsas_event_created', $event_id, $data);
        
        return $event_id;
    }
    
    /**
     * Actualizar evento 
     */
    public function update_event($id, $data) {
        global $wpdb;
        
        $id = intval($id);
        if (!$id) {
            return false;
        }
        
        // Verificar que el evento existe
        $existing_event = $this->get_event($id);
        if (!$existing_event) {
            return false;
        }
        
        // Preparar datos de actualización
        $data['updated_at'] = current_time('mysql');
        
        // Asegurar formato correcto de tiempo
        if (!empty($data['event_time']) && !preg_match('/^\d{2}:\d{2}:\d{2}$/', $data['event_time'])) {
            $data['event_time'] .= ':00';
        }
        
        // Si se proporciona attachment_id, obtener la URL
        if (!empty($data['image_attachment_id'])) {
            $attachment_url = wp_get_attachment_url($data['image_attachment_id']);
            if ($attachment_url) {
                $data['image_url'] = $attachment_url;
            }
        }
        
        // Campos permitidos para actualización
        $allowed_fields = array('title', 'type', 'description', 'event_date', 'event_time', 'image_url', 'image_attachment_id', 'color', 'icon', 'updated_at');
        $update_data = array();
        $update_format = array();
        
        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $data)) {
                $update_data[$field] = $data[$field];
                $update_format[] = '%s';
                
                // Formato específico para attachment_id
                if ($field === 'image_attachment_id') {
                    $update_format[] = '%d';
                } else {
                    $update_format[] = '%s';
                }
            }
        }
        
        if (empty($update_data)) {
            return false; // No hay datos para actualizar
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $id),
            $update_format,
            array('%d')
        );
        
        if ($result === false) {
            $this->log_db_error('update_event', $wpdb->last_error, $wpdb->last_query);
            return false;
        }
        
        // Invalidar cache
        $this->invalidate_cache_for_event($id);
        $this->invalidate_cache_for_event_changes();
        
        // Hook para extensiones
        do_action('eventos_probolsas_event_updated', $id, $data, $existing_event);
        
        return true;
    }
    
    /**
     * Eliminar evento
     */
    public function delete_event($id) {
        global $wpdb;
        
        $id = intval($id);
        if (!$id) {
            return false;
        }
        
        // Obtener datos del evento antes de eliminar
        $event = $this->get_event($id);
        if (!$event) {
            return false; // Evento no existe
        }
        
        // Eliminar evento de la base de datos
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );
        
        if ($result === false) {
            $this->log_db_error('delete_event', $wpdb->last_error, $wpdb->last_query);
            return false;
        }
        
        // Verificar si el attachment debe ser eliminado
        if (!empty($event['image_attachment_id'])) {
            // Verificar si otros eventos usan la misma imagen
            $usage_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE image_attachment_id = %d",
                $event['image_attachment_id']
            ));
            
            // Si ningún otro evento usa la imagen, eliminarla de la biblioteca de medios
            if ($usage_count == 0) {
                wp_delete_attachment($event['image_attachment_id'], true);
            }
        }
        
        // Invalidar cache
        $this->invalidate_cache_for_event($id);
        $this->invalidate_cache_for_event_changes();
        
        // Hook para extensiones
        do_action('eventos_probolsas_event_deleted', $id, $event);
        
        return true;
    }
    
    /**
     * Obtener eventos por mes
     */
    public function get_events_by_month($year, $month) {
        $filters = array(
            'year' => $year,
            'month' => $month,
            'orderby' => 'event_date',
            'order' => 'ASC'
        );
        
        return $this->get_events($filters);
    }
    
    /**
     * Obtener estadísticas de eventos
     */
    public function get_stats() {
        global $wpdb;
        
        // Cache de estadísticas
        $cache_key = 'eventos_stats';
        $cached_stats = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached_stats !== false) {
            return $cached_stats;
        }
        
        $stats = array();
        
        // Total de eventos
        $stats['total'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}"));
        
        // Eventos por tipo
        $types_sql = "SELECT type, COUNT(*) as count FROM {$this->table_name} GROUP BY type";
        $types = $wpdb->get_results($types_sql, ARRAY_A);
        
        $stats['by_type'] = array();
        foreach ($types as $type) {
            $stats['by_type'][$type['type']] = intval($type['count']);
        }
        
        // Próximos eventos (siguientes 30 días)
        $future_sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE event_date BETWEEN %s AND %s",
            current_time('Y-m-d'),
            date('Y-m-d', strtotime('+30 days'))
        );
        $stats['upcoming'] = intval($wpdb->get_var($future_sql));
        
        // Eventos de hoy
        $today_sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE event_date = %s",
            current_time('Y-m-d')
        );
        $stats['today'] = intval($wpdb->get_var($today_sql));
        
        // Eventos con imagen
        $with_image_sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE image_url IS NOT NULL AND image_url != ''";
        $stats['with_image'] = intval($wpdb->get_var($with_image_sql));
        
        // Eventos sin hora específica
        $no_time_sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE event_time IS NULL OR event_time = ''";
        $stats['without_time'] = intval($wpdb->get_var($no_time_sql));
        
        // Cache por 15 minutos
        wp_cache_set($cache_key, $stats, $this->cache_group, 900);
        
        return $stats;
    }
    
    /**
     * Buscar eventos con texto completo
     */
    public function search_events_fulltext($search_term, $limit = 20) {
        global $wpdb;
        
        if (empty($search_term)) {
            return array();
        }
        
        $search_term = sanitize_text_field($search_term);
        $cache_key = 'search_' . md5($search_term . $limit);
        $cached_results = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached_results !== false) {
            return $cached_results;
        }
        
        // Buscar en título y descripción con relevancia
        $sql = $wpdb->prepare("
            SELECT *, 
                   CASE 
                       WHEN title LIKE %s THEN 3
                       WHEN title LIKE %s THEN 2
                       WHEN description LIKE %s THEN 1
                       ELSE 0
                   END as relevance
            FROM {$this->table_name}
            WHERE title LIKE %s OR description LIKE %s
            ORDER BY relevance DESC, event_date ASC
            LIMIT %d
        ", 
            $search_term . '%',  // Comienza con
            '%' . $search_term . '%',  // Contiene
            '%' . $search_term . '%',  // Descripción contiene
            '%' . $search_term . '%',  // Título contiene
            '%' . $search_term . '%',  // Descripción contiene
            $limit
        );
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        if ($results === null) {
            $this->log_db_error('search_events_fulltext', $wpdb->last_error, $sql);
            return array();
        }
        
        $processed_results = $this->process_event_results($results);
        
        // Cache por 5 minutos (búsquedas cambian frecuentemente)
        wp_cache_set($cache_key, $processed_results, $this->cache_group, 300);
        
        return $processed_results;
    }
    
    /**
     * Optimizar tabla de base de datos
     */
    public function optimize_table() {
        global $wpdb;
        
        // Solo para administradores
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $result = $wpdb->query("OPTIMIZE TABLE {$this->table_name}");
        
        if ($result === false) {
            $this->log_db_error('optimize_table', $wpdb->last_error, "OPTIMIZE TABLE {$this->table_name}");
            return false;
        }
        
        // Limpiar todo el cache después de optimizar
        wp_cache_flush_group($this->cache_group);
        
        return true;
    }
    
    /**
     * Procesar resultados de eventos
     */
    private function process_event_results($results) {
        if (empty($results)) {
            return array();
        }
        
        $processed = array();
        
        foreach ($results as $event) {
            $processed[] = $this->process_single_event($event);
        }
        
        return $processed;
    }
    
    /**
     * Procesar un solo evento
     */
    private function process_single_event($event) {
        if (empty($event)) {
            return $event;
        }
        
        // Asegurar tipos correctos
        $event['id'] = intval($event['id']);
        
        // Procesar attachment_id si existe
        if (!empty($event['image_attachment_id'])) {
            $attachment_id = intval($event['image_attachment_id']);
            
            // Obtener información del attachment
            if (wp_attachment_is_image($attachment_id)) {
                $attachment_url = wp_get_attachment_url($attachment_id);
                
                if ($attachment_url) {
                    $event['image_url'] = $attachment_url;
                    $event['image_alt'] = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                    $event['image_title'] = get_the_title($attachment_id);
                    
                    // Obtener diferentes tamaños de imagen
                    $image_medium = wp_get_attachment_image_src($attachment_id, 'medium');
                    $image_thumbnail = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                    
                    $event['image_medium_url'] = $image_medium ? $image_medium[0] : $attachment_url;
                    $event['image_thumbnail_url'] = $image_thumbnail ? $image_thumbnail[0] : $attachment_url;
                    
                    // Información adicional del archivo
                    $attachment_meta = wp_get_attachment_metadata($attachment_id);
                    if ($attachment_meta) {
                        $event['image_width'] = $attachment_meta['width'] ?? null;
                        $event['image_height'] = $attachment_meta['height'] ?? null;
                        $event['image_file_size'] = isset($attachment_meta['filesize']) 
                            ? size_format($attachment_meta['filesize']) 
                            : null;
                    }
                }
            }
        }
        
        // Formatear fecha y hora si están presentes
        if (!empty($event['event_date'])) {
            $event['formatted_date'] = Eventos_Probolsas_Helpers::format_date($event['event_date']);
        }
        
        if (!empty($event['event_time'])) {
            $event['formatted_time'] = Eventos_Probolsas_Helpers::format_time($event['event_time']);
        }
        
        // Validar URL de imagen (fallback si no hay attachment)
        if (empty($event['image_attachment_id']) && !empty($event['image_url']) && !filter_var($event['image_url'], FILTER_VALIDATE_URL)) {
            $event['image_url'] = '';
        }
        
        // Asegurar que los campos requeridos existan
        $required_fields = array('title', 'type', 'event_date', 'color', 'icon');
        foreach ($required_fields as $field) {
            if (!isset($event[$field])) {
                $event[$field] = '';
            }
        }
        
        // Aplicar filtros para extensiones
        return apply_filters('eventos_probolsas_process_event', $event);
    }
    
    /**
     * Invalidar cache para un evento específico
     */
    private function invalidate_cache_for_event($event_id) {
        wp_cache_delete("event_{$event_id}", $this->cache_group);
    }
    
    /**
     * Invalidar cache general por cambios en eventos
     */
    private function invalidate_cache_for_event_changes() {
        // Invalidar cache de estadísticas
        wp_cache_delete('eventos_stats', $this->cache_group);
        
        // Invalidar cache de listados
        wp_cache_flush_group($this->cache_group);
        
        // Hook para que otros plugins invaliden su cache
        do_action('eventos_probolsas_cache_invalidated');
    }
    
    /**
     * Limpiar imagen de evento eliminado
     */
    private function cleanup_event_image($image_url) {
        if (empty($image_url)) {
            return;
        }
        
        // Solo eliminar si es una imagen subida por el plugin
        $upload_dir = wp_upload_dir();
        $plugin_upload_path = $upload_dir['basedir'] . '/eventos-probolsas/';
        
        // Convertir URL a path local
        $image_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
        
        // Verificar que la imagen esté en el directorio del plugin
        if (strpos($image_path, $plugin_upload_path) === 0 && file_exists($image_path)) {
            $deleted = unlink($image_path);
            
            if ($deleted) {
                // Log de imagen eliminada
                error_log("Eventos Probolsas: Imagen eliminada - {$image_path}");
            }
        }
    }
    
    /**
     * Log de errores de base de datos
     */
    private function log_db_error($function, $error, $query) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'function' => $function,
            'error' => $error,
            'query' => $query,
            'user_id' => get_current_user_id(),
            'backtrace' => wp_debug_backtrace_summary()
        );
        
        error_log('Eventos Probolsas DB Error: ' . json_encode($log_data));
    }
}