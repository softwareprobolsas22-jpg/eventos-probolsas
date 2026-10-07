<?php
/**
 * Pruebas del controlador REST base (casos CP-1.15 a CP-1.17).
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Integration;

use Probolsas\Eventos\Core\Security\Capabilities;
use Probolsas\Eventos\Tests\Integration\Support\FakeRestController;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Verifica, contra la API REST real de WordPress, el formato de respuestas y errores de docs/api/README.md.
 *
 * @coversNothing
 */
final class RestControllerTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Servidor REST de WordPress para la prueba.
		add_action( 'rest_api_init', [ new FakeRestController(), 'register_routes' ] );
		do_action( 'rest_api_init', $wp_rest_server ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook de WordPress.
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Restablece el servidor REST de WordPress.

		parent::tear_down();
	}

	public function test_success_wraps_payload_in_data(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$response = $this->request( 'GET', '/items' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'data' => [ [ 'id' => 1 ] ] ], $response->get_data() );
	}

	public function test_success_responses_are_never_cached(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$calls = 0;
		$count = static function () use ( &$calls ): void {
			++$calls;
		};
		add_action( 'litespeed_control_set_nocache', $count ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- API del plugin LiteSpeed Cache.

		$headers = $this->request( 'GET', '/items' )->get_headers();

		$this->assertSame( 'no-store, private', $headers['Cache-Control'] );
		$this->assertSame( 'no-cache', $headers['X-LiteSpeed-Cache-Control'] );
		$this->assertSame( 1, $calls, 'Con LiteSpeed Cache activo, el plugin tampoco guarda la respuesta.' );
	}

	public function test_validation_error_returns_422_with_field_errors(): void {
		$this->login_as_manager();

		$response = $this->request( 'POST', '/items', [ 'name' => '   ' ] );
		$body     = $response->get_data();

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'eventos_validation_failed', $body['code'] );
		$this->assertSame( 'Revisa los campos marcados.', $body['message'] );
		$this->assertSame( [ 'name' => [ 'El campo «Nombre» es obligatorio.' ] ], $body['data']['errors'] );
	}

	public function test_valid_creation_returns_201(): void {
		$this->login_as_manager();

		$response = $this->request( 'POST', '/items', [ 'name' => 'Capacitaciones' ] );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( [ 'data' => [ 'id' => 2 ] ], $response->get_data() );
	}

	public function test_not_found_returns_404(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$response = $this->request( 'GET', '/missing' );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'eventos_not_found', $response->get_data()['code'] );
		$this->assertSame( 'El registro no existe.', $response->get_data()['message'] );
	}

	public function test_conflict_returns_409(): void {
		$this->login_as_manager();

		$response = $this->request( 'DELETE', '/conflict' );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'eventos_conflict', $response->get_data()['code'] );
	}

	public function test_anonymous_users_get_401(): void {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->request( 'GET', '/items' )->get_status() );
	}

	public function test_users_without_manage_capability_get_403_and_action_is_not_run(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$response = $this->request( 'POST', '/items', [ 'name' => 'Capacitaciones' ] );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'rest_forbidden', $response->get_data()['code'] );
	}

	/**
	 * Inicia sesión con un usuario que tiene eventos_manage.
	 */
	private function login_as_manager(): void {
		$user = self::factory()->user->create_and_get( [ 'role' => 'editor' ] );
		$user->add_cap( Capabilities::MANAGE );
		wp_set_current_user( $user->ID );
	}

	/**
	 * Ejecuta una petición a la API de prueba.
	 *
	 * @param string               $method Método HTTP.
	 * @param string               $path   Ruta relativa al recurso.
	 * @param array<string, mixed> $body   Cuerpo JSON.
	 */
	private function request( string $method, string $path, array $body = [] ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/eventos/v1/ep-test' . $path );

		if ( [] !== $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return rest_do_request( $request );
	}
}
