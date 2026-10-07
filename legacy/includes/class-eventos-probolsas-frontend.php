<?php
/**
 * Clase para funcionalidades del frontend - VERSIÓN MEJORADA
 */

if (!defined('ABSPATH')) {
    exit;
}

class Eventos_Probolsas_Frontend {
    
    private $db;
    
    public function __construct() {
        $this->db = new Eventos_Probolsas_DB();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // Shortcodes principales
        add_shortcode('eventos_probolsas', array($this, 'calendar_shortcode'));
        add_shortcode('eventos_calendario', array($this, 'calendar_shortcode'));
        add_shortcode('eventos_lista', array($this, 'list_shortcode'));
        
        // Shortcodes adicionales
        add_shortcode('eventos_proximos', array($this, 'upcoming_events_shortcode'));
        add_shortcode('eventos_widget', array($this, 'widget_shortcode'));
        
        // AJAX para frontend público - MEJORADOS
        add_action('wp_ajax_nopriv_eventos_get_calendar_data', array($this, 'ajax_get_calendar_data'));
        add_action('wp_ajax_eventos_get_calendar_data', array($this, 'ajax_get_calendar_data'));
        add_action('wp_ajax_nopriv_eventos_get_event_details', array($this, 'ajax_get_event_details'));
        add_action('wp_ajax_eventos_get_event_details', array($this, 'ajax_get_event_details'));
        
        // Hooks para integración con temas
        add_action('wp_head', array($this, 'add_frontend_meta'));
        add_filter('body_class', array($this, 'add_body_classes'));
        
        // Hook para manejo de descarga iCal
        add_action('init', array($this, 'handle_ical_download'));
    }
        
    /**
     * Cargar scripts y estilos del frontend - MEJORADO
     */
    public function enqueue_frontend_scripts() {
        
        if (!$this->should_load_assets()) {
            return;
        }
        
        // Verificar que las constantes existan
        if (!defined('EVENTOS_PROBOLSAS_PLUGIN_URL')) {
            return;
        }
        
        // CSS Principal
        wp_enqueue_style(
            'eventos-probolsas-frontend',
            EVENTOS_PROBOLSAS_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            EVENTOS_PROBOLSAS_VERSION
        );
        
        // Bootstrap CSS
        if (!wp_style_is('bootstrap', 'enqueued') && !wp_style_is('bootstrap5', 'enqueued')) {
            wp_enqueue_style(
                'eventos-bootstrap',
                'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css',
                array(),
                '5.3.0'
            );
        }
        
        // Font Awesome
        if (!wp_style_is('font-awesome', 'enqueued') && !wp_style_is('fontawesome', 'enqueued')) {
            wp_enqueue_style(
                'eventos-font-awesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
                array(),
                '6.4.0'
            );
        }
        
        // JavaScript Principal
        wp_enqueue_script(
            'eventos-probolsas-frontend',
            EVENTOS_PROBOLSAS_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            EVENTOS_PROBOLSAS_VERSION,
            true
        );
        
        // Bootstrap JS
        if (!wp_script_is('bootstrap', 'enqueued') && !wp_script_is('bootstrap5', 'enqueued')) {
            wp_enqueue_script(
                'eventos-bootstrap',
                'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js',
                array('jquery'),
                '5.3.0',
                true
            );
        }
        
        // Verificar que Helpers exista antes de usarlo
        if (!class_exists('Eventos_Probolsas_Helpers')) {
            return;
        }
        
        // Localizar script
        wp_localize_script('eventos-probolsas-frontend', 'eventosAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => Eventos_Probolsas_Helpers::get_ajax_nonce(),
            'plugin_url' => EVENTOS_PROBOLSAS_PLUGIN_URL,
            'strings' => array(
                'loading' => __('Cargando...', 'eventos-probolsas'),
                'no_events' => __('No hay eventos para mostrar.', 'eventos-probolsas'),
                'error' => __('Ha ocurrido un error. Intenta nuevamente.', 'eventos-probolsas'),
                'close' => __('Cerrar', 'eventos-probolsas'),
                'previous' => __('Anterior', 'eventos-probolsas'),
                'next' => __('Siguiente', 'eventos-probolsas'),
                'today' => __('Hoy', 'eventos-probolsas'),
                'view_details' => __('Ver detalles', 'eventos-probolsas'),
                'share_copied' => __('Enlace copiado al portapapeles', 'eventos-probolsas'),
                'filters_applied' => __('Filtros aplicados', 'eventos-probolsas'),
                'no_results' => __('No se encontraron eventos con los filtros aplicados', 'eventos-probolsas')
            ),
            'months' => Eventos_Probolsas_Helpers::get_month_names(),
            'days' => Eventos_Probolsas_Helpers::get_day_names(),
            'event_types' => Eventos_Probolsas_Helpers::get_event_types(),
            'settings' => array(
                'date_format' => get_option('date_format'),
                'time_format' => get_option('time_format'),
                'start_of_week' => get_option('start_of_week'),
                'timezone' => get_option('timezone_string') ?: 'America/Bogota'
            )
        ));
        
