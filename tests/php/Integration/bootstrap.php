<?php
/**
 * Bootstrap de las pruebas de integración. Se ejecuta dentro del contenedor tests-cli de wp-env.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

$eventos_plugin_dir = dirname( __DIR__, 3 );
$eventos_tests_dir  = getenv( 'WP_TESTS_DIR' );

if ( false === $eventos_tests_dir || '' === $eventos_tests_dir ) {
	$eventos_tests_dir = '/wordpress-phpunit';
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Constante que exige la suite de pruebas de WordPress.
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $eventos_plugin_dir . '/vendor/yoast/phpunit-polyfills' );

require_once $eventos_plugin_dir . '/vendor/autoload.php';
require_once $eventos_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $eventos_plugin_dir ): void {
		require $eventos_plugin_dir . '/eventos-probolsas.php';
	}
);

require $eventos_tests_dir . '/includes/bootstrap.php';
