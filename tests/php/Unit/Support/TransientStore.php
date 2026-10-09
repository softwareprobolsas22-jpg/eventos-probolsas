<?php
/**
 * Transients y opciones de WordPress en memoria.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Support;

use Brain\Monkey\Functions;

/**
 * Simula get/set_transient y get/update/delete_option con arreglos, para probar la caché de respuestas
 * (H-401) sin WordPress. Registra cuántas veces se escribió cada transient.
 */
final class TransientStore {

	/**
	 * Transients guardados.
	 *
	 * @var array<string, mixed>
	 */
	public array $transients = [];

	/**
	 * Opciones guardadas.
	 *
	 * @var array<string, mixed>
	 */
	public array $options = [];

	/**
	 * Escrituras de transients.
	 *
	 * @var int
	 */
	public int $writes = 0;

	/**
	 * Conecta las funciones de WordPress con este almacén (Brain Monkey).
	 */
	public static function install(): self {
		$store = new self();

		Functions\when( 'get_transient' )->alias( static fn( string $key ): mixed => $store->transients[ $key ] ?? false );
		Functions\when( 'set_transient' )->alias(
			static function ( string $key, mixed $value ) use ( $store ): bool {
				$store->transients[ $key ] = $value;
				++$store->writes;
				return true;
			}
		);
		Functions\when( 'get_option' )->alias( static fn( string $name, mixed $fallback = false ): mixed => $store->options[ $name ] ?? $fallback );
		Functions\when( 'update_option' )->alias(
			static function ( string $name, mixed $value ) use ( $store ): bool {
				$store->options[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			static function ( string $name ) use ( $store ): bool {
				unset( $store->options[ $name ] );
				return true;
			}
		);
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		return $store;
	}
}
