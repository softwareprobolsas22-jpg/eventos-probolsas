<?php
/**
 * Bootstrap de las pruebas unitarias (sin WordPress).
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

require_once dirname( __DIR__, 3 ) . '/vendor/autoload.php';

// Los archivos del plugin terminan si ABSPATH no está definido.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/ep-wordpress/' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Simula la constante de WordPress.
}
