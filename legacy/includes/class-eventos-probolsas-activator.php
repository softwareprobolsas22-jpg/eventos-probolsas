<?php
/**
 * Clase para activación/desactivación del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Eventos_Probolsas_Activator {
    
    /**
     * Ejecutar al activar el plugin
     */
    public static function activate() {
        self::create_tables();
        self::create_default_data();
        
        // Crear directorio de uploads si no existe
        $upload_dir = wp_upload_dir();
        $eventos_dir = $upload_dir['basedir'] . '/eventos-probolsas/';
        
        if (!file_exists($eventos_dir)) {
            wp_mkdir_p($eventos_dir);
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Ejecutar al desactivar el plugin
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Crear tabla de eventos
     */
    private static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'eventos_probolsas';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            type varchar(50) NOT NULL,
            description text,
            event_date date NOT NULL,
            event_time time,
            image_url varchar(500),
            color varchar(7) NOT NULL,
            icon varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type_index (type),
            KEY date_index (event_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Crear datos por defecto
     */
    private static function create_default_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'eventos_probolsas';
        
        // Verificar si ya existen datos
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($count == 0) {
            $default_events = array(
                array(
                    'title' => 'Ejemplo Cumpleaños',
                    'type' => 'cumpleanos',
                    'description' => 'Celebración de cumpleaños de ejemplo',
                    'event_date' => date('Y-m-d'),
                    'event_time' => '15:00:00',
                    'color' => '#FFF3CD',
                    'icon' => 'fas fa-birthday-cake'
                ),
                array(
                    'title' => 'Ejemplo Capacitación',
                    'type' => 'capacitacion',
                    'description' => 'Capacitación sobre nuevas herramientas',
                    'event_date' => date('Y-m-d', strtotime('+1 day')),
                    'event_time' => '09:00:00',
                    'color' => '#D4EDDA',
                    'icon' => 'fas fa-graduation-cap'
                )
            );
            
            foreach ($default_events as $event) {
                $wpdb->insert($table_name, $event);
            }
        }
    }
}