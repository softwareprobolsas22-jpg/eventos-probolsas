<?php
/**
 * Clase para manejo de AJAX - VERSIÓN MEJORADA
 */

if (!defined('ABSPATH')) {
    exit;
}

class Eventos_Probolsas_Ajax {
    
    private $db;
    
    public function __construct() {
        $this->db = new Eventos_Probolsas_DB();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Hooks para usuarios admin
        add_action('wp_ajax_eventos_create', array($this, 'ajax_create_event'));
        add_action('wp_ajax_eventos_update', array($this, 'ajax_update_event'));
        add_action('wp_ajax_eventos_delete', array($this, 'ajax_delete_event'));
        add_action('wp_ajax_eventos_get', array($this, 'ajax_get_event'));
        add_action('wp_ajax_eventos_search', array($this, 'ajax_search_events'));
        
        // Hooks para usuarios no logueados (frontend)
        add_action('wp_ajax_nopriv_eventos_get_calendar_data', array($this, 'ajax_get_calendar_data'));
        add_action('wp_ajax_eventos_get_calendar_data', array($this, 'ajax_get_calendar_data'));
        add_action('wp_ajax_nopriv_eventos_get_event_details', array($this, 'ajax_get_event_details'));
        add_action('wp_ajax_eventos_get_event_details', array($this, 'ajax_get_event_details'));
        add_action('wp_ajax_nopriv_eventos_search', array($this, 'ajax_search_events'));
        
        // NUEVOS: Hooks adicionales para funcionalidades mejoradas
        add_action('wp_ajax_nopriv_eventos_get_events_by_date', array($this, 'ajax_get_events_by_date'));
        add_action('wp_ajax_eventos_get_events_by_date', array($this, 'ajax_get_events_by_date'));
        add_action('wp_ajax_nopriv_eventos_get_calendar_stats', array($this, 'ajax_get_calendar_stats'));
        add_action('wp_ajax_eventos_get_calendar_stats', array($this, 'ajax_get_calendar_stats'));
        add_action('wp_ajax_eventos_export_csv', array($this, 'ajax_export_csv'));
        add_action('wp_ajax_eventos_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_eventos_upload_image', array($this, 'ajax_upload_image'));
        
        // Hook para validaciones en tiempo real
        add_action('wp_ajax_eventos_validate_date', array($this, 'ajax_validate_date'));
        add_action('wp_ajax_nopriv_eventos_validate_date', array($this, 'ajax_validate_date'));
    }
    
