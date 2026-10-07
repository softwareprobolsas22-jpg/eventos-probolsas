<?php
/**
 * Controlador REST de prueba.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Integration\Support;

use Probolsas\Eventos\Shared\Errors\ConflictException;
use Probolsas\Eventos\Shared\Errors\NotFoundException;
use Probolsas\Eventos\Shared\Http\RestController;
use Probolsas\Eventos\Shared\Validation\Validator;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Expone una ruta por cada comportamiento de RestController para probarlos contra WordPress real.
 */
final class FakeRestController extends RestController {

	/**
	 * Segmento base.
	 */
	protected function rest_base(): string {
		return 'ep-test';
	}

	/**
	 * Rutas de prueba.
	 */
	public function register_routes(): void {
		$this->add_route(
			'/items',
			[
				[
					'methods'             => 'GET',
					'permission_callback' => [ $this, 'can_view' ],
					'callback'            => fn(): WP_REST_Response => $this->ok( [ [ 'id' => 1 ] ] ),
				],
				[
					'methods'             => 'POST',
					'permission_callback' => [ $this, 'can_manage' ],
					'callback'            => fn( WP_REST_Request $request ) => $this->handle(
						function () use ( $request ): WP_REST_Response {
							$validator = new Validator( (array) $request->get_json_params() );
							$validator->field( 'name', 'Nombre' )->required()->max_length( 10 );
							$validator->validate();
							return $this->ok( [ 'id' => 2 ], 201 );
						}
					),
				],
			]
		);

		$this->add_route(
			'/missing',
			[
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'can_view' ],
				'callback'            => fn() => $this->handle(
					static function (): WP_REST_Response {
						throw new NotFoundException( 'El registro no existe.' );
					}
				),
			]
		);

		$this->add_route(
			'/conflict',
			[
				'methods'             => 'DELETE',
				'permission_callback' => [ $this, 'can_manage' ],
				'callback'            => fn() => $this->handle(
					static function (): WP_REST_Response {
						throw new ConflictException( 'No se puede eliminar: tiene 3 eventos.' );
					}
				),
			]
		);
	}
}