        // Añadir estilos inline personalizados
        $this->add_inline_styles();
    }
    
    /**
     * Determinar si debemos cargar los assets
     */
    private function should_load_assets() {
        global $post;
        
        if (is_a($post, 'WP_Post')) {
            $shortcodes = array('eventos_probolsas', 'eventos_calendario', 'eventos_lista');
            foreach ($shortcodes as $shortcode) {
                if (has_shortcode($post->post_content, $shortcode)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Añadir estilos inline personalizados
     */
    private function add_inline_styles() {
        $custom_css = '';
        
        // Permitir personalización de colores
        $primary_color = get_option('eventos_primary_color', '#007bff');
        $border_radius = get_option('eventos_border_radius', '8px');
        
        if ($primary_color !== '#007bff') {
            $custom_css .= "
                .eventos-calendar-container .btn-eventos-primary,
                .calendar-day.today {
                    background-color: {$primary_color} !important;
                    border-color: {$primary_color} !important;
                }
            ";
        }
        
        if ($border_radius !== '8px') {
            $custom_css .= "
                .eventos-calendar-container,
                .evento-card,
                .calendar-grid {
                    border-radius: {$border_radius} !important;
                }
            ";
        }
        
        // Agregar CSS personalizado del usuario
        $user_css = get_option('eventos_custom_css', '');
        if (!empty($user_css)) {
            $custom_css .= "\n" . $user_css;
        }
        
        if (!empty($custom_css)) {
            wp_add_inline_style('eventos-probolsas-frontend', $custom_css);
        }
    }
    
    /**
     * Shortcode principal para mostrar calendario - MEJORADO
     */
    public function calendar_shortcode($atts) {
        // Atributos por defecto mejorados
        $atts = shortcode_atts(array(
            'year' => date('Y'),
            'month' => date('n'),
            'show_filters' => 'true',
            'show_search' => 'true',
            'show_navigation' => 'true',
            'show_legend' => 'true',
            'show_stats' => 'false',
            'height' => 'auto',
            'theme' => 'default',
            'types' => '', // Filtrar por tipos específicos
            'limit' => '0', // Límite de eventos por día
            'view' => 'month', // month, week, list
            'auto_refresh' => 'false', // Auto-actualizar cada X minutos
            'compact_mode' => 'false' // Modo compacto para espacios reducidos
        ), $atts, 'eventos_calendario');
        
        // Validar y sanitizar atributos
        $atts['year'] = intval($atts['year']);
        $atts['month'] = intval($atts['month']);
        $atts['show_filters'] = $atts['show_filters'] === 'true';
        $atts['show_search'] = $atts['show_search'] === 'true';
        $atts['show_navigation'] = $atts['show_navigation'] === 'true';
        $atts['show_legend'] = $atts['show_legend'] === 'true';
        $atts['show_stats'] = $atts['show_stats'] === 'true';
        $atts['limit'] = intval($atts['limit']);
        $atts['auto_refresh'] = $atts['auto_refresh'] === 'true';
        $atts['compact_mode'] = $atts['compact_mode'] === 'true';
        
        // Validar año y mes
        if ($atts['year'] < 1900 || $atts['year'] > 2100) {
            $atts['year'] = date('Y');
        }
        if ($atts['month'] < 1 || $atts['month'] > 12) {
            $atts['month'] = date('n');
        }
        
        // Generar ID único para este calendario
        $calendar_id = 'eventos-calendar-' . wp_generate_password(8, false);
        
        ob_start();
        
        // Contenedor principal con clases personalizadas
        $container_classes = array('eventos-calendar-wrapper');
        if (!empty($atts['theme']) && $atts['theme'] !== 'default') {
            $container_classes[] = 'theme-' . sanitize_html_class($atts['theme']);
        }
        if ($atts['compact_mode']) {
            $container_classes[] = 'compact-mode';
        }
        
        echo '<div class="' . implode(' ', $container_classes) . '" id="' . esc_attr($calendar_id) . '">';
        
        // Incluir template del calendario
        include EVENTOS_PROBOLSAS_PLUGIN_PATH . 'includes/templates/frontend-calendar.php';
        
        echo '</div>';
        
        // Script de inicialización específico para este calendario
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof EventosCalendar !== 'undefined') {
                const options = {
                    container: '#<?php echo esc_js($calendar_id); ?>',
                    year: <?php echo $atts['year']; ?>,
                    month: <?php echo $atts['month']; ?>,
                    showFilters: <?php echo $atts['show_filters'] ? 'true' : 'false'; ?>,
                    showSearch: <?php echo $atts['show_search'] ? 'true' : 'false'; ?>,
                    showNavigation: <?php echo $atts['show_navigation'] ? 'true' : 'false'; ?>,
                    showLegend: <?php echo $atts['show_legend'] ? 'true' : 'false'; ?>,
                    showStats: <?php echo $atts['show_stats'] ? 'true' : 'false'; ?>,
                    limit: <?php echo $atts['limit']; ?>,
                    view: '<?php echo esc_js($atts['view']); ?>',
                    types: '<?php echo esc_js($atts['types']); ?>',
                    height: '<?php echo esc_js($atts['height']); ?>',
                    autoRefresh: <?php echo $atts['auto_refresh'] ? 'true' : 'false'; ?>,
                    compactMode: <?php echo $atts['compact_mode'] ? 'true' : 'false'; ?>
                };
                
                new EventosCalendar(options);
                
                <?php if ($atts['auto_refresh']): ?>
                // Auto-refresh cada 5 minutos si está habilitado
                setInterval(function() {
                    if (window.eventosCalendarInstance && window.eventosCalendarInstance.loadCalendar) {
                        window.eventosCalendarInstance.loadCalendar();
                    }
                }, 300000); 
                <?php endif; ?>
            }
        });
        </script>
        <?php
        
        include EVENTOS_PROBOLSAS_PLUGIN_PATH . 'includes/templates/frontend-modal.php';
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode para mostrar lista de eventos
     */
    public function list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => '10',
            'type' => '',
            'show_filters' => 'false',
            'show_search' => 'false',
            'upcoming_only' => 'true',
            'show_images' => 'true',
            'show_excerpt' => 'true',
            'excerpt_length' => '15',
            'columns' => '3',
            'layout' => 'card', // card, list, compact
            'order' => 'ASC', // ASC, DESC
            'orderby' => 'event_date', // event_date, title, created_at
            'date_from' => '',
            'date_to' => '',
            'pagination' => 'false',
            'group_by_month' => 'false' // Agrupar por mes
        ), $atts, 'eventos_lista');
        
        // Preparar filtros
        $filters = array(
            'date_from' => current_time('Y-m-d'), // Forzar eventos futuros
            'orderby' => 'event_date',
            'order' => 'ASC'
        );
        
        if (!empty($atts['type'])) {
            $filters['type'] = sanitize_text_field($atts['type']);
        }
        
        // Obtener eventos 
        $events = $this->db->get_events($filters);
        $events = array_slice($events, 0, 6);
        
        $event_types = Eventos_Probolsas_Helpers::get_event_types();
        
        // Generar ID único
        $list_id = 'eventos-lista-' . wp_generate_password(8, false);
        
        ob_start();
        
        echo '<div class="eventos-lista-wrapper" id="' . esc_attr($list_id) . '">';
        
        // Lista de eventos
        echo '<div class="eventos-lista" data-layout="' . esc_attr($atts['layout']) . '">';
        
        if (empty($events)) {
            echo '<div class="alert alert-info text-center">';
            echo '<i class="fas fa-calendar-times fa-2x mb-3"></i>';
            echo '<h1><i class="fa-solid fa-list"></i> La lista no se puedo cargar</h1>';
            echo '<h5>' . __('No hay eventos próximos', 'eventos-probolsas') . '</h5>';
            echo '<p class="text-muted">' . __('No se encontraron eventos próximos disponibles.', 'eventos-probolsas') . '</p>';
            echo '</div>';
        } else {
            $this->render_events_list($events, $atts, $event_types);
        }
        
        echo '</div>';
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * NUEVA FUNCIÓN: Renderizar eventos agrupados por mes
     */
    private function render_events_grouped_by_month($events, $atts, $event_types) {
        $grouped_events = array();
        
        // Agrupar eventos por mes
        foreach ($events as $event) {
            $month_key = date('Y-m', strtotime($event['event_date']));
            $month_name = Eventos_Probolsas_Helpers::get_month_names()[intval(date('n', strtotime($event['event_date'])))];
            $year = date('Y', strtotime($event['event_date']));
            
            if (!isset($grouped_events[$month_key])) {
                $grouped_events[$month_key] = array(
                    'name' => $month_name . ' ' . $year,
                    'events' => array()
                );
            }
            
            $grouped_events[$month_key]['events'][] = $event;
        }
        
        // Renderizar cada grupo
        foreach ($grouped_events as $month_key => $group) {
            echo '<div class="eventos-month-group mb-4">';
            echo '<h4 class="eventos-month-title border-bottom pb-2 mb-3">';
            echo '<i class="fas fa-calendar-alt me-2"></i>';
            echo esc_html($group['name']);
            echo '<span class="badge bg-primary ms-2">' . count($group['events']) . '</span>';
            echo '</h4>';
            
            $this->render_events_list($group['events'], $atts, $event_types);
            
            echo '</div>';
        }
    }
    
    /**
     * Renderizar lista de eventos - MEJORADO
     */
    private function render_events_list($events, $atts, $event_types) {
        $layout = $atts['layout'];
        $columns = intval($atts['columns']);
        
        if ($layout === 'card') {
            echo '<div class="row">';
            $col_class = 'col-md-' . (12 / max(1, min(4, $columns)));
        }
        
        foreach ($events as $event) {
            $type_config = Eventos_Probolsas_Helpers::get_event_type_config($event['type']);
            
            if ($layout === 'card') {
                echo '<div class="' . $col_class . ' mb-3">';
                $this->render_event_card($event, $atts, $type_config);
                echo '</div>';
            } elseif ($layout === 'list') {
                $this->render_event_list_item($event, $atts, $type_config);
            } else { // compact
                $this->render_event_compact($event, $atts, $type_config);
            }
        }
        
        if ($layout === 'card') {
            echo '</div>';
        }
    }
    
    /**
     * Renderizar evento como tarjeta
     */
    private function render_event_card($event, $atts, $type_config) {
        echo '<div class="card evento-card h-100" style="border-left: 4px solid ' . esc_attr($event['color']) . ';">';

    if ($atts['show_images'] === 'true' && !empty($event['image_url'])) {
        // Contenedor con aspect ratio 16:9 o 4:3
        echo '<div class="evento-card-image-wrapper" style="position: relative; width: 100%; padding-top: 150%; overflow: hidden;">';
        echo '<img src="' . esc_url($event['image_url']) . '" class="card-img-top evento-image" alt="' . esc_attr($event['title']) . '" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; object-position: center;">';
        echo '</div>';
    }
        echo '<div class="card-body">';
        echo '<div class="d-flex align-items-center mb-2">';
        echo '<span class="badge me-2" style="background-color: ' . esc_attr($event['color']) . ';">';
        echo '<i class="' . esc_attr($event['icon']) . '"></i> ' . esc_html($type_config['label']);
        echo '</span>';
        echo '</div>';
        
        echo '<h5 class="card-title">' . esc_html($event['title']) . '</h5>';
        
        echo '<p class="card-text text-muted">';
        echo '<i class="fas fa-calendar me-1"></i>' . esc_html(Eventos_Probolsas_Helpers::format_date($event['event_date']));
        if (!empty($event['event_time'])) {
            echo '<br><i class="fas fa-clock me-1"></i>' . esc_html(Eventos_Probolsas_Helpers::format_time($event['event_time']));
        }
        echo '</p>';
        
        if ($atts['show_excerpt'] === 'true' && !empty($event['description'])) {
            echo '<p class="card-text">' . esc_html(wp_trim_words($event['description'], intval($atts['excerpt_length']))) . '</p>';
        }
        
        echo '</div>';
        
        echo '<div class="card-footer">';
        echo '<button type="button" class="btn btn-primary btn-sm evento-details-btn" data-event-id="' . esc_attr($event['id']) . '" onclick="showEventModal(' . esc_attr($event['id']) . ')">';
        echo '<i class="fas fa-eye me-1"></i>' . __('Ver detalles', 'eventos-probolsas');
        echo '</button>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Renderizar evento como ítem de lista - MEJORADO
     */
    private function render_event_list_item($event, $atts, $type_config) {
        echo '<div class="evento-list-item d-flex align-items-center mb-3 p-3 border rounded evento-clickable" data-event-id="' . esc_attr($event['id']) . '" style="cursor: pointer;">';
        
        if ($atts['show_images'] === 'true' && !empty($event['image_url'])) {
            echo '<img src="' . esc_url($event['image_url']) . '" class="evento-thumbnail me-3" width="80" height="80" style="border-radius: 8px; object-fit: cover;">';
        }
        
        echo '<div class="evento-icon me-3" style="background-color: ' . esc_attr($event['color']) . '; width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">';
        echo '<i class="' . esc_attr($event['icon']) . ' text-white"></i>';
        echo '</div>';
        
        echo '<div class="flex-grow-1">';
        echo '<h6 class="mb-1">' . esc_html($event['title']) . '</h6>';
        echo '<p class="text-muted mb-1">';
        echo '<span class="badge" style="background-color: ' . esc_attr($event['color']) . ';">' . esc_html($type_config['label']) . '</span>';
        echo ' <i class="fas fa-calendar ms-2 me-1"></i>' . esc_html(Eventos_Probolsas_Helpers::format_date($event['event_date']));
        if (!empty($event['event_time'])) {
            echo ' <i class="fas fa-clock ms-2 me-1"></i>' . esc_html(Eventos_Probolsas_Helpers::format_time($event['event_time']));
        }
        echo '</p>';
        
        if ($atts['show_excerpt'] === 'true' && !empty($event['description'])) {
            echo '<p class="text-muted mb-0 small">' . esc_html(wp_trim_words($event['description'], intval($atts['excerpt_length']))) . '</p>';
        }
        echo '</div>';
        
        echo '<div>';
        echo '<button type="button" class="btn btn-outline-primary btn-sm evento-details-btn" data-event-id="' . esc_attr($event['id']) . '">';
        echo '<i class="fas fa-eye"></i>';
        echo '</button>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Renderizar evento compacto - MEJORADO
     */
    private function render_event_compact($event, $atts, $type_config) {
        echo '<div class="evento-compact-item d-flex align-items-center mb-2 p-2 border-start evento-clickable" style="border-left-color: ' . esc_attr($event['color']) . ' !important; border-left-width: 4px !important; cursor: pointer;" data-event-id="' . esc_attr($event['id']) . '">';
        
        echo '<div class="evento-date me-3 text-center" style="min-width: 60px;">';
        echo '<div class="fw-bold">' . date('d', strtotime($event['event_date'])) . '</div>';
        echo '<div class="small text-muted">' . date('M', strtotime($event['event_date'])) . '</div>';
        echo '</div>';
        
        echo '<div class="flex-grow-1">';
        echo '<h6 class="mb-0">' . esc_html($event['title']) . '</h6>';
        echo '<small class="text-muted">';
        echo '<i class="' . esc_attr($event['icon']) . ' me-1"></i>' . esc_html($type_config['label']);
        if (!empty($event['event_time'])) {
            echo ' • ' . esc_html(Eventos_Probolsas_Helpers::format_time($event['event_time']));
        }
        echo '</small>';
        echo '</div>';
        
        echo '<button type="button" class="btn btn-sm btn-outline-primary evento-details-btn" data-event-id="' . esc_attr($event['id']) . '">';
        echo '<i class="fas fa-eye"></i>';
        echo '</button>';
        
        echo '</div>';
    }
    
    /**
     * Shortcode para próximos eventos - MEJORADO
     */
    public function upcoming_events_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => '5',
            'days' => '30',
            'show_date' => 'true',
            'show_time' => 'true',
            'show_type' => 'true',
            'layout' => 'compact',
            'title' => __('Próximos Eventos', 'eventos-probolsas'),
            'show_empty_message' => 'true'
        ), $atts, 'eventos_proximos');
        
        $filters = array(
            'date_from' => current_time('Y-m-d'),
            'date_to' => date('Y-m-d', strtotime('+' . intval($atts['days']) . ' days'))
        );
        
        $events = $this->db->get_events($filters);
        $events = array_slice($events, 0, intval($atts['limit']));
        
        ob_start();
        
        echo '<div class="eventos-proximos-widget">';
        
        if (!empty($atts['title'])) {
            echo '<h4 class="widget-title"><i class="fas fa-calendar-check me-2"></i>' . esc_html($atts['title']) . '</h4>';
        }
        
        if (empty($events)) {
            if ($atts['show_empty_message'] === 'true') {
                echo '<p class="text-muted">' . __('No hay eventos próximos.', 'eventos-probolsas') . '</p>';
            }
        } else {
            echo '<ul class="eventos-proximos-list">';
            foreach ($events as $event) {
                $type_config = Eventos_Probolsas_Helpers::get_event_type_config($event['type']);
                
                echo '<li class="evento-proximo-item evento-clickable" data-event-id="' . esc_attr($event['id']) . '" style="cursor: pointer;">';
                echo '<div class="d-flex align-items-center">';
                
                if ($atts['show_type'] === 'true') {
                    echo '<div class="evento-icon me-2" style="background-color: ' . esc_attr($event['color']) . ';">';
                    echo '<i class="' . esc_attr($event['icon']) . '"></i>';
                    echo '</div>';
                }
                
                echo '<div class="evento-info flex-grow-1">';
                echo '<h6 class="mb-1">' . esc_html($event['title']) . '</h6>';
                
                if ($atts['show_date'] === 'true') {
                    echo '<small class="text-muted">';
                    echo '<i class="fas fa-calendar me-1"></i>';
                    echo esc_html(Eventos_Probolsas_Helpers::format_date($event['event_date']));
                    
                    if ($atts['show_time'] === 'true' && !empty($event['event_time'])) {
                        echo ' <i class="fas fa-clock ms-2 me-1"></i>';
                        echo esc_html(Eventos_Probolsas_Helpers::format_time($event['event_time']));
                    }
                    echo '</small>';
                }
                
                echo '</div>';
                echo '</div>';
                echo '</li>';
            }
            echo '</ul>';
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode para widget compacto - MEJORADO
     */
    public function widget_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Eventos', 'eventos-probolsas'),
            'limit' => '3',
            'type' => '',
            'upcoming_only' => 'true',
            'show_link' => 'true',
            'link_text' => __('Ver todos los eventos', 'eventos-probolsas'),
            'link_url' => '',
            'show_count' => 'true'
        ), $atts, 'eventos_widget');
        
        $filters = array();
        
        if (!empty($atts['type'])) {
            $filters['type'] = $atts['type'];
        }
        
        if ($atts['upcoming_only'] === 'true') {
            $filters['date_from'] = current_time('Y-m-d');
        }
        
        $events = $this->db->get_events($filters);
        $total_events = count($events);
        $events = array_slice($events, 0, intval($atts['limit']));
        
        ob_start();
        
        echo '<div class="eventos-widget">';
        
        if (!empty($atts['title'])) {
            echo '<h4 class="widget-title">';
            echo esc_html($atts['title']);
            if ($atts['show_count'] === 'true' && $total_events > 0) {
                echo ' <span class="badge bg-primary ms-2">' . $total_events . '</span>';
            }
            echo '</h4>';
        }
        
        if (empty($events)) {
            echo '<p class="text-muted">' . __('No hay eventos disponibles.', 'eventos-probolsas') . '</p>';
        } else {
            echo '<div class="eventos-widget-list">';
            foreach ($events as $event) {
                echo '<div class="evento-widget-item evento-clickable" data-event-id="' . esc_attr($event['id']) . '" style="cursor: pointer;">';
                echo '<div class="evento-widget-date">';
                echo '<span class="day">' . date('d', strtotime($event['event_date'])) . '</span>';
                echo '<span class="month">' . date('M', strtotime($event['event_date'])) . '</span>';
                echo '</div>';
                echo '<div class="evento-widget-info">';
                echo '<h6>' . esc_html($event['title']) . '</h6>';
                if (!empty($event['event_time'])) {
                    echo '<small class="text-muted">' . esc_html(Eventos_Probolsas_Helpers::format_time($event['event_time'])) . '</small>';
                }
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        }
        
        if ($atts['show_link'] === 'true' && !empty($atts['link_text'])) {
            $link_url = !empty($atts['link_url']) ? $atts['link_url'] : '#';
            echo '<div class="eventos-widget-footer">';
            echo '<a href="' . esc_url($link_url) . '" class="btn btn-outline-primary btn-sm">';
            echo esc_html($atts['link_text']);
            echo '</a>';
            echo '</div>';
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Renderizar filtros para lista
     */
    private function render_list_filters($atts, $event_types) {
        echo '<div class="eventos-filters mb-4">';
        echo '<div class="row g-3">';
        
        if ($atts['show_search'] === 'true') {
            echo '<div class="col-md-4">';
            echo '<input type="text" id="eventos-search" class="form-control" placeholder="' . __('Buscar eventos...', 'eventos-probolsas') . '">';
            echo '</div>';
        }
        
        if ($atts['show_filters'] === 'true') {
            echo '<div class="col-md-3">';
            echo '<select id="eventos-type-filter" class="form-select">';
            echo '<option value="">' . __('Todos los tipos', 'eventos-probolsas') . '</option>';
            foreach ($event_types as $type => $config) {
                echo '<option value="' . esc_attr($type) . '">' . esc_html($config['label']) . '</option>';
            }
            echo '</select>';
            echo '</div>';
            
            echo '<div class="col-md-2">';
            echo '<input type="date" id="eventos-date-from" class="form-control" placeholder="' . __('Desde', 'eventos-probolsas') . '">';
            echo '</div>';
            
            echo '<div class="col-md-2">';
            echo '<input type="date" id="eventos-date-to" class="form-control" placeholder="' . __('Hasta', 'eventos-probolsas') . '">';
            echo '</div>';
            
            echo '<div class="col-md-1">';
            echo '<button type="button" id="eventos-clear-filters" class="btn btn-outline-secondary" data-tooltip="' . __('Limpiar filtros', 'eventos-probolsas') . '">';
            echo '<i class="fas fa-times"></i>';
            echo '</button>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * AJAX: Obtener datos del calendario - MEJORADO
     */
    public function ajax_get_calendar_data() {
        // Permitir acceso sin login para frontend público
        $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
        $month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
        
        // Validar año y mes
        if ($year < 1900 || $year > 2100 || $month < 1 || $month > 12) {
            wp_send_json_error(__('Año o mes no válido.', 'eventos-probolsas'));
        }
        
        // Obtener eventos del mes
        $events = $this->db->get_events_by_month($year, $month);
        
        // Organizar eventos por fecha
        $calendar_events = array();
        $event_types = Eventos_Probolsas_Helpers::get_event_types();
        
        foreach ($events as $event) {
            $day = date('j', strtotime($event['event_date']));
            
            if (!isset($calendar_events[$day])) {
                $calendar_events[$day] = array();
            }
            
            $formatted_event = array(
                'id' => $event['id'],
                'title' => $event['title'],
                'type' => $event['type'],
                'description' => $event['description'],
                'event_date' => $event['event_date'],
                'event_time' => $event['event_time'],
                'image_url' => $event['image_url'],
                'color' => $event['color'],
                'icon' => $event['icon'],
                'formatted_date' => Eventos_Probolsas_Helpers::format_date($event['event_date']),
                'formatted_time' => Eventos_Probolsas_Helpers::format_time($event['event_time'])
            );
            
            if (isset($event_types[$event['type']])) {
                $formatted_event['type_label'] = $event_types[$event['type']]['label'];
            }
            
            $calendar_events[$day][] = $formatted_event;
        }
        
        // Generar información del calendario
        $first_day_of_month = mktime(0, 0, 0, $month, 1, $year);
        $days_in_month = date('t', $first_day_of_month);
        $first_day_of_week = date('w', $first_day_of_month);
        
        // Ajustar primer día según configuración de WordPress
        $start_of_week = get_option('start_of_week', 0);
        $first_day_of_week = ($first_day_of_week - $start_of_week + 7) % 7;
        
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
            'stats' => $this->get_calendar_stats($events) // NUEVO: Estadísticas del mes
        );
        
        wp_send_json_success($calendar_info);
    }
    
    /**
     * NUEVA FUNCIÓN: Obtener estadísticas del calendario
     */
    private function get_calendar_stats($events) {
        $today = current_time('Y-m-d');
        $next_week = date('Y-m-d', strtotime('+7 days'));
        
        $stats = array(
            'total_month' => count($events),
            'today' => 0,
            'next_week' => 0,
            'by_type' => array()
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
        }
        
        return $stats;
    }
    
    /**
     * AJAX: Obtener detalles de un evento - MEJORADO
     */
    public function ajax_get_event_details() {
        // Permitir acceso sin login para frontend público
        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        
        if (!$event_id) {
            wp_send_json_error(__('ID de evento no válido.', 'eventos-probolsas'));
        }
        
        $event = $this->db->get_event($event_id);
        
        if (!$event) {
            wp_send_json_error(__('Evento no encontrado.', 'eventos-probolsas'));
        }
        
        // Formatear datos para envío
        $event['formatted_date'] = Eventos_Probolsas_Helpers::format_date($event['event_date']);
        $event['formatted_time'] = Eventos_Probolsas_Helpers::format_time($event['event_time']);
        $event['formatted_datetime'] = $this->format_datetime($event['event_date'], $event['event_time']);
        
        $type_config = Eventos_Probolsas_Helpers::get_event_type_config($event['type']);
        if ($type_config) {
            $event['type_label'] = $type_config['label'];
            $event['requires_image'] = $type_config['requires_image'];
        }
        
        // Obtener eventos del mismo día para navegación
        $same_day_events = $this->get_events_by_date($event['event_date']);
        $current_index = array_search($event_id, array_column($same_day_events, 'id'));
        
        $event['navigation'] = array(
            'has_prev' => $current_index > 0,
            'has_next' => $current_index < count($same_day_events) - 1,
            'prev_id' => $current_index > 0 ? $same_day_events[$current_index - 1]['id'] : null,
            'next_id' => $current_index < count($same_day_events) - 1 ? $same_day_events[$current_index + 1]['id'] : null,
            'position' => $current_index + 1,
            'total' => count($same_day_events)
        );
        
        // Añadir metadatos adicionales
        $event['share_url'] = $this->get_event_share_url($event);
        $event['ical_url'] = $this->get_event_ical_url($event);
        
        // NUEVO: Añadir eventos relacionados
        $event['related_events'] = $this->get_related_events($event);
        
        wp_send_json_success($event);
    }
    
    /**
     * NUEVA FUNCIÓN: Obtener eventos relacionados
     */
    private function get_related_events($event, $limit = 3) {
        $filters = array(
            'type' => $event['type'],
            'date_from' => current_time('Y-m-d')
        );
        
        $related = $this->db->get_events($filters);
        
        // Excluir el evento actual
        $related = array_filter($related, function($related_event) use ($event) {
            return $related_event['id'] != $event['id'];
        });
        
        // Limitar resultados
        return array_slice($related, 0, $limit);
    }
    
    /**
     * Obtener eventos por fecha específica
     */
   private function get_events_by_date($date) {
        $filters = array(
            'date_from' => $date,
            'date_to' => $date
        );
        return $this->db->get_events($filters);
    }
    
    /**
     * Formatear fecha y hora combinadas
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
     * Generar URL para compartir evento
     */
    private function get_event_share_url($event) {
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        return add_query_arg('evento_id', $event['id'], $current_url);
    }
    
    /**
     * Generar URL para descargar .ics
     */
    private function get_event_ical_url($event) {
        return add_query_arg(array(
            'action' => 'eventos_download_ical',
            'event_id' => $event['id'],
            'nonce' => wp_create_nonce('download_ical_' . $event['id'])
        ), admin_url('admin-ajax.php'));
    }
    
    /**
     * Añadir meta tags al head
     */
    public function add_frontend_meta() {
        global $post;
        
        // Verificar si hay un evento específico en la URL
        if (isset($_GET['evento_id'])) {
            $event_id = intval($_GET['evento_id']);
            $event = $this->db->get_event($event_id);
            
            if ($event) {
                echo '<meta property="og:title" content="' . esc_attr($event['title']) . '" />' . "\n";
                echo '<meta property="og:description" content="' . esc_attr($event['description'] ?: 'Evento en el calendario') . '" />' . "\n";
                echo '<meta property="og:type" content="event" />' . "\n";
                
                if (!empty($event['image_url'])) {
                    echo '<meta property="og:image" content="' . esc_url($event['image_url']) . '" />' . "\n";
                }
                
                if (!empty($event['event_date'])) {
                    $start_time = $event['event_date'];
                    if (!empty($event['event_time'])) {
                        $start_time .= 'T' . $event['event_time'];
                    }
                    echo '<meta property="event:start_time" content="' . esc_attr($start_time) . '" />' . "\n";
                }
            }
        }
        
        // Meta tags generales para páginas con calendarios
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'eventos_probolsas')) {
            echo '<meta name="description" content="' . __('Calendario de eventos interactivo', 'eventos-probolsas') . '" />' . "\n";
            echo '<meta property="og:type" content="website" />' . "\n";
        }
    }
    
    /**
     * Añadir clases al body
     */
    public function add_body_classes($classes) {
        global $post;
        
        if (is_a($post, 'WP_Post')) {
            $shortcodes = array('eventos_probolsas', 'eventos_calendario', 'eventos_lista');
            foreach ($shortcodes as $shortcode) {
                if (has_shortcode($post->post_content, $shortcode)) {
                    $classes[] = 'has-eventos-calendar';
                    break;
                }
            }
        }
        
        return $classes;
    }
    
    /**
     * Manejar descarga de archivo iCal
     */
    public function handle_ical_download() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'eventos_download_ical') {
            return;
        }
        
        $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
        $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';
        
        if (!wp_verify_nonce($nonce, 'download_ical_' . $event_id)) {
            wp_die(__('Error de seguridad.', 'eventos-probolsas'));
        }
        
        $event = $this->db->get_event($event_id);
        if (!$event) {
            wp_die(__('Evento no encontrado.', 'eventos-probolsas'));
        }
        
        $ical_content = $this->generate_ical($event);
        
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="evento-' . $event_id . '.ics"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        echo $ical_content;
        exit;
    }
    
    /**
     * Generar contenido iCal
     */
    private function generate_ical($event) {
        $start_date = new DateTime($event['event_date'] . ' ' . ($event['event_time'] ?: '00:00:00'));
        $end_date = clone $start_date;
        $end_date->add(new DateInterval('PT1H')); // Agregar 1 hora por defecto
        
        $ical = array();
        $ical[] = 'BEGIN:VCALENDAR';
        $ical[] = 'VERSION:2.0';
        $ical[] = 'PRODID:-//Probolsas//Eventos Plugin//ES';
        $ical[] = 'CALSCALE:GREGORIAN';
        $ical[] = 'METHOD:PUBLISH';
        $ical[] = 'BEGIN:VEVENT';
        $ical[] = 'UID:evento-' . $event['id'] . '@' . parse_url(home_url(), PHP_URL_HOST);
        $ical[] = 'DTSTART:' . $start_date->format('Ymd\THis\Z');
        $ical[] = 'DTEND:' . $end_date->format('Ymd\THis\Z');
        $ical[] = 'SUMMARY:' . $this->escape_ical_text($event['title']);
        $ical[] = 'DESCRIPTION:' . $this->escape_ical_text($event['description'] ?: $event['title']);
        $ical[] = 'LOCATION:' . $this->escape_ical_text(get_bloginfo('name'));
        $ical[] = 'STATUS:CONFIRMED';
        $ical[] = 'SEQUENCE:0';
        $ical[] = 'CREATED:' . date('Ymd\THis\Z', strtotime($event['created_at']));
        $ical[] = 'LAST-MODIFIED:' . date('Ymd\THis\Z', strtotime($event['updated_at']));
        
        if (!empty($event['image_url'])) {
            $ical[] = 'ATTACH:' . $event['image_url'];
        }
        
        $ical[] = 'END:VEVENT';
        $ical[] = 'END:VCALENDAR';
        
        return implode("\r\n", $ical);
    }
    
    /**
     * Escapar texto para iCal
     */
    private function escape_ical_text($text) {
        $text = str_replace(array("\\", ";", ",", "\n", "\r"), array("\\\\", "\\;", "\\,", "\\n", ""), $text);
        return $text;
    }
}