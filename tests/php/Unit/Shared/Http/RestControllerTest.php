<?php
/**
 * Pruebas unitarias del controlador REST base.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Shared\Http;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Probolsas\Eventos\Shared\Errors\ConflictException;
use Probolsas\Eventos\Shared\Errors\NotFoundException;
use Probolsas\Eventos\Shared\Persistence\PersistenceException;
use Probolsas\Eventos\Shared\Validation\ValidationException;
use Probolsas\Eventos\Tests\Unit\Support\FakeRestController;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;
use RuntimeException;
use WP_Error;
use WP_REST_Response;

/**
 * Formato de respuesta, conversión de excepciones en errores HTTP, permisos y registro de rutas.
 * La verificación contra la API REST real de WordPress está en tests/php/Integration/RestControllerTest.php.
 *
 * @covers \Probolsas\Eventos\Shared\Http\RestController
 */
final class RestControllerTest extends UnitTestCase {

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
	}

	public function test_ok_wraps_data_and_disables_every_cache(): void {
		Actions\expectDone( 'litespeed_control_set_nocache' )->once();

		$response = $this->controller()->run( fn() => $this->controller()->respond( [ 'id' => 7 ], 201 ) );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( [ 'data' => [ 'id' => 7 ] ], $response->get_data() );
		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'no-store, private', $response->get_headers()['Cache-Control'] );
		$this->assertSame( 'no-cache', $response->get_headers()['X-LiteSpeed-Cache-Control'] );
	}

	public function test_validation_errors_become_422_with_field_errors(): void {
		$error = $this->controller()->run(
			static function (): never {
				throw new ValidationException( [ 'title' => [ 'El campo «Título» es obligatorio.' ] ] );
			}
		);

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'eventos_validation_failed', $error->get_error_code() );
		$this->assertSame( 'Revisa los campos marcados.', $error->get_error_message() );
		$this->assertSame(
			[
				'status' => 422,
				'errors' => [ 'title' => [ 'El campo «Título» es obligatorio.' ] ],
			],
			$error->get_error_data()
		);
	}

	public function test_not_found_becomes_404_without_field_errors(): void {
		$error = $this->controller()->run(
			static function (): never {
				throw new NotFoundException( 'El evento no existe o fue eliminado.' );
			}
		);

		$this->assertSame( 'eventos_not_found', $error->get_error_code() );
		$this->assertSame( 'El evento no existe o fue eliminado.', $error->get_error_message() );
		$this->assertSame( [ 'status' => 404 ], $error->get_error_data() );
	}

	public function test_conflict_becomes_409(): void {
		$error = $this->controller()->run(
			static function (): never {
				throw new ConflictException( 'No se puede eliminar «Cumpleaños» porque tiene 3 eventos.' );
			}
		);

		$this->assertSame( 'eventos_conflict', $error->get_error_code() );
		$this->assertSame( [ 'status' => 409 ], $error->get_error_data() );
	}

	public function test_persistence_errors_become_500_and_the_technical_detail_goes_only_to_the_log(): void {
		$log      = tempnam( sys_get_temp_dir(), 'ep-log' );
		$previous = ini_set( 'error_log', $log ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- Captura el log de la prueba.

		try {
			$error = $this->controller()->run(
				static function (): never {
					throw new PersistenceException( 'No se pudo guardar la información.', "Unknown column 'x'" );
				}
			);
		} finally {
			ini_set( 'error_log', (string) $previous ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- Restaura el log.
		}

		$this->assertSame( 'eventos_server_error', $error->get_error_code() );
		$this->assertSame( 'No se pudo guardar la información.', $error->get_error_message() );
		$this->assertSame( [ 'status' => 500 ], $error->get_error_data() );
		$this->assertStringContainsString( "Eventos: Unknown column 'x'", (string) file_get_contents( $log ) );
		unlink( $log );
	}

	public function test_unexpected_errors_are_not_swallowed(): void {
		$this->expectException( RuntimeException::class );

		$this->controller()->run(
			static function (): never {
				throw new RuntimeException( 'Error de programación' );
			}
		);
	}

	public function test_permissions_use_the_plugin_capabilities(): void {
		Functions\when( 'current_user_can' )->alias( static fn( string $capability ): bool => 'eventos_view' === $capability );

		$this->assertTrue( $this->controller()->can_view() );
		$this->assertFalse( $this->controller()->can_manage() );
	}

	public function test_routes_are_registered_under_the_plugin_namespace(): void {
		$controller = $this->controller();
		Actions\expectAdded( 'rest_api_init' )->once()->with( [ $controller, 'register_routes' ] );
		Functions\expect( 'register_rest_route' )->once()->with( 'eventos/v1', '/event-types/(?P<id>\d+)', [ 'methods' => 'GET' ] );

		$controller->register();
		$controller->register_routes();
	}

	/**
	 * Controlador de prueba que expone los métodos protegidos.
	 */
	private function controller(): FakeRestController {
		return new FakeRestController();
	}
}
