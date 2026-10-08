<?php
/**
 * Pruebas de integración de la API de tipos de evento.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Integration;

use Probolsas\Eventos\Core\Database\Tables;
use Probolsas\Eventos\Core\Security\Capabilities;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Contrato de docs/api/event-types.md contra WordPress y MySQL reales.
 *
 * @coversNothing
 */
final class EventTypeApiTest extends IntegrationTestCase {

	public function set_up(): void {
		parent::set_up();
		$this->plugin()->activate();

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

	public function test_fresh_install_has_the_four_initial_types(): void {
		$types = $this->request( 'GET', '' )->get_data()['data'];

		$this->assertSame( [ 'Cumpleaños', 'Capacitaciones', 'Reuniones especiales', 'Reuniones laborales' ], array_column( $types, 'name' ) );
		$this->assertSame( [ 'cumpleanos', 'capacitaciones', 'reuniones-especiales', 'reuniones-laborales' ], array_column( $types, 'slug' ) );
		$this->assertSame( [ true, true, true, false ], array_column( $types, 'requires_attachment' ) );
		$this->assertSame( [ 'light', 'light', 'light', 'light' ], array_column( $types, 'text_tone' ) );
		$this->assertSame( [ 0, 0, 0, 0 ], array_column( $types, 'events_count' ) );
	}

	public function test_create_update_and_delete(): void {
		$created = $this->request(
			'POST',
			'',
			[
				'name'                => 'Integraciones',
				'color'               => '#669f30',
				'icon'                => 'people-group',
				'requires_attachment' => false,
			]
		);

		$this->assertSame( 201, $created->get_status() );
		$data = $created->get_data()['data'];
		$this->assertSame( '#669F30', $data['color'] );
		$this->assertSame( 'dark', $data['text_tone'] );
		$this->assertSame( 5, $data['sort_order'] );

		$updated = $this->request(
			'PUT',
			'/' . $data['id'],
			[
				'name'  => 'Integraciones y pausas',
				'color' => '#155728',
				'icon'  => 'mug-hot',
			]
		);
		$this->assertSame( 200, $updated->get_status() );
		$this->assertSame( 'integraciones', $updated->get_data()['data']['slug'], 'El slug no cambia al renombrar.' );

		$deleted = $this->request( 'DELETE', '/' . $data['id'] );
		$this->assertSame( 200, $deleted->get_status() );
		$this->assertSame( 404, $this->request( 'GET', '/' . $data['id'] )->get_status() );
	}

	public function test_duplicated_name_returns_422_with_the_field_error(): void {
		$response = $this->request(
			'POST',
			'',
			[
				'name'  => 'CUMPLEAÑOS',
				'color' => '#155728',
				'icon'  => 'gift',
			]
		);

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'eventos_validation_failed', $response->get_data()['code'] );
		$this->assertSame( [ 'Ya existe un tipo de evento llamado «CUMPLEAÑOS».' ], $response->get_data()['data']['errors']['name'] );
	}

	public function test_types_with_events_cannot_be_deleted(): void {
		global $wpdb;
		$type_id = (int) $this->request( 'GET', '' )->get_data()['data'][0]['id'];
		$now     = gmdate( 'Y-m-d H:i:s' );
		$wpdb->insert(
			$this->tables()->name( Tables::EVENTS ),
			[
				'type_id'        => $type_id,
				'title'          => 'Cumpleaños de Ana',
				'start_date'     => '2026-10-07',
				'created_at_gmt' => $now,
				'updated_at_gmt' => $now,
			]
		);

		$response = $this->request( 'DELETE', '/' . $type_id );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'No se puede eliminar «Cumpleaños» porque tiene 1 evento asociado.', $response->get_data()['message'] );
		$this->assertSame( 1, $this->request( 'GET', '/' . $type_id )->get_data()['data']['events_count'] );
	}

	public function test_reorder(): void {
		$ids = array_column( $this->request( 'GET', '' )->get_data()['data'], 'id' );

		$response = $this->request( 'PUT', '/order', [ 'ids' => array_reverse( $ids ) ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Reuniones laborales', $response->get_data()['data'][0]['name'] );
		$this->assertSame( 422, $this->request( 'PUT', '/order', [ 'ids' => [ $ids[0] ] ] )->get_status() );
	}

	public function test_permissions(): void {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request( 'GET', '' )->get_status(), 'Un visitante no ve los tipos (D-1).' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertSame( 200, $this->request( 'GET', '' )->get_status(), 'Cualquier usuario con sesión los ve (filtros del calendario).' );
		$this->assertSame(
			403,
			$this->request(
				'POST',
				'',
				[
					'name'  => 'Otro',
					'color' => '#155728',
					'icon'  => 'star',
				]
			)->get_status(),
			'Sin eventos_manage no se gestionan.'
		);
	}

	public function test_client_config_publishes_the_validation_rules(): void {
		$config = $this->plugin()->container()->get( \Probolsas\Eventos\Core\Assets\Assets::class )->client_config();

		$this->assertSame( 100, $config['rules']['event_type']['name']['maxLength'] );
		$this->assertSame( 'icons', $config['rules']['event_type']['icon']['oneOf'] );
	}

	/**
	 * Ejecuta una petición a la API de tipos de evento.
	 *
	 * @param string               $method Método HTTP.
	 * @param string               $path   Ruta relativa al recurso.
	 * @param array<string, mixed> $body   Cuerpo JSON.
	 */
	private function request( string $method, string $path, array $body = [] ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/eventos/v1/event-types' . $path );

		if ( [] !== $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return rest_do_request( $request );
	}
}
