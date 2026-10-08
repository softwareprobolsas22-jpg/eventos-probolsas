<?php
/**
 * Conexión $wpdb simulada para las pruebas unitarias de los repositorios.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Support;

use Mockery;
use Mockery\MockInterface;

/**
 * Crea un doble de `wpdb` cuyo prepare() reemplaza los marcadores como lo hace WordPress (%i → `id`,
 * %d → entero, %s → 'texto'), para comprobar el SQL final sin base de datos.
 */
final class FakeWpdb {

	/**
	 * Conexión simulada con prefijo `wp_`.
	 */
	public static function create(): MockInterface {
		$wpdb             = Mockery::mock( 'wpdb' );
		$wpdb->prefix     = 'wp_';
		$wpdb->insert_id  = 0;
		$wpdb->last_error = '';
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing(
			static function ( string $query, mixed ...$args ): string {
				return (string) preg_replace_callback(
					'/%[ids]/',
					static function ( array $found ) use ( &$args ): string {
						$value = array_shift( $args );
						return match ( $found[0] ) {
							'%i' => "`{$value}`",
							'%d' => (string) (int) $value,
							default => "'{$value}'",
						};
					},
					$query
				);
			}
		);

		return $wpdb;
	}
}
