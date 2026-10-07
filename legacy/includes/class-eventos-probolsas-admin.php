<?php
/**
 * Clase para funcionalidades del admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Eventos_Probolsas_Admin {
    
    private $db;
    
    public function __construct() {
        $this->db = new Eventos_Probolsas_DB();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_eventos_upload_image', array($this, 'ajax_upload_image'));
        
        // Procesar formularios
        add_action('admin_init', array($this, 'process_form_submissions'));
        
        // Mensajes de admin
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }
    
    /**
     * Agregar menús de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Eventos Probolsas', 'eventos-probolsas'),
            __('Eventos', 'eventos-probolsas'),
            'manage_options',
            'eventos-probolsas',
            array($this, 'admin_page'),
            'dashicons-calendar-alt',
            30
        );
        
        add_submenu_page(
            'eventos-probolsas',
            __('Todos los Eventos', 'eventos-probolsas'),
            __('Todos los Eventos', 'eventos-probolsas'),
            'manage_options',
            'eventos-probolsas',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'eventos-probolsas',
            __('Agregar Evento', 'eventos-probolsas'),
            __('Agregar Evento', 'eventos-probolsas'),
            'manage_options',
            'eventos-probolsas-add',
            array($this, 'add_event_page')
        );
        
        add_submenu_page(
            'eventos-probolsas',
            __('Configuración', 'eventos-probolsas'),
            __('Configuración', 'eventos-probolsas'),
            'manage_options',
            'eventos-probolsas-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Cargar scripts y estilos del admin
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'eventos-probolsas') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'eventos-probolsas-admin',
            EVENTOS_PROBOLSAS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            EVENTOS_PROBOLSAS_VERSION
        );
        
        // Bootstrap CSS
        wp_enqueue_style(
            'bootstrap',
            'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css',
            array(),
            '5.3.0'
        );
        
        // Font Awesome
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css',
            array(),
            '6.4.0'
        );
        
        // JavaScript
        wp_enqueue_script(
            'eventos-probolsas-admin',
            EVENTOS_PROBOLSAS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            EVENTOS_PROBOLSAS_VERSION,
            true
        );
        
        // Bootstrap JS
        wp_enqueue_script(
            'bootstrap',
            'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js',
            array('jquery'),
            '5.3.0',
            true
        );
        
        // Localizar script
        wp_localize_script('eventos-probolsas-admin', 'eventosAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => Eventos_Probolsas_Helpers::get_ajax_nonce(),
            'strings' => array(
                'confirm_delete' => __('¿Estás seguro de que deseas eliminar este evento?', 'eventos-probolsas'),
                'error' => __('Ha ocurrido un error. Intenta nuevamente.', 'eventos-probolsas'),
                'success' => __('Operación completada exitosamente.', 'eventos-probolsas'),
                'uploading' => __('Subiendo imagen...', 'eventos-probolsas'),
                'invalid_file' => __('Archivo no válido. Solo se permiten imágenes JPG, JPEG, PNG y GIF.', 'eventos-probolsas'),
                'required_fields' => __('Por favor completa todos los campos requeridos.', 'eventos-probolsas'),
                'image_required' => __('Este tipo de evento requiere una imagen.', 'eventos-probolsas'),
                'saved_successfully' => __('Evento guardado exitosamente.', 'eventos-probolsas'),
                'deleted_successfully' => __('Evento eliminado exitosamente.', 'eventos-probolsas')
            )
        ));
        
        // Media uploader
        wp_enqueue_media();
    }
    
    /**
     * Procesar formularios del admin
     */
    public function process_form_submissions() {
        // Verificar si estamos en la página correcta
        if (!isset($_GET['page']) || strpos($_GET['page'], 'eventos-probolsas') === false) {
            return;
        }
        
        // Procesar creación de evento
        if (isset($_POST['eventos_nonce']) && wp_verify_nonce($_POST['eventos_nonce'], 'eventos_save_event')) {
            $this->handle_save_event();
        }
        
        // Procesar eliminación de evento
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['event_id'])) {
            $this->handle_delete_event();
        }
    }
    
    /**
     * Manejar guardado de evento
     */
    private function handle_save_event() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            $this->set_admin_notice(__('No tienes permisos para realizar esta acción.', 'eventos-probolsas'), 'error');
            return;
        }
        
        // Sanitizar datos
        $data = $this->sanitize_form_data($_POST);
        
        // Validar datos
        $errors = $this->validate_form_data($data);
        if (!empty($errors)) {
            $this->set_admin_notice(implode('<br>', $errors), 'error');
            return;
        }
        
        // Obtener configuración del tipo
        $type_config = Eventos_Probolsas_Helpers::get_event_type_config($data['type']);
        if ($type_config) {
            $data['color'] = $type_config['color'];
            $data['icon'] = $type_config['icon'];
        }
        
        // Manejar upload de imagen
        if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
            $uploaded_url = $this->handle_image_upload($_FILES['event_image']);
            if (is_wp_error($uploaded_url)) {
                $this->set_admin_notice($uploaded_url->get_error_message(), 'error');
                return;
            }
            $data['image_url'] = $uploaded_url;
        }
        
        // Crear o actualizar evento
        if (isset($_POST['event_id']) && !empty($_POST['event_id'])) {
            // Actualizar evento existente
            $event_id = intval($_POST['event_id']);
            $success = $this->db->update_event($event_id, $data);
            $message = $success ? __('Evento actualizado exitosamente.', 'eventos-probolsas') : __('Error al actualizar el evento.', 'eventos-probolsas');
            $redirect_url = admin_url('admin.php?page=eventos-probolsas&action=edit&event_id=' . $event_id);
        } else {
            // Crear nuevo evento
            $event_id = $this->db->create_event($data);
            $success = $event_id !== false;
            $message = $success ? __('Evento creado exitosamente.', 'eventos-probolsas') : __('Error al crear el evento.', 'eventos-probolsas');
            $redirect_url = $success ? admin_url('admin.php?page=eventos-probolsas&action=edit&event_id=' . $event_id) : admin_url('admin.php?page=eventos-probolsas-add');
        }
        
        $notice_type = $success ? 'success' : 'error';
        $this->set_admin_notice($message, $notice_type);
        
        // Redireccionar para evitar reenvío de formulario
        if ($success) {
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * Manejar eliminación de evento
     */
    private function handle_delete_event() {
        // Verificar nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_event_' . $_GET['event_id'])) {
            wp_die(__('Error de seguridad.', 'eventos-probolsas'));
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'eventos-probolsas'));
        }
        
        $event_id = intval($_GET['event_id']);
        $success = $this->db->delete_event($event_id);
        
        $message = $success ? __('Evento eliminado exitosamente.', 'eventos-probolsas') : __('Error al eliminar el evento.', 'eventos-probolsas');
        $notice_type = $success ? 'success' : 'error';
        
        $this->set_admin_notice($message, $notice_type);
        
        // Redireccionar a la lista
        wp_redirect(admin_url('admin.php?page=eventos-probolsas'));
        exit;
    }
    
    /**
     * Sanitizar datos del formulario
     */
    private function sanitize_form_data($data) {
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
        
        if (isset($data['event_time']) && !empty($data['event_time'])) {
            $sanitized['event_time'] = sanitize_text_field($data['event_time']) . ':00';
        }
        
        return $sanitized;
    }
    
    /**
     * Validar datos del formulario
     */
    private function validate_form_data($data) {
        $errors = array();
        
        // Título requerido
        if (empty($data['title'])) {
            $errors[] = __('El título es requerido.', 'eventos-probolsas');
        } elseif (strlen($data['title']) < 3) {
            $errors[] = __('El título debe tener al menos 3 caracteres.', 'eventos-probolsas');
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
            }
        }
        
        // Validar hora si está presente
        if (!empty($data['event_time'])) {
            $time_parts = explode(':', $data['event_time']);
            if (count($time_parts) < 2 || !is_numeric($time_parts[0]) || !is_numeric($time_parts[1])) {
                $errors[] = __('Formato de hora no válido.', 'eventos-probolsas');
            }
        }
        
        return $errors;
    }
    
    /**
     * Manejar upload de imagen
     */
    private function handle_image_upload($file) {
        // Verificar que es una imagen válida
        $check = getimagesize($file['tmp_name']);
        if ($check === false) {
            return new WP_Error('invalid_image', __('El archivo no es una imagen válida.', 'eventos-probolsas'));
        }
        
        // Verificar tamaño (max 5MB)
        $max_size = 5 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', __('El archivo es demasiado grande. Máximo 5MB.', 'eventos-probolsas'));
        }
        
        // Configurar upload
        $upload_overrides = array(
            'test_form' => false
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
     * Establecer mensaje de administración
     */
    private function set_admin_notice($message, $type = 'info') {
        $notices = get_transient('eventos_admin_notices') ?: array();
        $notices[] = array(
            'message' => $message,
            'type' => $type
        );
        set_transient('eventos_admin_notices', $notices, 30);
    }
    
    /**
     * Mostrar mensajes de administración
     */
    public function show_admin_notices() {
        if (!isset($_GET['page']) || strpos($_GET['page'], 'eventos-probolsas') === false) {
            return;
        }
        
        $notices = get_transient('eventos_admin_notices');
        if ($notices) {
            foreach ($notices as $notice) {
                $class = 'notice-' . $notice['type'];
                echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . wp_kses_post($notice['message']) . '</p></div>';
            }
            delete_transient('eventos_admin_notices');
        }
    }
    
    /**
     * Página principal de administración
     */
    public function admin_page() {
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
        
        switch ($action) {
            case 'edit':
                $this->edit_event_page($event_id);
                break;
            case 'view':
                $this->view_event_page($event_id);
                break;
            default:
                $this->list_events_page();
                break;
        }
    }
    
    /**
     * Página de listado de eventos
     */
    private function list_events_page() {
        // Obtener filtros
        $filters = array();
        
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = sanitize_text_field($_GET['date_from']);
        }
        
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = sanitize_text_field($_GET['date_to']);
        }
        
        if (!empty($_GET['type'])) {
            $filters['type'] = sanitize_text_field($_GET['type']);
        }
        
        if (!empty($_GET['search'])) {
            $filters['search'] = sanitize_text_field($_GET['search']);
        }
        
        $events = $this->db->get_events($filters);
        $event_types = Eventos_Probolsas_Helpers::get_event_types();
        
        include EVENTOS_PROBOLSAS_PLUGIN_PATH . 'includes/templates/admin-list.php';
    }
    
    /**
     * Página para agregar evento
     */
    public function add_event_page() {
        $event = null;
        $event_types = Eventos_Probolsas_Helpers::get_event_types();
        
        include EVENTOS_PROBOLSAS_PLUGIN_PATH . 'includes/templates/admin-form.php';
    }
    
    /**
     * Página para editar evento
     */
    private function edit_event_page($event_id) {
        $event = $this->db->get_event($event_id);
        
        if (!$event) {
            wp_die(__('Evento no encontrado.', 'eventos-probolsas'));
        }
        
        $event_types = Eventos_Probolsas_Helpers::get_event_types();
        
        include EVENTOS_PROBOLSAS_PLUGIN_PATH . 'includes/templates/admin-form.php';
    }
    
    /**
     * Página para ver detalles del evento
     */
    private function view_event_page($event_id) {
        $event = $this->db->get_event($event_id);
        
        if (!$event) {
            wp_die(__('Evento no encontrado.', 'eventos-probolsas'));
        }
        
        $event_types = Eventos_Probolsas_Helpers::get_event_types();
        $type_config = Eventos_Probolsas_Helpers::get_event_type_config($event['type']);
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <i class="fas fa-eye me-2"></i>
                <?php echo esc_html($event['title']); ?>
                <span class="badge bg-secondary ms-2">ID: <?php echo esc_html($event['id']); ?></span>
            </h1>
            
            <a href="<?php echo admin_url('admin.php?page=eventos-probolsas'); ?>" class="page-title-action">
                <i class="fas fa-arrow-left me-1"></i>
                <?php _e('Volver a la lista', 'eventos-probolsas'); ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=eventos-probolsas&action=edit&event_id=' . $event['id']); ?>" class="page-title-action">
                <i class="fas fa-edit me-1"></i>
                <?php _e('Editar', 'eventos-probolsas'); ?>
            </a>
            
            <hr class="wp-header-end">
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <?php _e('Información del Evento', 'eventos-probolsas'); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped">
                                <tr>
                                    <th width="150"><?php _e('Tipo', 'eventos-probolsas'); ?></th>
                                    <td>
                                        <span class="badge fs-6" style="background-color: <?php echo esc_attr($event['color']); ?>;">
                                            <i class="<?php echo esc_attr($event['icon']); ?> me-1"></i>
                                            <?php echo esc_html($type_config['label']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php _e('Fecha', 'eventos-probolsas'); ?></th>
                                    <td>
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo esc_html(Eventos_Probolsas_Helpers::format_date($event['event_date'])); ?>
                                    </td>
                                </tr>
                                <?php if (!empty($event['event_time'])): ?>
                                <tr>
                                    <th><?php _e('Hora', 'eventos-probolsas'); ?></th>
                                    <td>
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo esc_html(Eventos_Probolsas_Helpers::format_time($event['event_time'])); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($event['description'])): ?>
                                <tr>
                                    <th><?php _e('Descripción', 'eventos-probolsas'); ?></th>
                                    <td><?php echo nl2br(esc_html($event['description'])); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th><?php _e('Creado', 'eventos-probolsas'); ?></th>
                                    <td>
                                        <i class="fas fa-plus me-1"></i>
                                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($event['created_at']))); ?>
                                    </td>
                                </tr>
                                <?php if ($event['updated_at'] !== $event['created_at']): ?>
                                <tr>
                                    <th><?php _e('Actualizado', 'eventos-probolsas'); ?></th>
                                    <td>
                                        <i class="fas fa-edit me-1"></i>
                                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($event['updated_at']))); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($event['image_url'])): ?>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-image me-2"></i>
                                <?php _e('Imagen', 'eventos-probolsas'); ?>
                            </h5>
                        </div>
                        <div class="card-body text-center">
                            <img src="<?php echo esc_url($event['image_url']); ?>" 
                                alt="<?php echo esc_attr($event['title']); ?>"
                                class="img-fluid rounded shadow-sm evento-thumbnail"
                                style="cursor: pointer; max-height: 300px;">
                            <div class="mt-3">
                                <a href="<?php echo esc_url($event['image_url']); ?>" 
                                    target="_blank" 
                                    class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-external-link-alt me-1"></i>
                                    <?php _e('Ver original', 'eventos-probolsas'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-4">
                <a href="<?php echo admin_url('admin.php?page=eventos-probolsas&action=edit&event_id=' . $event['id']); ?>" 
                    class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i>
                    <?php _e('Editar Evento', 'eventos-probolsas'); ?>
                </a>
                
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=eventos-probolsas&action=delete&event_id=' . $event['id']), 'delete_event_' . $event['id']); ?>" 
                    class="btn btn-danger ms-2"
                    onclick="return confirm('<?php esc_attr_e('¿Estás seguro de que deseas eliminar este evento? Esta acción no se puede deshacer.', 'eventos-probolsas'); ?>')">
                    <i class="fas fa-trash me-1"></i>
                    <?php _e('Eliminar Evento', 'eventos-probolsas'); ?>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=eventos-probolsas'); ?>" 
                    class="btn btn-secondary ms-2">
                    <i class="fas fa-list me-1"></i>
                    <?php _e('Volver a la Lista', 'eventos-probolsas'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Página de configuración
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <i class="fas fa-cogs me-2"></i>
                <?php _e('Configuración de Eventos', 'eventos-probolsas'); ?>
            </h1>
            
            <hr class="wp-header-end">
            
            <div class="card">
                <div class="card-body">
                    <h5><?php _e('Tipos de Eventos', 'eventos-probolsas'); ?></h5>
                    <p class="text-muted"><?php _e('Configuración de los tipos de eventos disponibles.', 'eventos-probolsas'); ?></p>
                    
                    <?php
                    $event_types = Eventos_Probolsas_Helpers::get_event_types();
                    ?>
                    
                    <div class="row">
                        <?php foreach ($event_types as $type => $config): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3" style="width: 40px; height: 40px; background-color: <?php echo esc_attr($config['color']); ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <i class="<?php echo esc_attr($config['icon']); ?> text-white"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1"><?php echo esc_html($config['label']); ?></h6>
                                            <small class="text-muted">
                                                Color: <?php echo esc_html($config['color']); ?> | 
                                                Imagen: <?php echo $config['requires_image'] ? __('Requerida', 'eventos-probolsas') : __('Opcional', 'eventos-probolsas'); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <hr>
                    
                    <h5><?php _e('Información del Plugin', 'eventos-probolsas'); ?></h5>
                    <table class="table table-sm">
                        <tr>
                            <th width="150"><?php _e('Versión', 'eventos-probolsas'); ?></th>
                            <td><?php echo esc_html(EVENTOS_PROBOLSAS_VERSION); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Eventos totales', 'eventos-probolsas'); ?></th>
                            <td>
                                <?php
                                $stats = $this->db->get_stats();
                                echo esc_html($stats['total']);
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Shortcode', 'eventos-probolsas'); ?></th>
                            <td>
                                <code>[eventos_calendario]</code> - <?php _e('Mostrar calendario', 'eventos-probolsas'); ?><br>
                                <code>[eventos_lista]</code> - <?php _e('Mostrar lista de eventos', 'eventos-probolsas'); ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Directorio de imágenes', 'eventos-probolsas'); ?></th>
                            <td>
                                <?php
                                $upload_dir = wp_upload_dir();
                                echo esc_html($upload_dir['baseurl'] . '/eventos-probolsas/');
                                ?>
                            </td>
                        </tr>
                    </table>
                    
                    <hr>
                    
                    <h5><?php _e('Herramientas', 'eventos-probolsas'); ?></h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-download fa-2x text-primary mb-3"></i>
                                    <h6><?php _e('Exportar Eventos', 'eventos-probolsas'); ?></h6>
                                    <p class="text-muted small"><?php _e('Descargar todos los eventos en formato CSV', 'eventos-probolsas'); ?></p>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="export-events-btn">
                                        <i class="fas fa-download me-1"></i>
                                        <?php _e('Exportar', 'eventos-probolsas'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-upload fa-2x text-success mb-3"></i>
                                    <h6><?php _e('Importar Eventos', 'eventos-probolsas'); ?></h6>
                                    <p class="text-muted small"><?php _e('Subir eventos desde archivo CSV', 'eventos-probolsas'); ?></p>
                                    <input type="file" id="import-events-input" accept=".csv" style="display: none;">
                                    <button type="button" class="btn btn-outline-success btn-sm" id="import-events-btn">
                                        <i class="fas fa-upload me-1"></i>
                                        <?php _e('Importar', 'eventos-probolsas'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-broom fa-2x text-warning mb-3"></i>
                                    <h6><?php _e('Limpiar Cache', 'eventos-probolsas'); ?></h6>
                                    <p class="text-muted small"><?php _e('Limpiar caché de eventos y regenerar', 'eventos-probolsas'); ?></p>
                                    <button type="button" class="btn btn-outline-warning btn-sm" id="clear-cache-btn">
                                        <i class="fas fa-broom me-1"></i>
                                        <?php _e('Limpiar', 'eventos-probolsas'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Exportar eventos
            $('#export-events-btn').on('click', function() {
                const params = new URLSearchParams({
                    action: 'eventos_export_csv',
                    nonce: eventosAjax.nonce
                });
                window.open(eventosAjax.ajax_url + '?' + params.toString());
            });
            
            // Importar eventos
            $('#import-events-btn').on('click', function() {
                $('#import-events-input').click();
            });
            
            $('#import-events-input').on('change', function(e) {
                const file = e.target.files[0];
                if (file && window.eventosAdmin) {
                    window.eventosAdmin.importEvents(file);
                }
            });
            
            // Limpiar cache
            $('#clear-cache-btn').on('click', function() {
                const $btn = $(this);
                const originalText = $btn.html();
                
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Limpiando...');
                
                $.ajax({
                    url: eventosAjax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'eventos_clear_cache',
                        nonce: eventosAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            if (window.eventosAdmin) {
                                window.eventosAdmin.showAlert('Cache limpiado exitosamente', 'success');
                            } else {
                                alert('Cache limpiado exitosamente');
                            }
                        } else {
                            alert('Error al limpiar el cache');
                        }
                    },
                    error: function() {
                        alert('Error al limpiar el cache');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(originalText);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX para subir imagen
     */
    public function ajax_upload_image() {
        // Verificar nonce
        if (!Eventos_Probolsas_Helpers::verify_ajax_nonce($_POST['nonce'])) {
            wp_die('Security check failed');
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para subir imágenes.', 'eventos-probolsas'));
        }
        
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('Error al subir la imagen.', 'eventos-probolsas'));
        }
        
        $uploaded_url = $this->handle_image_upload($_FILES['image']);
        
        if (is_wp_error($uploaded_url)) {
            wp_send_json_error($uploaded_url->get_error_message());
        }
        
        wp_send_json_success(array(
            'url' => $uploaded_url,
            'message' => __('Imagen subida exitosamente.', 'eventos-probolsas')
        ));
    }
    
    /**
     * Generar URL de eliminación con nonce
     */
    public function get_delete_url($event_id) {
        return wp_nonce_url(
            admin_url('admin.php?page=eventos-probolsas&action=delete&event_id=' . $event_id),
            'delete_event_' . $event_id
        );
    }
    
    /**
     * Obtener estadísticas para el dashboard
     */
    public function get_dashboard_stats() {
        return $this->db->get_stats();
    }
    
    /**
     * Validar permisos de usuario
     */
    private function check_permissions($capability = 'manage_options') {
        if (!current_user_can($capability)) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'eventos-probolsas'));
        }
    }
    
    /**
     * Limpiar datos antiguos y optimizar
     */
    public function cleanup_old_data() {
        // Limpiar eventos muy antiguos si es necesario
        // Limpiar imágenes huérfanas
        // Optimizar base de datos
        
        do_action('eventos_probolsas_cleanup');
    }
    
    /**
     * Registrar hooks de limpieza
     */
    public function register_cleanup_hooks() {
        // Ejecutar limpieza semanal
        if (!wp_next_scheduled('eventos_weekly_cleanup')) {
            wp_schedule_event(time(), 'weekly', 'eventos_weekly_cleanup');
        }
        
        add_action('eventos_weekly_cleanup', array($this, 'cleanup_old_data'));
    }
    
    /**
     * Generar breadcrumbs para navegación
     */
    private function generate_breadcrumbs($current_page = '') {
        $breadcrumbs = array();
        
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
                $breadcrumbs[] = array(
                    'title' => $page_titles[$current_page],
                    'url' => '',
                    'current' => true
                );
            }
        }
        
        return $breadcrumbs;
    }
    
    /**
     * Renderizar breadcrumbs
     */
    private function render_breadcrumbs($current_page = '') {
        $breadcrumbs = $this->generate_breadcrumbs($current_page);
        
        if (count($breadcrumbs) > 1) {
            echo '<nav aria-label="breadcrumb" class="mb-4">';
            echo '<ol class="breadcrumb">';
            
            foreach ($breadcrumbs as $crumb) {
                if ($crumb['current']) {
                    echo '<li class="breadcrumb-item active" aria-current="page">' . esc_html($crumb['title']) . '</li>';
                } else {
                    echo '<li class="breadcrumb-item"><a href="' . esc_url($crumb['url']) . '">' . esc_html($crumb['title']) . '</a></li>';
                }
            }
            
            echo '</ol>';
            echo '</nav>';
        }
    }
}