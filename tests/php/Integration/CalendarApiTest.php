<?php
/**
 * Pruebas de integración del calendario de los colaboradores.
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
 * Contrato «Calendario» de docs/api/events.md contra WordPress y MySQL reales (H-301): feed por rango con
 * solapamiento y filtro de tipos, `.ics` con la hora de Colombia (RL-03), próximos eventos desde hoy en
 * Colombia, permisos (D-1, R-22) y los shortcodes `[eventos_calendario]` y `[eventos_proximos]`.
 *
 * @coversNothing
 */
final class CalendarApiTest extends IntegrationTestCase {

	/**
	 * Tipos iniciales por nombre.
	 *
	 * @var array<string, int>
	 */
	private array $types = [];

	public function set_up(): void {
		parent::set_up();
		$this->plugin()->activate();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Servidor REST de WordPress para la prueba.
		do_action( 'rest_api_init', $wp_rest_server ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook de WordPress.

		$manager = self::factory()->user->create_and_get( [ 'role' => 'editor' ] );
		$manager->add_cap( Capabilities::MANAGE );
		wp_set_current_user( $manager->ID );

		// De la semilla, solo «Reuniones laborales» no exige adjunto: un segundo tipo sin adjunto permite
		// probar el filtro sin subir archivos.
		$pauses = $this->request(
			'POST',
			'/event-types',
			[
				'name'                => 'Pausas activas',
				'color'               => '#669F30',
				'icon'                => 'mug-hot',
				'requires_attachment' => false,
			]
		);
		$this->assertSame( 201, $pauses->get_status(), (string) wp_json_encode( $pauses->get_data() ) );

		foreach ( $this->request( 'GET', '/event-types' )->get_data()['data'] as $type ) {
			$this->types[ $type['name'] ] = (int) $type['id'];
		}
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Restablece el servidor REST.

		parent::tear_down();
	}

	public function test_the_feed_returns_the_visible_range_for_full_calendar(): void {
		$meeting = $this->create( 'Reunión de planeación', '2026-10-07', '15:00', 'Reuniones laborales' );
		$this->create( 'Inducción', '2026-10-31', null, 'Pausas activas' );
		$this->create( 'Fuera del rango', '2026-11-08', null, 'Pausas activas' );
		$this->collaborator();

		// Vista de mes de octubre de 2026 con la semana desde el lunes: 28 de septiembre a 8 de noviembre (exclusivo).
		$response = $this->request(
			'GET',
			'/calendar',
			[],
			[
				'start' => '2026-09-28',
				'end'   => '2026-11-08',
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data()['data'];
		$this->assertSame( [ 'Reunión de planeación', 'Inducción' ], array_column( $data, 'title' ) );
		$this->assertSame( (string) $meeting, $data[0]['id'] );
		$this->assertSame( '2026-10-07T15:00:00', $data[0]['start'], 'R-08: sin zona horaria.' );
		$this->assertSame( [ '2026-10-31', true ], [ $data[1]['start'], $data[1]['allDay'] ] );
		$this->assertSame( '#1D4ED8', $data[0]['backgroundColor'], 'Color de la semilla (D-13).' );
		$this->assertSame( '#FFFFFF', $data[0]['textColor'] );
		$this->assertSame( 'briefcase', $data[0]['extendedProps']['icon'] );
		$this->assertSame( 'no-store, private', $response->get_headers()['Cache-Control'] );
	}

	public function test_the_feed_filters_by_type_and_rejects_invalid_ranges(): void {
		$this->create( 'Reunión de planeación', '2026-10-07', '15:00', 'Reuniones laborales' );
		$this->create( 'Inducción', '2026-10-07', null, 'Pausas activas' );

		$filtered = $this->request(
			'GET',
			'/calendar',
			[],
			[
				'start' => '2026-10-01',
				'end'   => '2026-11-01',
				'types' => [ $this->types['Pausas activas'] ],
			]
		);
		$this->assertSame( [ 'Inducción' ], array_column( $filtered->get_data()['data'], 'title' ) );

		$too_long = $this->request(
			'GET',
			'/calendar',
			[],
			[
				'start' => '2026-01-01',
				'end'   => '2026-06-01',
			]
		);
		$this->assertSame( 422, $too_long->get_status() );
		$this->assertSame( 'eventos_validation_failed', $too_long->get_data()['code'] );
		$this->assertArrayHasKey( 'end', $too_long->get_data()['data']['errors'] );

		$this->assertSame(
			422,
			$this->request(
				'GET',
				'/calendar',
				[],
				[
					'start' => '2026-10-07T00:00:00Z',
					'end'   => '2026-10-08',
				]
			)->get_status(),
			'Solo fechas sin zona.'
		);
		$this->assertSame( 422, $this->request( 'GET', '/calendar' )->get_status() );
	}

	public function test_the_ics_keeps_the_colombian_wall_time(): void {
		$id = $this->create( 'Reunión de planeación', '2026-10-07', '15:00', 'Reuniones laborales' );
		$this->collaborator();

		$response = $this->request( 'GET', "/events/{$id}/ics" );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'text/calendar; charset=utf-8', $response->get_headers()['Content-Type'] );
		$this->assertSame( "attachment; filename=\"evento-{$id}.ics\"", $response->get_headers()['Content-Disposition'] );
		$ics = (string) $response->get_data();
		$this->assertStringContainsString( "DTSTART;TZID=America/Bogota:20261007T150000\r\n", $ics, 'RL-03: la hora no se corre 5 horas.' );
		$this->assertStringContainsString( "TZOFFSETTO:-0500\r\n", $ics );
		$this->assertStringNotContainsString( '20261007T150000Z', $ics );

		$this->assertSame( 404, $this->request( 'GET', '/events/999999/ics' )->get_status() );
	}

	public function test_upcoming_starts_today_in_colombia(): void {
		$today     = $this->plugin()->container()->get( DateFormatter::class )->today();
		$yesterday = $this->shift( $today, '-1 day' );
		$this->create( 'Ayer', $yesterday, '09:00', 'Reuniones laborales' );
		$this->create( 'Hoy', $today, null, 'Pausas activas' );
		$this->create( 'Mañana', $this->shift( $today, '+1 day' ), '08:00', 'Reuniones laborales' );
		$this->create( 'Pasado mañana', $this->shift( $today, '+2 days' ), '08:00', 'Pausas activas' );
		$this->collaborator();

		$all = $this->request( 'GET', '/upcoming' )->get_data()['data'];
		$this->assertSame( [ 'Hoy', 'Mañana', 'Pasado mañana' ], array_column( $all, 'title' ) );
		$this->assertSame( [ 'id', 'name', 'slug', 'color', 'text_tone', 'icon' ], array_keys( $all[0]['type'] ) );

		$this->assertSame( [ 'Hoy' ], array_column( $this->request( 'GET', '/upcoming', [], [ 'limit' => 1 ] )->get_data()['data'], 'title' ) );
		$this->assertSame( [ 'Mañana' ], array_column( $this->request( 'GET', '/upcoming', [], [ 'types' => [ $this->types['Reuniones laborales'] ] ] )->get_data()['data'], 'title' ) );
		$this->assertSame( 422, $this->request( 'GET', '/upcoming', [], [ 'limit' => 21 ] )->get_status() );
	}

	public function test_permissions(): void {
		$id = $this->create( 'Reunión de planeación', '2026-10-07', '15:00', 'Reuniones laborales' );
		$this->collaborator();

		foreach ( [ '/calendar?start=2026-10-01&end=2026-11-01', '/upcoming', "/events/{$id}/ics" ] as $path ) {
			[ $route, $query ] = array_pad( explode( '?', $path ), 2, '' );
			parse_str( $query, $params );
			$this->assertSame( 200, $this->request( 'GET', $route, [], $params )->get_status(), "Suscriptor: {$path}." );
		}

		wp_set_current_user( 0 );
		foreach ( [ '/calendar?start=2026-10-01&end=2026-11-01', '/upcoming', "/events/{$id}/ics" ] as $path ) {
			[ $route, $query ] = array_pad( explode( '?', $path ), 2, '' );
			parse_str( $query, $params );
			$response = $this->request( 'GET', $route, [], $params );
			$this->assertSame( 401, $response->get_status(), "Visitante: {$path} (D-1, R-22)." );
			$this->assertStringNotContainsString( 'Reunión', (string) wp_json_encode( $response->get_data() ) );
		}
	}

	public function test_the_shortcodes_mount_widgets_only_for_collaborators(): void {
		$this->collaborator();
		$content = '[eventos_calendario tipos="pausas-activas"] [eventos_calendario] [eventos_proximos limite="3"]';
		$html    = do_shortcode( $content );

		$this->assertSame( 3, substr_count( $html, 'data-ep-widget=' ), 'RL-08: un contenedor por shortcode.' );
		$this->assertStringContainsString( 'data-ep-widget="calendar" data-ep-props="{&quot;types&quot;:[' . $this->types['Pausas activas'] . ']}"', $html );
		$this->assertStringContainsString( 'data-ep-widget="upcoming"', $html );
		$this->assertStringNotContainsString( ' id="', $html, 'RL-08: sin IDs que se puedan repetir.' );
		$this->assertTrue( wp_script_is( 'ep-public', 'enqueued' ) );

		wp_set_current_user( 0 );
		$notice = do_shortcode( '[eventos_calendario]' );
		$this->assertStringContainsString( 'Inicia sesión para ver el calendario de eventos.', $notice, 'D-1.' );
		$this->assertStringNotContainsString( 'data-ep-widget', $notice );
	}

	/**
	 * Cambia al usuario por un colaborador sin permisos de gestión.
	 */
	private function collaborator(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
	}

	/**
	 * Crea un evento con el gestor actual y devuelve su ID.
	 *
	 * @param string      $title Título.
	 * @param string      $date  Fecha.
	 * @param string|null $time  Hora.
	 * @param string      $type  Nombre del tipo (sin adjunto obligatorio).
	 */
	private function create( string $title, string $date, ?string $time, string $type ): int {
		$response = $this->request(
			'POST',
			'/events',
			[
				'title'      => $title,
				'type_id'    => $this->types[ $type ],
				'start_date' => $date,
				'start_time' => $time ?? '',
			]
		);
		$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );

		return (int) $response->get_data()['data']['id'];
	}

	/**
	 * Fecha de calendario desplazada.
	 *
	 * @param string $date   Fecha `Y-m-d`.
	 * @param string $modify Desplazamiento, por ejemplo `+1 day`.
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
	 * @param array<string, mixed> $query  Parámetros de la dirección.
	 */
	private function request( string $method, string $path, array $body = [], array $query = [] ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/eventos/v1' . $path );
		$request->set_query_params( $query );

		if ( [] !== $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return rest_do_request( $request );
	}
}
