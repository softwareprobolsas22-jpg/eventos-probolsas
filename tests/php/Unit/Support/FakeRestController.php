<?php
/**
 * Controlador REST de prueba para las pruebas unitarias.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Support;

use Probolsas\Eventos\Shared\Http\RestController;
use WP_Error;
use WP_REST_Response;

/**
 * Expone los métodos protegidos de RestController. Brain Monkey necesita una clase con nombre para
 * registrar sus métodos como callbacks de hooks.
 */
final class FakeRestController extends RestController {

	/**
	 * Segmento base.
	 */
	protected function rest_base(): string {
		return 'event-types';
	}

	/**
	 * Una ruta de ejemplo.
	 */
	public function register_routes(): void {
		$this->add_route( '/(?P<id>\d+)', [ 'methods' => 'GET' ] );
	}

	/**
	 * Ejecuta una acción como lo haría un endpoint.
	 *
	 * @param callable(): WP_REST_Response $action Acción.
	 */
	public function run( callable $action ): WP_REST_Response|WP_Error {
		return $this->handle( $action );
	}

	/**
	 * Respuesta exitosa.
	 *
	 * @param mixed $data   Datos.
	 * @param int   $status Estado.
	 */
	public function respond( mixed $data, int $status ): WP_REST_Response {
		return $this->ok( $data, $status );
	}
}
