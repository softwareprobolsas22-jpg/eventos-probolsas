<?php
/**
 * Pruebas de integración del dashboard.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Probolsas\Eventos\Core\Security\Capabilities;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * `GET /dashboard` contra WordPress y MySQL reales (H-206): cifras con la fecha de Colombia y permisos.
 *
 * @coversNothing
 */
final class DashboardApiTest extends IntegrationTestCase {

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

	public function test_the_summary_counts_today_the_next_30_days_and_each_type(): void {
		$types = [];
		foreach ( $this->request( 'GET', '/event-types' )->get_data()['data'] as $type ) {
			$types[ $type['name'] ] = (int) $type['id'];
		}
		$meetings = $types['Reuniones laborales'];
		$today    = $this->plugin()->container()->get( DateFormatter::class )->today();

		foreach ( [ '-1 day', '+0 days', '+0 days', '+29 days', '+30 days' ] as $index => $shift ) {
			$response = $this->request(
				'POST',
				'/events',
				[
					'title'      => "Reunión {$index}",
					'type_id'    => $meetings,
					'start_date' => $this->shift( $today, $shift ),
				]
			);
			$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		}

		$response = $this->request( 'GET', '/dashboard' );
		$data     = $response->get_data()['data'];

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 2, 3, 5 ], [ $data['today'], $data['next_30_days'], $data['total'] ] );
		$this->assertSame( [ $today, $this->shift( $today, '+29 days' ) ], [ $data['date_from'], $data['date_to'] ] );
		$this->assertCount( 4, $data['by_type'], 'Los 4 tipos de la semilla, también los que no tienen eventos.' );
		$this->assertContains(
			[
				'type_id' => $meetings,
				'count'   => 5,
			],
			$data['by_type']
		);
	}

	public function test_permissions(): void {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request( 'GET', '/dashboard' )->get_status(), 'Visitante (D-1).' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertSame( 403, $this->request( 'GET', '/dashboard' )->get_status(), 'Sin eventos_manage (R-22).' );
	}

	/**
	 * Fecha de calendario desplazada.
	 *
	 * @param string $date   Fecha `Y-m-d`.
	 * @param string $modify Desplazamiento.
	 */
	private function shift( string $date, string $modify ): string {
		return ( new DateTimeImmutable( $date, new DateTimeZone( 'UTC' ) ) )->modify( $modify )->format( 'Y-m-d' );
	}

	/**
	 * Petición a la API del plugin.
	 *
	 * @param string               $method Método HTTP.
	 * @param string               $path   Ruta relativa a `eventos/v1`.
	 * @param array<string, mixed> $body   Cuerpo JSON.
	 */
	private function request( string $method, string $path, array $body = [] ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/eventos/v1' . $path );

		if ( [] !== $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return rest_do_request( $request );
	}
}
