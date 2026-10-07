<?php
/**
 * Plugin Name:       Eventos Probolsas
 * Plugin URI:        https://github.com/softwareprobolsas22-jpg/eventos-probolsas
 * Description:       Calendario de eventos de la intranet de Probolsas: cumpleaños, capacitaciones y reuniones.
 * Version:           2.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.3
 * Author:            Probolsas
 * Text Domain:       eventos-probolsas
 * Domain Path:       /languages
 * License:           Propietario
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

use Probolsas\Eventos\Core\Autoloader;
use Probolsas\Eventos\Core\Plugin;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/src/Core/Autoloader.php';

Autoloader::register( 'Probolsas\\Eventos\\', __DIR__ . '/src/' );
Plugin::init( __FILE__ );
