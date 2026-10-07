<?php
/**
 * Plugin Name: Eventos - Probolsas
 * Plugin URI: https://intranet.probolsas.com/
 * Description: Plugin para gestión de eventos con calendario interactivo, tipos personalizados y vista administrativa completa.
 * Version: 19.8.9
 * Author: Probolsas
 * License: GPL v2 or later
 * Text Domain: eventos-probolsas
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('EVENTOS_PROBOLSAS_VERSION', '19.8.9');
define('EVENTOS_PROBOLSAS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EVENTOS_PROBOLSAS_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Clase principal del plugin
 */
class Eventos_Probolsas {
    
    /**
     * Instancia única del plugin
     */
    private static $instance = null;
    
    /**
     * Obtener instancia única
     */
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor privado
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Cargar dependencias
     */
    private function load_dependencies() {
        require_once EVENTOS_PROBOLSAS_PLUGIN_PATH . 'includes/class-eventos-probolsas-activator.php';
        require_once EVENTOS_PROBOLSAS_PLUGIN_PATH . 'includes/class-eventos-probolsas-db.php';
        require_once EVENTOS_PROBOLSAS_PLUGIN_PATH . 'includes/class-eventos-probolsas-admin.php';
        require_once EVENTOS_PROBOLSAS_PLUGIN_PATH . 'includes/class-eventos-probolsas-frontend.php';
        require_once EVENTOS_PROBOLSAS_PLUGIN_PATH . 'includes/class-eventos-probolsas-ajax.php';
        require_once EVENTOS_PROBOLSAS_PLUGIN_PATH . 'includes/class-eventos-probolsas-helpers.php';
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array('Eventos_Probolsas_Activator', 'activate'));
        register_deactivation_hook(__FILE__, array('Eventos_Probolsas_Activator', 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init_plugin'));
    }
    
    /**
     * Inicializar plugin
     */
    public function init_plugin() {
        // Cargar textdomain
        load_plugin_textdomain('eventos-probolsas', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        
        // Inicializar clases
        new Eventos_Probolsas_Admin();
        new Eventos_Probolsas_Frontend();
        new Eventos_Probolsas_Ajax();
    }
}



// Inicializar plugin
Eventos_Probolsas::get_instance();