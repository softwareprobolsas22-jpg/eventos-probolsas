<?php
/**
 * Pruebas de integración de los ajustes.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Integration;

use Probolsas\Eventos\Core\Security\Capabilities;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * `GET/PUT /settings` contra WordPress real (H-402, D-16).
 *
 * @coversNothing
 */
final class SettingsApiTest extends IntegrationTestCase {

	public function set_up(): void {
		parent::set_up();
		$this->plugin()->activate();
		delete_option( 'eventos_delete_data_on_uninstall' );

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Servidor REST de WordPress para la prueba.
		do_action( 'rest_api_init', $wp_rest_server ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook de WordPress.

		$manager = self::factory()->user->create_and_get( [ 'role' => 'editor' ] );
		$manager->add_cap( Capabilities::MANAGE );
		wp_set_current_user( $manager->ID );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Restablece el servidor REST.

		parent::tear_down();
	}

	public function test_the_setting_is_off_by_default_and_can_be_saved(): void {
		$this->assertSame( [ 'delete_data_on_uninstall' => false ], $this->request( 'GET' )->get_data()['data'] );

		$saved = $this->request( 'PUT', [ 'delete_data_on_uninstall' => true ] );
		$this->assertSame( 200, $saved->get_status() );
		$this->assertTrue( $saved->get_data()['data']['delete_data_on_uninstall'] );
		$this->assertTrue( $this->request( 'GET' )->get_data()['data']['delete_data_on_uninstall'] );

		$invalid = $this->request( 'PUT', [ 'delete_data_on_uninstall' => 'quizá' ] );
		$this->assertSame( 422, $invalid->get_status() );
		$this->assertArrayHasKey( 'delete_data_on_uninstall', $invalid->get_data()['data']['errors'] );
	}

	public function test_permissions(): void {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request( 'GET' )->get_status(), 'Visitante (D-1).' );
		$this->assertSame( 401, $this->request( 'PUT', [ 'delete_data_on_uninstall' => true ] )->get_status() );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertSame( 403, $this->request( 'GET' )->get_status(), 'Sin eventos_manage (R-22).' );
		$this->assertSame( 403, $this->request( 'PUT', [ 'delete_data_on_uninstall' => true ] )->get_status() );
		$this->assertFalse( get_option( 'eventos_delete_data_on_uninstall', false ) );
	}

	/**
	 * Petición a `/settings`.
	 *
	 * @param string               $method Método HTTP.
	 * @param array<string, mixed> $body   Cuerpo JSON.
	 */
	private function request( string $method, array $body = [] ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/eventos/v1/settings' );

		if ( [] !== $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return rest_do_request( $request );
	}
}
