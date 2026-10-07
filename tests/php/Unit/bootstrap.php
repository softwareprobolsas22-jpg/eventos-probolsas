<?php
/**
 * Bootstrap de las pruebas unitarias (sin WordPress).
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

require_once dirname( __DIR__, 3 ) . '/vendor/autoload.php';
require_once __DIR__ . '/Support/wp-rest-doubles.php';

// Formato de resultado de $wpdb (mismo valor que en WordPress).
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Simula la constante de WordPress.
}

// Los archivos del plugin terminan si ABSPATH no está definido.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/ep-wordpress/' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Simula la constante de WordPress.
}