    /**
     * Crear evento vía AJAX
     */
    public function ajax_create_event() {
        
        // Verificar nonce
        if (!$this->verify_nonce($_POST['nonce'])) {
            wp_send_json_error(__('Error de seguridad.', 'eventos-probolsas'));
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'eventos-probolsas'));
        }
        
        // Sanitizar datos
        $data = $this->sanitize_event_data($_POST);
        
        // Validar datos
        $validation_result = $this->validate_event_data($data);
        if (!$validation_result['valid']) {
            wp_send_json_error($validation_result['errors']);
        }
        
        // Obtener configuración del tipo
        $type_config = Eventos_Probolsas_Helpers::get_event_type_config($data['type']);
        if ($type_config) {
            $data['color'] = $type_config['color'];
            $data['icon'] = $type_config['icon'];
        }
        
        // Crear evento
        $event_id = $this->db->create_event($data);
        
        if ($event_id) {
            // Limpiar cache relacionado
            $this->clear_events_cache();
            
            // Obtener el evento creado para devolverlo completo
            $created_event = $this->db->get_event($event_id);
            
            wp_send_json_success(array(
                'message' => __('Evento creado exitosamente.', 'eventos-probolsas'),
                'event_id' => $event_id,
                'event' => $this->format_event_for_response($created_event)
            ));
        } else {
            wp_send_json_error(__('Error al crear el evento.', 'eventos-probolsas'));
        }
    }
    
    /**
     * Actualizar evento vía AJAX
     */
    public function ajax_update_event() {
        // Verificar nonce
        if (!$this->verify_nonce($_POST['nonce'])) {
            wp_send_json_error(__('Error de seguridad.', 'eventos-probolsas'));
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'eventos-probolsas'));
        }
        
        $event_id = intval($_POST['event_id']);
        if (!$event_id) {
            wp_send_json_error(__('ID de evento no válido.', 'eventos-probolsas'));
        }
        
        // Verificar que el evento existe
        $existing_event = $this->db->get_event($event_id);
        if (!$existing_event) {
            wp_send_json_error(__('El evento no existe.', 'eventos-probolsas'));
        }
        
        // Sanitizar datos
        $data = $this->sanitize_event_data($_POST);
        
        // Validar datos
        $validation_result = $this->validate_event_data($data);
        if (!$validation_result['valid']) {
            wp_send_json_error($validation_result['errors']);
        }
        
        // Obtener configuración del tipo
        $type_config = Eventos_Probolsas_Helpers::get_event_type_config($data['type']);
        if ($type_config) {
            $data['color'] = $type_config['color'];
            $data['icon'] = $type_config['icon'];
        }
        
        // Actualizar evento
        $success = $this->db->update_event($event_id, $data);
        
        if ($success) {
            // Limpiar cache relacionado
            $this->clear_events_cache();
            
            // Obtener el evento actualizado
            $updated_event = $this->db->get_event($event_id);
            
            wp_send_json_success(array(
                'message' => __('Evento actualizado exitosamente.', 'eventos-probolsas'),
                'event' => $this->format_event_for_response($updated_event)
            ));
        } else {
            wp_send_json_error(__('Error al actualizar el evento.', 'eventos-probolsas'));
        }
    }
    
    /**
     * Eliminar evento vía AJAX
     */
    public function ajax_delete_event() {
        // Verificar nonce
        if (!$this->verify_nonce($_POST['nonce'])) {
            wp_send_json_error(__('Error de seguridad.', 'eventos-probolsas'));
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'eventos-probolsas'));
        }
        
        $event_id = intval($_POST['event_id']);
        if (!$event_id) {
            wp_send_json_error(__('ID de evento no válido.', 'eventos-probolsas'));
        }
        
        // Verificar que el evento existe
        $existing_event = $this->db->get_event($event_id);
        if (!$existing_event) {
            wp_send_json_error(__('El evento no existe.', 'eventos-probolsas'));
        }
        
        $success = $this->db->delete_event($event_id);
        
        if ($success) {
            // Limpiar cache relacionado
            $this->clear_events_cache();
            
            wp_send_json_success(array(
                'message' => __('Evento eliminado exitosamente.', 'eventos-probolsas'),
                'deleted_event_id' => $event_id
            ));
        } else {
            wp_send_json_error(__('Error al eliminar el evento.', 'eventos-probolsas'));
        }
    }
    
    /**
     * Obtener evento vía AJAX
     */
    public function ajax_get_event() {
        // Verificar nonce
        if (!$this->verify_nonce($_POST['nonce'])) {
            wp_send_json_error(__('Error de seguridad.', 'eventos-probolsas'));
        }
        
        $event_id = intval($_POST['event_id']);
        if (!$event_id) {
            wp_send_json_error(__('ID de evento no válido.', 'eventos-probolsas'));
        }
        
        $event = $this->db->get_event($event_id);
        
        if ($event) {
            wp_send_json_success($this->format_event_for_response($event));
        } else {
            wp_send_json_error(__('Evento no encontrado.', 'eventos-probolsas'));
        }
    }
    
    /**
     * Buscar eventos vía AJAX
     */
    public function ajax_search_events() {
        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        
        $filters = array();
        
        if (!empty($search_term)) {
            $filters['search'] = $search_term;
        }
        
        if (!empty($type)) {
            $filters['type'] = $type;
        }
        
        if (!empty($date_from)) {
            $filters['date_from'] = $date_from;
        }
        
        if (!empty($date_to)) {
            $filters['date_to'] = $date_to;
        }
        
        // Agregar límite y offset para paginación
        $filters['limit'] = $limit;
        $filters['offset'] = $offset;
        
        $events = $this->db->get_events($filters);
        
        // Obtener conteo total para paginación
        $total_count = $this->db->get_events_count($filters);
        
        // Formatear eventos para respuesta
        $formatted_events = array();
        
        foreach ($events as $event) {
            $formatted_events[] = $this->format_event_for_response($event);
        }
        
        wp_send_json_success(array(
            'events' => $formatted_events,
            'total' => $total_count,
            'has_more' => ($offset + $limit) < $total_count,
            'filters_applied' => !empty($search_term) || !empty($type) || !empty($date_from) || !empty($date_to)
        ));
    }
    
    /**
     * Obtener eventos para calendario vía AJAX 
     */
    public function ajax_get_calendar_data() {
        $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
        $month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
        
        // Validar año y mes
        if ($year < 1900 || $year > 2100 || $month < 1 || $month > 12) {
            wp_send_json_error(__('Año o mes no válido.', 'eventos-probolsas'));
        }
        
        // Intentar obtener del cache primero
        $cache_key = "eventos_calendar_{$year}_{$month}";
        $cached_data = wp_cache_get($cache_key, 'eventos_probolsas');
        
        if ($cached_data !== false) {
            wp_send_json_success($cached_data);
            return;
        }
        
        $events = $this->db->get_events_by_month($year, $month);
        
        // Organizar eventos por fecha
        $calendar_events = array();
        $event_types = Eventos_Probolsas_Helpers::get_event_types();
        
        foreach ($events as $event) {
            $day = date('j', strtotime($event['event_date']));
            
            if (!isset($calendar_events[$day])) {
                $calendar_events[$day] = array();
            }
            
            $calendar_events[$day][] = $this->format_event_for_calendar($event, $event_types);
        }
        
        // Generar información del calendario
        $first_day_of_month = mktime(0, 0, 0, $month, 1, $year);
        $days_in_month = date('t', $first_day_of_month);
        $first_day_of_week = date('w', $first_day_of_month);
        
        // Ajustar primer día según configuración de WordPress
        $start_of_week = get_option('start_of_week', 0);
        $first_day_of_week = ($first_day_of_week - $start_of_week + 7) % 7;
        
        // Calcular estadísticas
        $stats = $this->calculate_calendar_stats($events);
        
        $calendar_info = array(
            'year' => $year,
            'month' => $month,
            'month_name' => Eventos_Probolsas_Helpers::get_month_names()[$month],
            'days_in_month' => $days_in_month,
            'first_day_of_week' => $first_day_of_week,
            'start_of_week' => $start_of_week,
            'events' => $calendar_events,
            'total_events' => count($events),
            'event_types' => $event_types,
            'stats' => $stats,
            'cache_time' => current_time('mysql')
        );
        
        // Guardar en cache por 5 minutos
        wp_cache_set($cache_key, $calendar_info, 'eventos_probolsas', 300);
        
        wp_send_json_success($calendar_info);
    }
    
    /**
     * Obtener detalles de evento para modal
     */
    public function ajax_get_event_details() {
        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        
        if (!$event_id) {
            wp_send_json_error(__('ID de evento no válido.', 'eventos-probolsas'));
        }
        
        // Intentar obtener del cache
        $cache_key = "evento_details_{$event_id}";
        $cached_event = wp_cache_get($cache_key, 'eventos_probolsas');
        
        if ($cached_event !== false) {
            wp_send_json_success($cached_event);
            return;
        }
        
        $event = $this->db->get_event($event_id);
        
        if (!$event) {
            wp_send_json_error(__('Evento no encontrado.', 'eventos-probolsas'));
        }
        
        // Formatear datos completos del evento
        $formatted_event = $this->format_event_details($event);
        
        // Guardar en cache por 5 minutos
        wp_cache_set($cache_key, $formatted_event, 'eventos_probolsas', 300);
        
        wp_send_json_success($formatted_event);
    }
    
    /**
     * Obtener eventos por fecha específica
     */
    public function ajax_get_events_by_date() {
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        
        if (empty($date)) {
            wp_send_json_error(__('Fecha no válida.', 'eventos-probolsas'));
        }
        
        // Validar formato de fecha
        if (!DateTime::createFromFormat('Y-m-d', $date)) {
            wp_send_json_error(__('Formato de fecha no válido.', 'eventos-probolsas'));
        }
        
        $filters = array(
            'date_from' => $date,
            'date_to' => $date
        );
        
        $events = $this->db->get_events($filters);
        
        $formatted_events = array();
        foreach ($events as $event) {
            $formatted_events[] = $this->format_event_for_response($event);
        }
        
        wp_send_json_success(array(
            'date' => $date,
            'events' => $formatted_events,
            'count' => count($formatted_events)
        ));
    }
    
    /**
     * Obtener estadísticas del calendario
     */
    public function ajax_get_calendar_stats() {
        $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
        $month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
        
        $events = $this->db->get_events_by_month($year, $month);
        $stats = $this->calculate_calendar_stats($events);
        
        wp_send_json_success($stats);
    }
    
    /**
     * Exportar eventos a CSV
     */
    public function ajax_export_csv() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'eventos-probolsas'));
        }
        
        // Verificar nonce
        if (!wp_verify_nonce($_GET['nonce'], 'eventos_probolsas_ajax')) {
            wp_die(__('Error de seguridad.', 'eventos-probolsas'));
        }
        
        // Obtener filtros de la URL
        $filters = array();
        if (!empty($_GET['search'])) $filters['search'] = sanitize_text_field($_GET['search']);
        if (!empty($_GET['type'])) $filters['type'] = sanitize_text_field($_GET['type']);
        if (!empty($_GET['date_from'])) $filters['date_from'] = sanitize_text_field($_GET['date_from']);
        if (!empty($_GET['date_to'])) $filters['date_to'] = sanitize_text_field($_GET['date_to']);
        
        $events = $this->db->get_events($filters);
        
        if (empty($events)) {
            wp_die(__('No hay eventos para exportar.', 'eventos-probolsas'));
        }
        
        // Generar CSV
        $filename = 'eventos-probolsas-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers del CSV
        $headers = array(
            __('ID', 'eventos-probolsas'),
            __('Título', 'eventos-probolsas'),
            __('Tipo', 'eventos-probolsas'),
            __('Descripción', 'eventos-probolsas'),
            __('Fecha', 'eventos-probolsas'),
            __('Hora', 'eventos-probolsas'),
            __('Imagen', 'eventos-probolsas'),
            __('Creado', 'eventos-probolsas'),
            __('Actualizado', 'eventos-probolsas')
        );
        
        fputcsv($output, $headers);
        
        // Datos de eventos
        foreach ($events as $event) {
            $type_config = Eventos_Probolsas_Helpers::get_event_type_config($event['type']);
            
            $row = array(
                $event['id'],
                $event['title'],
                $type_config ? $type_config['label'] : $event['type'],
                $event['description'],
                Eventos_Probolsas_Helpers::format_date($event['event_date']),
                $event['event_time'] ? Eventos_Probolsas_Helpers::format_time($event['event_time']) : '',
                $event['image_url'] ? 'Sí' : 'No',
                $event['created_at'],
                $event['updated_at']
            );
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Limpiar cache de eventos
     */
    public function ajax_clear_cache() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'eventos-probolsas'));
        }
        
        // Verificar nonce
        if (!$this->verify_nonce($_POST['nonce'])) {
            wp_send_json_error(__('Error de seguridad.', 'eventos-probolsas'));
        }
        
        $this->clear_events_cache();
        
        wp_send_json_success(array(
            'message' => __('Cache limpiado exitosamente.', 'eventos-probolsas')
        ));
    }
    
    /**
     * NUEVA FUNCIÓN: Validar fecha en tiempo real
     */
    public function ajax_validate_date() {
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        
        if (empty($date)) {
            wp_send_json_error(__('Fecha requerida.', 'eventos-probolsas'));
        }
        
        $datetime = DateTime::createFromFormat('Y-m-d', $date);
        
        if (!$datetime || $datetime->format('Y-m-d') !== $date) {
            wp_send_json_error(__('Formato de fecha no válido.', 'eventos-probolsas'));
        }
        
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        
        if ($datetime < $today) {
            wp_send_json_error(__('La fecha no puede ser anterior a hoy.', 'eventos-probolsas'));
        }
        
        // Verificar si hay conflictos de eventos en la misma fecha
        $existing_events = $this->db->get_events(array(
            'date_from' => $date,
            'date_to' => $date
        ));
        
        wp_send_json_success(array(
            'valid' => true,
            'formatted_date' => Eventos_Probolsas_Helpers::format_date($date),
            'day_of_week' => $datetime->format('l'),
            'existing_events' => count($existing_events),
            'message' => count($existing_events) > 0 
                ? sprintf(__('Ya hay %d evento(s) en esta fecha.', 'eventos-probolsas'), count($existing_events))
                : __('Fecha disponible.', 'eventos-probolsas')
        ));
    }
    
    /**
     * FUNCIÓN AUXILIAR: Formatear evento para respuesta básica
     */
    private function format_event_for_response($event) {
        $type_config = Eventos_Probolsas_Helpers::get_event_type_config($event['type']);
        
        return array(
            'id' => intval($event['id']),
            'title' => $event['title'],
            'type' => $event['type'],
            'type_label' => $type_config ? $type_config['label'] : $event['type'],
            'description' => $event['description'],
            'event_date' => $event['event_date'],
            'event_time' => $event['event_time'],
            'image_url' => $event['image_url'],
            'color' => $event['color'],
            'icon' => $event['icon'],
            'formatted_date' => Eventos_Probolsas_Helpers::format_date($event['event_date']),
            'formatted_time' => Eventos_Probolsas_Helpers::format_time($event['event_time']),
            'created_at' => $event['created_at'],
            'updated_at' => $event['updated_at'],
            'requires_image' => $type_config ? $type_config['requires_image'] : false
        );
    }
    
    /**
     * FUNCIÓN AUXILIAR: Formatear evento para calendario
     */
    private function format_event_for_calendar($event, $event_types) {
        return array(
            'id' => intval($event['id']),
            'title' => $event['title'],
            'type' => $event['type'],
            'description' => $event['description'],
            'event_date' => $event['event_date'],
            'event_time' => $event['event_time'],
            'image_url' => $event['image_url'],
            'color' => $event['color'],
            'icon' => $event['icon'],
            'formatted_date' => Eventos_Probolsas_Helpers::format_date($event['event_date']),
            'formatted_time' => Eventos_Probolsas_Helpers::format_time($event['event_time']),
            'type_label' => isset($event_types[$event['type']]) ? $event_types[$event['type']]['label'] : $event['type']
        );
    }
    
    /**
     * FUNCIÓN AUXILIAR: Formatear detalles completos del evento
     */
    private function format_event_details($event) {
        $type_config = Eventos_Probolsas_Helpers::get_event_type_config($event['type']);
        
        // Obtener eventos del mismo día para navegación
        $same_day_events = $this->db->get_events(array(
            'date_from' => $event['event_date'],
            'date_to' => $event['event_date']
        ));
        
        $current_index = array_search($event['id'], array_column($same_day_events, 'id'));
        
        // Obtener eventos relacionados
        $related_events = $this->get_related_events($event);
        
        return array(
            'id' => intval($event['id']),
            'title' => $event['title'],
            'type' => $event['type'],
            'type_label' => $type_config ? $type_config['label'] : $event['type'],
            'description' => $event['description'],
            'event_date' => $event['event_date'],
            'event_time' => $event['event_time'],
            'image_url' => $event['image_url'],
            'color' => $event['color'],
            'icon' => $event['icon'],
            'formatted_date' => Eventos_Probolsas_Helpers::format_date($event['event_date']),
            'formatted_time' => Eventos_Probolsas_Helpers::format_time($event['event_time']),
            'formatted_datetime' => $this->format_datetime($event['event_date'], $event['event_time']),
            'created_at' => $event['created_at'],
            'updated_at' => $event['updated_at'],
            'requires_image' => $type_config ? $type_config['requires_image'] : false,
            'share_url' => $this->get_event_share_url($event),
            'ical_url' => $this->get_event_ical_url($event),
            'navigation' => array(
                'has_prev' => $current_index > 0,
                'has_next' => $current_index < count($same_day_events) - 1,
                'prev_id' => $current_index > 0 ? $same_day_events[$current_index - 1]['id'] : null,
                'next_id' => $current_index < count($same_day_events) - 1 ? $same_day_events[$current_index + 1]['id'] : null,
                'position' => $current_index + 1,
                'total' => count($same_day_events)
            ),
            'related_events' => $related_events
        );
    }
    
    /**
     * FUNCIÓN AUXILIAR: Obtener eventos relacionados
     */
    private function get_related_events($event, $limit = 3) {
        $filters = array(
            'type' => $event['type'],
            'date_from' => current_time('Y-m-d'),
            'limit' => $limit + 1 // +1 para excluir el evento actual
        );
        
        $related = $this->db->get_events($filters);
        
        // Excluir el evento actual
        $related = array_filter($related, function($related_event) use ($event) {
            return $related_event['id'] != $event['id'];
        });
        
        // Formatear eventos relacionados
        $formatted_related = array();
        foreach (array_slice($related, 0, $limit) as $related_event) {
            $formatted_related[] = $this->format_event_for_response($related_event);
        }
        
        return $formatted_related;
    }
    
    /**
     * FUNCIÓN AUXILIAR: Calcular estadísticas del calendario
     */
    private function calculate_calendar_stats($events) {
        $today = current_time('Y-m-d');
        $next_week = date('Y-m-d', strtotime('+7 days'));
        $this_month = date('Y-m');
        
        $stats = array(
            'total_month' => count($events),
            'today' => 0,
            'next_week' => 0,
            'by_type' => array(),
            'with_image' => 0,
            'without_time' => 0
        );
        
        foreach ($events as $event) {
            // Eventos de hoy
            if ($event['event_date'] === $today) {
                $stats['today']++;
            }
            
            // Eventos de la próxima semana
            if ($event['event_date'] >= $today && $event['event_date'] <= $next_week) {
                $stats['next_week']++;
            }
            
            // Por tipo
            if (!isset($stats['by_type'][$event['type']])) {
                $stats['by_type'][$event['type']] = 0;
            }
            $stats['by_type'][$event['type']]++;
            
            // Con imagen
            if (!empty($event['image_url'])) {
                $stats['with_image']++;
            }
            
            // Sin hora específica
            if (empty($event['event_time'])) {
                $stats['without_time']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * FUNCIÓN AUXILIAR: Formatear fecha y hora combinadas
     */
    private function format_datetime($date, $time) {
        $datetime_str = $date;
        if (!empty($time)) {
            $datetime_str .= ' ' . $time;
        }
        
        $datetime = new DateTime($datetime_str);
        
        $format = get_option('date_format');
        if (!empty($time)) {
            $format .= ' ' . get_option('time_format');
        }
        
        return $datetime->format($format);
    }
    
    /**
     * FUNCIÓN AUXILIAR: Generar URL para compartir evento
     */
    private function get_event_share_url($event) {
        $current_url = home_url();
        return add_query_arg('evento_id', $event['id'], $current_url);
    }
    
    /**
     * FUNCIÓN AUXILIAR: Generar URL para descargar .ics
     */
    private function get_event_ical_url($event) {
        return add_query_arg(array(
            'action' => 'eventos_download_ical',
            'event_id' => $event['id'],
            'nonce' => wp_create_nonce('download_ical_' . $event['id'])
        ), admin_url('admin-ajax.php'));
    }
    
    /**
     * FUNCIÓN AUXILIAR: Verificar nonce
     */
    private function verify_nonce($nonce) {
        return wp_verify_nonce($nonce, 'eventos_probolsas_ajax');
    }
    
    /**
     * FUNCIÓN AUXILIAR: Sanitizar datos de evento
     */
    private function sanitize_event_data($data) {
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
        
        if (isset($data['image_attachment_id'])) {
            $sanitized['image_attachment_id'] = intval($data['image_attachment_id']);
        }
        
        return $sanitized;
    }
    
    /**
     * FUNCIÓN AUXILIAR: Validar datos de evento
     */
    private function validate_event_data($data) {
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
            $types = Eventos_Probolsas_Helpers::get_event_types();
            if (!isset($types[$data['type']])) {
                $errors[] = __('Tipo de evento no válido.', 'eventos-probolsas');
            }
        }
        
        // Fecha requerida y válida
        if (empty($data['event_date'])) {
            $errors[] = __('La fecha del evento es requerida.', 'eventos-probolsas');
        } else {
            $date = DateTime::createFromFormat('Y-m-d', $data['event_date']);
            if (!$date || $date->format('Y-m-d') !== $data['event_date']) {
                $errors[] = __('Formato de fecha no válido.', 'eventos-probolsas');
            } else {
                // Verificar que la fecha no sea del pasado
                $today = new DateTime();
                $today->setTime(0, 0, 0);
                if ($date < $today) {
                    $errors[] = __('La fecha del evento no puede ser anterior a hoy.', 'eventos-probolsas');
                }
            }
        }
        
        // Validar hora si está presente
        if (!empty($data['event_time'])) {
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $data['event_time'])) {
                $errors[] = __('Formato de hora no válido (HH:MM).', 'eventos-probolsas');
            }
        }
        
        // Validar descripción
        if (!empty($data['description']) && strlen($data['description']) > 1000) {
            $errors[] = __('La descripción no puede exceder 1000 caracteres.', 'eventos-probolsas');
        }
        
        // Validar imagen requerida para ciertos tipos
        if (!empty($data['type'])) {
            $type_config = Eventos_Probolsas_Helpers::get_event_type_config($data['type']);
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
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * FUNCIÓN AUXILIAR: Limpiar cache de eventos
     */
    private function clear_events_cache() {
        // Limpiar cache de WordPress
        wp_cache_flush_group('eventos_probolsas');
        
        // Limpiar transients relacionados
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_eventos_%' 
             OR option_name LIKE '_transient_timeout_eventos_%'"
        );
        
        // Hook para limpiar cache de terceros
        do_action('eventos_probolsas_cache_cleared');
    }
    
    /**
     * FUNCIÓN AUXILIAR: Log de actividad (para debugging y auditoría)
     */
    private function log_activity($action, $event_id = null, $details = array()) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'action' => $action,
            'event_id' => $event_id,
            'user_id' => get_current_user_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        );
        
        error_log('Eventos Probolsas Activity: ' . json_encode($log_data));
    }
    
    /**
     * FUNCIÓN AUXILIAR: Validar límites de rate limiting
     */
    private function check_rate_limit($action = 'general') {
        $user_id = get_current_user_id();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Clave para el rate limiting
        $rate_key = "eventos_rate_{$action}_{$user_id}_{$ip_address}";
        
        // Límites por acción
        $limits = array(
            'create' => array('requests' => 10, 'window' => 3600), // 10 por hora
            'update' => array('requests' => 20, 'window' => 3600), // 20 por hora
            'delete' => array('requests' => 5, 'window' => 3600),  // 5 por hora
            'search' => array('requests' => 100, 'window' => 3600), // 100 por hora
            'general' => array('requests' => 50, 'window' => 3600)  // 50 por hora
        );
        
        $limit = isset($limits[$action]) ? $limits[$action] : $limits['general'];
        
        // Obtener contador actual
        $current_count = get_transient($rate_key);
        
        if ($current_count === false) {
            // Primera solicitud en la ventana
            set_transient($rate_key, 1, $limit['window']);
            return true;
        } elseif ($current_count >= $limit['requests']) {
            // Límite excedido
            return false;
        } else {
            // Incrementar contador
            set_transient($rate_key, $current_count + 1, $limit['window']);
            return true;
        }
    }
    
    /**
     * FUNCIÓN AUXILIAR: Preparar respuesta de error estándar
     */
    private function send_error_response($message, $code = 'error', $data = array()) {
        $response = array(
            'success' => false,
            'data' => array(
                'code' => $code,
                'message' => $message,
                'timestamp' => current_time('mysql')
            )
        );
        
        if (!empty($data)) {
            $response['data'] = array_merge($response['data'], $data);
        }
        
        wp_send_json($response);
    }
    
    /**
     * FUNCIÓN AUXILIAR: Preparar respuesta de éxito estándar
     */
    private function send_success_response($data = array(), $message = '') {
        $response = array(
            'success' => true,
            'data' => array_merge(array(
                'timestamp' => current_time('mysql')
            ), $data)
        );
        
        if (!empty($message)) {
            $response['data']['message'] = $message;
        }
        
        wp_send_json($response);
    }
    
    /**
     * FUNCIÓN AUXILIAR: Sanitizar filtros de búsqueda
     */
    private function sanitize_search_filters($filters) {
        $sanitized = array();
        
        if (isset($filters['search'])) {
            $sanitized['search'] = sanitize_text_field($filters['search']);
        }
        
        if (isset($filters['type'])) {
            $sanitized['type'] = sanitize_key($filters['type']);
        }
        
        if (isset($filters['date_from'])) {
            $date = sanitize_text_field($filters['date_from']);
            if (DateTime::createFromFormat('Y-m-d', $date)) {
                $sanitized['date_from'] = $date;
            }
        }
        
        if (isset($filters['date_to'])) {
            $date = sanitize_text_field($filters['date_to']);
            if (DateTime::createFromFormat('Y-m-d', $date)) {
                $sanitized['date_to'] = $date;
            }
        }
        
        if (isset($filters['limit'])) {
            $sanitized['limit'] = max(1, min(100, intval($filters['limit'])));
        }
        
        if (isset($filters['offset'])) {
            $sanitized['offset'] = max(0, intval($filters['offset']));
        }
        
        return $sanitized;
    }
    
    /**
     * FUNCIÓN DE UTILIDAD: Obtener información del navegador para logging
     */
    private function get_browser_info() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Detectar navegador básico
        if (strpos($user_agent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($user_agent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($user_agent, 'Safari') !== false) {
            $browser = 'Safari';
        } elseif (strpos($user_agent, 'Edge') !== false) {
            $browser = 'Edge';
        } else {
            $browser = 'Unknown';
        }
        
        return array(
            'browser' => $browser,
            'user_agent' => $user_agent,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? ''
        );
    }
    
    /**
     * Upload de imagen a biblioteca de medios de WordPress
     */
    public function ajax_upload_image() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'eventos_probolsas_ajax')) {
            wp_send_json_error(__('Error de seguridad.', 'eventos-probolsas'));
        }
        
        // Verificar permisos
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('No tienes permisos para subir archivos.', 'eventos-probolsas'));
        }
        
        // Verificar que se haya subido un archivo
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('No se recibió ningún archivo o hubo un error en la subida.', 'eventos-probolsas'));
        }
        
        $file = $_FILES['image'];
        
        // Validar tipo de archivo
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
        $file_type = wp_check_filetype($file['name'], null);
        
        if (!in_array($file['type'], $allowed_types) && !in_array($file_type['type'], $allowed_types)) {
            wp_send_json_error(__('Tipo de archivo no permitido. Solo se permiten JPG, PNG y GIF.', 'eventos-probolsas'));
        }
        
        // Validar tamaño (máximo 5MB)
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_size) {
            wp_send_json_error(__('El archivo es demasiado grande. Máximo 5MB permitido.', 'eventos-probolsas'));
        }
        
        // Incluir funciones necesarias para el upload
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        if (!function_exists('wp_insert_attachment')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }
        
        // Configurar filtros de upload para eventos
        $upload_overrides = array(
            'test_form' => false,
            'test_size' => true,
            'test_upload' => true,
            'mimes' => array(
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'gif'  => 'image/gif'
            )
        );
        
        // Subir archivo a la biblioteca de medios
        $movefile = wp_handle_upload($file, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            // Preparar datos del attachment
            $attachment = array(
                'guid'           => $movefile['url'],
                'post_mime_type' => $movefile['type'],
                'post_title'     => sanitize_file_name(pathinfo($movefile['file'], PATHINFO_FILENAME)),
                'post_content'   => '',
                'post_status'    => 'inherit'
            );
            
            // Insertar attachment en la base de datos
            $attachment_id = wp_insert_attachment($attachment, $movefile['file']);
            
            if ($attachment_id) {
                // Generar metadatos del attachment
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $movefile['file']);
                wp_update_attachment_metadata($attachment_id, $attachment_data);
                
                // Obtener diferentes tamaños de imagen
                $image_attributes = wp_get_attachment_image_src($attachment_id, 'full');
                $image_medium = wp_get_attachment_image_src($attachment_id, 'medium');
                $image_thumbnail = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                
                wp_send_json_success(array(
                    'message' => __('Imagen subida exitosamente.', 'eventos-probolsas'),
                    'attachment_id' => $attachment_id,
                    'url' => $image_attributes[0],
                    'width' => $image_attributes[1],
                    'height' => $image_attributes[2],
                    'medium_url' => $image_medium ? $image_medium[0] : $image_attributes[0],
                    'thumbnail_url' => $image_thumbnail ? $image_thumbnail[0] : $image_attributes[0],
                    'alt_text' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
                    'title' => get_the_title($attachment_id),
                    'filename' => basename($movefile['file']),
                    'file_size' => size_format(filesize($movefile['file'])),
                    'mime_type' => $movefile['type']
                ));
            } else {
                wp_send_json_error(__('Error al crear el attachment en la base de datos.', 'eventos-probolsas'));
            }
        } else {
            $error_message = isset($movefile['error']) ? $movefile['error'] : __('Error desconocido al subir el archivo.', 'eventos-probolsas');
            wp_send_json_error($error_message);
        }
    }
    
    /**
     * Eliminar imagen de la biblioteca de medios
     */
    public function ajax_delete_image() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'eventos_probolsas_ajax')) {
            wp_send_json_error(__('Error de seguridad.', 'eventos-probolsas'));
        }
        
        // Verificar permisos
        if (!current_user_can('delete_posts')) {
            wp_send_json_error(__('No tienes permisos para eliminar archivos.', 'eventos-probolsas'));
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        if (!$attachment_id) {
            wp_send_json_error(__('ID de imagen no válido.', 'eventos-probolsas'));
        }
        
        // Verificar que el attachment existe y es una imagen
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            wp_send_json_error(__('Imagen no encontrada.', 'eventos-probolsas'));
        }
        
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(__('El archivo no es una imagen.', 'eventos-probolsas'));
        }
        
        // Verificar que la imagen no esté siendo usada por otros eventos
        global $wpdb;
        $table_name = $wpdb->prefix . 'eventos_probolsas';
        
        $usage_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE image_attachment_id = %d",
            $attachment_id
        ));
        
        if ($usage_count > 1) {
            wp_send_json_error(__('Esta imagen está siendo utilizada por otros eventos y no se puede eliminar.', 'eventos-probolsas'));
        }
        
        // Eliminar attachment
        if (wp_delete_attachment($attachment_id, true)) {
            wp_send_json_success(array(
                'message' => __('Imagen eliminada exitosamente.', 'eventos-probolsas'),
                'attachment_id' => $attachment_id
            ));
        } else {
            wp_send_json_error(__('Error al eliminar la imagen.', 'eventos-probolsas'));
        }
    }
}