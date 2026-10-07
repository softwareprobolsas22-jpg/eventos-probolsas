<?php
/**
 * Autoloader PSR-4 del plugin.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core;

/**
 * Carga las clases del plugin sin depender de Composer en producción.
 *
 * Composer solo se usa para las herramientas de desarrollo; el plugin no tiene dependencias PHP en tiempo de ejecución.
 */
final class Autoloader {

	/**
	 * Registra un autoloader PSR-4 para un prefijo de namespace.
	 *
	 * @param string $prefix   Prefijo del namespace, terminado en "\\".
	 * @param string $base_dir Directorio base de las clases, terminado en "/".
	 */
	public static function register( string $prefix, string $base_dir ): void {
		spl_autoload_register(
			static function ( string $class_name ) use ( $prefix, $base_dir ): void {
				if ( ! str_starts_with( $class_name, $prefix ) ) {
					return;
				}

				$relative_class = substr( $class_name, strlen( $prefix ) );
				$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

				if ( is_readable( $file ) ) {
					require $file;
				}
			}
		);
	}
}
