<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Eventos_Probolsas
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Verificar permisos
if (!current_user_can('delete_plugins')) {
    return;
}

/**
 * Limpiar todos los datos del plugin
 */
class Eventos_Probolsas_Uninstaller {
    
    public static function uninstall() {
        global $wpdb;
        
        // Eliminar tabla de eventos
        self::drop_tables();
        
        // Eliminar archivos subidos
        self::delete_uploaded_files();
        
        // Eliminar opciones
        self::delete_options();
        
        // Limpiar cache
        self::clear_cache();
        
        // Log de desinstalación
        self::log_uninstall();
    }
    
    /**
     * Eliminar tablas de la base de datos
     */
    private static function drop_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'eventos_probolsas';
        
        // Verificar si la tabla existe antes de eliminar
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        if ($table_exists) {
            $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
            
            // Verificar que se eliminó correctamente
            $table_exists_after = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            ));
            
            if (!$table_exists_after) {
                error_log('Eventos Probolsas: Tabla eliminada correctamente');
            }
        }
    }
    
    /**
     * Eliminar archivos subidos
     */
    private static function delete_uploaded_files() {
        $upload_dir = wp_upload_dir();
        $eventos_dir = $upload_dir['basedir'] . '/eventos-probolsas/';
        
        if (is_dir($eventos_dir)) {
            self::delete_directory($eventos_dir);
            error_log('Eventos Probolsas: Archivos eliminados correctamente');
        }
    }
    
    /**
     * Eliminar directorio recursivamente
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $file_path = $dir . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($file_path)) {
                self::delete_directory($file_path);
            } else {
                unlink($file_path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Eliminar opciones de WordPress
     */
    private static function delete_options() {
        // Opciones del plugin
        $options = array(
            'eventos_probolsas_version',
            'eventos_probolsas_settings',
            'eventos_probolsas_cache_time',
            'eventos_probolsas_last_cleanup',
            'eventos_probolsas_stats'
        );
        
        foreach ($options as $option) {
            delete_option($option);
            delete_site_option($option); // Para multisitio
        }
        
        // Eliminar transients relacionados
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_eventos_probolsas_%' 
             OR option_name LIKE '_transient_timeout_eventos_probolsas_%'"
        );
        
        error_log('Eventos Probolsas: Opciones eliminadas correctamente');
    }
    
    /**
     * Limpiar cache
     */
    private static function clear_cache() {
        // Limpiar cache de objetos
        wp_cache_flush();
        
        // Limpiar rewrite rules
        flush_rewrite_rules();
        
        // Limpiar cache de terceros si están disponibles
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        
        if (function_exists('wp_rocket_clean_domain')) {
            wp_rocket_clean_domain();
        }
        
        error_log('Eventos Probolsas: Cache limpiado correctamente');
    }
    
    /**
     * Log de desinstalación
     */
    private static function log_uninstall() {
        $log_data = array(
            'plugin' => 'Eventos Probolsas',
            'version' => get_option('eventos_probolsas_version', '1.0.0'),
            'uninstalled_at' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'site_url' => get_site_url()
        );
        
        error_log('Eventos Probolsas desinstalado: ' . json_encode($log_data));
        
        // Opcional: Enviar estadísticas anónimas de desinstalación
        // self::send_uninstall_stats($log_data);
    }
    
    /**
     * Enviar estadísticas anónimas (opcional)
     */
    private static function send_uninstall_stats($data) {
        // Solo si el usuario ha dado consentimiento
        $send_stats = get_option('eventos_probolsas_send_stats', false);
        
        if (!$send_stats) {
            return;
        }
        
        $stats_data = array(
            'action' => 'uninstall',
            'plugin_version' => $data['version'],
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'site_hash' => md5(get_site_url()), // Anonimizado
            'timestamp' => time()
        );
        
        wp_remote_post('https://stats.probolsas.com/plugin-events', array(
            'body' => $stats_data,
            'timeout' => 5,
            'blocking' => false
        ));
    }
    
    /**
     * Crear backup antes de desinstalar (opcional)
     */
    private static function create_backup() {
        global $wpdb;
        
        $backup_option = get_option('eventos_probolsas_backup_on_uninstall', false);
        
        if (!$backup_option) {
            return;
        }
        
        $table_name = $wpdb->prefix . 'eventos_probolsas';
        $events = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
        
        if (!empty($events)) {
            $backup_data = array(
                'events' => $events,
                'export_date' => current_time('mysql'),
                'plugin_version' => get_option('eventos_probolsas_version', '1.0.0')
            );
            
            $upload_dir = wp_upload_dir();
            $backup_file = $upload_dir['basedir'] . '/eventos-probolsas-backup-' . date('Y-m-d-H-i-s') . '.json';
            
            file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT));
            
            error_log('Eventos Probolsas: Backup creado en ' . $backup_file);
        }
    }
    
    /**
     * Confirmar desinstalación con el usuario
     */
    public static function confirm_uninstall() {
        // Solo mostrar si estamos en el admin y es la acción correcta
        if (!is_admin() || !current_user_can('delete_plugins')) {
            return;
        }
        
        // Verificar si se está desinstalando
        if (isset($_GET['action']) && $_GET['action'] === 'delete-selected' && 
            isset($_GET['plugin']) && strpos($_GET['plugin'], 'eventos-probolsas') !== false) {
            
            ?>
            <script>
            if (!confirm('¿Estás seguro de que deseas desinstalar Eventos Probolsas?\n\nEsto eliminará:\n- Todos los eventos creados\n- Imágenes subidas\n- Configuraciones del plugin\n\nEsta acción no se puede deshacer.')) {
                window.history.back();
            }
            </script>
            <?php
        }
    }
}

// Ejecutar desinstalación
try {
    Eventos_Probolsas_Uninstaller::uninstall();
    
    // Mensaje de éxito (solo visible en logs)
    error_log('Eventos Probolsas: Desinstalación completada exitosamente');
    
} catch (Exception $e) {
    // Log del error
    error_log('Error en desinstalación de Eventos Probolsas: ' . $e->getMessage());
    
    // No fallar silenciosamente, registrar el error
    if (defined('WP_DEBUG') && WP_DEBUG) {
        wp_die('Error al desinstalar Eventos Probolsas: ' . $e->getMessage());
    }
}

// Limpiar memoria
unset($wpdb, $table_name, $upload_dir, $eventos_dir);
