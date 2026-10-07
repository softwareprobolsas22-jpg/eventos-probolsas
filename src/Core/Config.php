<?php
/**
 * Acceso a la configuración del plugin.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core;

/**
 * Configuración de solo lectura cargada desde `config/*.php` (fuente única de verdad).
 *
 * Cada archivo devuelve un arreglo y queda disponible bajo su nombre: `config/ui.php` → `ui.page_sizes`.
 */
final class Config {

	/**
	 * Crea la configuración.
	 *
	 * @param array<string, mixed> $items Configuración agrupada por nombre de archivo.
	 */
	public function __construct( private readonly array $items ) {}

	/**
	 * Carga todos los archivos PHP de un directorio.
	 *
	 * @param string $directory Directorio de configuración.
	 */
	public static function from_directory( string $directory ): self {
		$items = [];
		$files = glob( rtrim( $directory, '/\\' ) . '/*.php' );

		foreach ( false === $files ? [] : $files as $file ) {
			$items[ basename( $file, '.php' ) ] = require $file;
		}

		return new self( $items );
	}

	/**
	 * Obtiene un valor con notación de puntos.
	 *
	 * @param string $key      Clave, por ejemplo `ui.page_sizes`.
	 * @param mixed  $fallback Valor devuelto si la clave no existe.
	 */
	public function get( string $key, mixed $fallback = null ): mixed {
		$value = $this->items;

		foreach ( explode( '.', $key ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $fallback;
			}

			$value = $value[ $segment ];
		}

		return $value;
	}
}
