<?php
/**
 * Desinstalación del plugin: elimina tablas, opciones y capabilities. Los archivos de la Biblioteca de
 * Medios se conservan.
 *
 * WordPress carga solo este archivo (no el archivo principal), por eso registra su propio autoloader.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

use Probolsas\Eventos\Core\Autoloader;
use Probolsas\Eventos\Core\Plugin;

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/src/Core/Autoloader.php';

Autoloader::register( 'Probolsas\\Eventos\\', __DIR__ . '/src/' );
Plugin::uninstall( __DIR__ . '/eventos-probolsas.php' );
