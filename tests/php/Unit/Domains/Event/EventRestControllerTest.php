<?php
/**
 * Pruebas unitarias de la API de eventos.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Domains\Event;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use Probolsas\Eventos\Core\Admin\AdminMenu;
use Probolsas\Eventos\Core\Admin\AdminPage;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Core\Container;
use Probolsas\Eventos\Core\View\View;
use Probolsas\Eventos\Domains\Event\Application\EventService;
use Probolsas\Eventos\Domains\Event\EventServiceProvider;
use Probolsas\Eventos\Domains\Event\Presentation\EventCsvExport;
use Probolsas\Eventos\Domains\Event\Presentation\EventRestController;
use Probolsas\Eventos\Domains\Event\Presentation\EventsPage;
use Probolsas\Eventos\Domains\EventType\Domain\EventType;
use Probolsas\Eventos\Domains\Media\Domain\MediaPolicy;
use Probolsas\Eventos\Shared\Time\Clock;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Ui\ColorContrast;
use Probolsas\Eventos\Tests\Unit\Support\InMemoryAttachmentGateway;
use Probolsas\Eventos\Tests\Unit\Support\InMemoryEventRepository;
use Probolsas\Eventos\Tests\Unit\Support\InMemoryEventTypeRepository;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Representación del contrato (docs/api/events.md), códigos HTTP, paginación, exportación CSV, pantalla
 * y proveedor. Permisos y rutas contra la API REST real de WordPress: tests/php/Integration/EventApiTest.php.
 *
 * @covers \Probolsas\Eventos\Domains\Event\Presentation\EventRestController
 * @covers \Probolsas\Eventos\Domains\Event\Presentation\EventCsvExport
 * @covers \Probolsas\Eventos\Domains\Event\Presentation\EventsPage
 * @covers \Probolsas\Eventos\Domains\Event\EventServiceProvider
 */
final class EventRestControllerTest extends UnitTestCase {

	/**
	 * Eventos en memoria.
	 *
	 * @var InMemoryEventRepository
	 */
	private InMemoryEventRepository $events;

	/**
	 * Tipos en memoria.
	 *
	 * @var InMemoryEventTypeRepository
	 */
	private InMemoryEventTypeRepository $types;

	/**
	 * Biblioteca de Medios en memoria.
	 *
	 * @var InMemoryAttachmentGateway
	 */
	private InMemoryAttachmentGateway $attachments;

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $value ): string => trim( strip_tags( $value ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Simula sanitize_text_field.
		Functions\when( 'sanitize_textarea_field' )->alias( static fn( string $value ): string => trim( strip_tags( $value ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Simula sanitize_textarea_field.
		Functions\when( 'get_current_user_id' )->justReturn( 3 );
		Functions\when( 'get_userdata' )->alias( static fn( int $id ): object|false => 3 === $id ? (object) [ 'display_name' => 'Talento Humano' ] : false );

		$this->events      = new InMemoryEventRepository();
		$this->types       = new InMemoryEventTypeRepository();
		$this->attachments = new InMemoryAttachmentGateway();
		$this->types->insert( new EventType( null, 'Cumpleaños', 'cumpleaños', 'cumpleanos', '#9D174D', 'cake-candles', true, '', 1, '2026-10-01 00:00:00', '2026-10-01 00:00:00' ) );
		$this->types->insert( new EventType( null, 'Pausas', 'pausas', 'pausas', '#FDE68A', 'mug-hot', false, '', 2, '2026-10-01 00:00:00', '2026-10-01 00:00:00' ) );
		$this->attachments->add( 315, 'image/jpeg' );
	}

	public function test_routes_follow_the_contract_permissions(): void {
		$routes = [];
		Functions\when( 'register_rest_route' )->alias(
			static function ( string $route_namespace, string $route, array $endpoints ) use ( &$routes ): void {
				$routes[ $route ] = isset( $endpoints['methods'] ) ? [ $endpoints ] : $endpoints;
			}
		);

		$this->controller()->register_routes();

		$permissions = [];
		foreach ( $routes as $route => $endpoints ) {
			foreach ( $endpoints as $endpoint ) {
				$permissions[] = $endpoint['methods'] . ' ' . $route . ' → ' . $endpoint['permission_callback'][1];
			}
		}

		$this->assertSame(
			[
				'GET /events → can_manage',
				'POST /events → can_manage',
				'GET /events/export\.csv → can_manage',
				'GET /events/(?P<id>\d+) → can_view',
				'PUT /events/(?P<id>\d+) → can_manage',
				'DELETE /events/(?P<id>\d+) → can_manage',
			],
			$permissions
		);
	}

	public function test_registers_the_routes_and_the_csv_delivery(): void {
		Functions\expect( 'add_action' )->once()->with( 'rest_api_init', \Mockery::type( 'array' ) );
		Functions\expect( 'add_filter' )->once()->with( 'rest_pre_serve_request', \Mockery::type( 'array' ), 10, 3 );

		$this->controller()->register();
	}

	public function test_store_returns_201_with_the_contract_representation(): void {
		$response = $this->controller()->store( new WP_REST_Request( $this->body() ) );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );
		$this->assertSame(
			[
				'id'          => 1,
				'title'       => 'Cumpleaños de Ana María',
				'description' => 'Celebración en la sala de juntas.',
				'type'        => [
					'id'        => 1,
					'name'      => 'Cumpleaños',
					'slug'      => 'cumpleanos',
					'color'     => '#9D174D',
					'text_tone' => 'light',
					'icon'      => 'cake-candles',
				],
				'start_date'  => '2026-10-07',
				'start_time'  => '15:00',
				'end_date'    => null,
				'end_time'    => null,
				'all_day'     => false,
				'is_past'     => false,
				'attachment'  => [
					'id'            => 315,
					'kind'          => 'image',
					'mime'          => 'image/jpeg',
					'url'           => 'https://intranet.test/uploads/archivo-315.jpg',
					'thumbnail_url' => null,
					'title'         => 'archivo-315',
					'filename'      => 'archivo-315.jpg',
					'filesize'      => 1024,
				],
				'created_by'  => [
					'id'   => 3,
					'name' => 'Talento Humano',
				],
				'updated_by'  => [
					'id'   => 3,
					'name' => 'Talento Humano',
				],
				'created_at'  => '2026-10-07T20:30:00-05:00',
				'updated_at'  => '2026-10-07T20:30:00-05:00',
			],
			$response->get_data()['data']
		);
	}

	public function test_the_input_is_sanitized(): void {
		$data = $this->controller()->store(
			new WP_REST_Request(
				$this->body(
					[
						'title'       => '  <b>Cumpleaños</b> de Ana ',
						'description' => '<script>x</script>Torta',
						'start_time'  => '',
						'type_id'     => 2,
					]
				)
			)
		)->get_data()['data'];

		$this->assertSame( 'Cumpleaños de Ana', $data['title'] );
		$this->assertSame( 'xTorta', $data['description'] );
		$this->assertTrue( $data['all_day'] );
		$this->assertSame( 'dark', $data['type']['text_tone'], 'Tipo de color claro: texto negro (R-02).' );
	}

	public function test_validation_errors_are_422_with_field_errors(): void {
		$response = $this->controller()->store( new WP_REST_Request( $this->body( [ 'title' => '' ] ) ) );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'eventos_validation_failed', $response->get_error_code() );
		$this->assertSame( 422, $response->get_error_data()['status'] );
		$this->assertSame( [ 'El campo «Título» es obligatorio.' ], $response->get_error_data()['errors']['title'] );
	}

	public function test_index_paginates_with_total_headers(): void {
		$controller = $this->controller();
		foreach ( range( 1, 3 ) as $day ) {
			$controller->store( new WP_REST_Request( $this->body( [ 'start_date' => "2026-10-0{$day}" ] ) ) );
		}

		$response = $controller->index(
			new WP_REST_Request(
				[
					'per_page' => '25',
					'search'   => 'ana',
				]
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertCount( 3, $response->get_data()['data'] );
		$this->assertSame( '3', $response->get_headers()['X-WP-Total'] );
		$this->assertSame( '1', $response->get_headers()['X-WP-TotalPages'] );
		$this->assertTrue( $response->get_data()['data'][0]['is_past'] );

		$empty = $controller->index( new WP_REST_Request( [ 'page' => '9' ] ) );
		$this->assertSame( [], $empty->get_data()['data'], 'Página fuera de rango: lista vacía con 200.' );
		$this->assertSame( 422, $controller->index( new WP_REST_Request( [ 'per_page' => '200' ] ) )->get_error_data()['status'] );
	}

	public function test_show_includes_the_events_of_the_same_day(): void {
		$controller = $this->controller();
		$controller->store( new WP_REST_Request( $this->body( [ 'start_time' => '16:00' ] ) ) );
		$controller->store(
			new WP_REST_Request(
				$this->body(
					[
						'start_time' => '09:00',
						'title'      => 'Desayuno de bienvenida',
					]
				)
			)
		);

		$data = $controller->show( new WP_REST_Request( [ 'id' => '1' ] ) )->get_data()['data'];

		$this->assertSame(
			[
				[
					'id'         => 2,
					'title'      => 'Desayuno de bienvenida',
					'start_time' => '09:00',
				],
				[
					'id'         => 1,
					'title'      => 'Cumpleaños de Ana María',
					'start_time' => '16:00',
				],
			],
			$data['same_day']
		);
		$this->assertSame( 404, $controller->show( new WP_REST_Request( [ 'id' => '99' ] ) )->get_error_data()['status'] );
	}

	public function test_update_and_destroy(): void {
		$controller = $this->controller();
		$controller->store( new WP_REST_Request( $this->body() ) );
		Functions\when( 'get_current_user_id' )->justReturn( 8 );

		$updated = $controller->update(
			new WP_REST_Request(
				$this->body(
					[
						'id'    => '1',
						'title' => 'Cumpleaños de Ana',
					]
				)
			)
		)->get_data()['data'];
		$deleted = $controller->destroy( new WP_REST_Request( [ 'id' => '1' ] ) )->get_data()['data'];

		$this->assertSame( 'Cumpleaños de Ana', $updated['title'] );
		$this->assertNull( $updated['updated_by'], 'Un usuario eliminado se entrega como null.' );
		$this->assertSame(
			[
				'id'   => 3,
				'name' => 'Talento Humano',
			],
			$updated['created_by']
		);
		$this->assertSame(
			[
				'deleted' => true,
				'id'      => 1,
			],
			$deleted
		);
		$this->assertSame( [], $this->events->events );
		$this->assertNotNull( $this->attachments->find( 315 ), 'D-4: el adjunto sigue en la biblioteca.' );
	}

	public function test_a_missing_type_or_attachment_is_presented_as_null(): void {
		$controller = $this->controller();
		$controller->store( new WP_REST_Request( $this->body() ) );
		unset( $this->types->types[1], $this->attachments->attachments[315] );

		// Otra petición: el controlador guarda los tipos leídos solo durante una petición.
		$data = $this->controller()->show( new WP_REST_Request( [ 'id' => '1' ] ) )->get_data()['data'];

		$this->assertNull( $data['type'] );
		$this->assertNull( $data['attachment'] );
	}

	public function test_export_returns_a_csv_with_the_active_filters(): void {
		$controller = $this->controller();
		$controller->store( new WP_REST_Request( $this->body( [ 'title' => '=HYPERLINK("x")' ] ) ) );
		$controller->store(
			new WP_REST_Request(
				$this->body(
					[
						'type_id'       => 2,
						'title'         => 'Pausa activa',
						'start_time'    => '',
						'attachment_id' => '',
						'description'   => "Línea 1\nLínea 2; con separador",
					]
				)
			)
		);

		$response = $controller->export( new WP_REST_Request( [ 'type' => '2' ] ) );
		$csv      = (string) $response->get_data();

		$this->assertSame( 'text/csv; charset=utf-8', $response->get_headers()['Content-Type'] );
		$this->assertSame( 'attachment; filename="eventos-2026-10-07.csv"', $response->get_headers()['Content-Disposition'] );
		$this->assertStringStartsWith( "\xEF\xBB\xBF" . 'Evento;Tipo;Fecha;Hora;Descripción;Adjunto;"Creado por";Actualizado' . "\n", $csv );
		$this->assertStringContainsString( "\"Pausa activa\";Pausas;07/10/2026;\"Todo el día\";\"Línea 1\nLínea 2; con separador\";No;\"Talento Humano\";\"07/10/2026 08:30 p. m.\"", $csv );
		$this->assertStringNotContainsString( 'HYPERLINK', $csv, 'Solo los eventos del tipo filtrado.' );

		$all = (string) $controller->export( new WP_REST_Request() )->get_data();
		$this->assertStringContainsString( "\"'=HYPERLINK(\"\"x\"\")\";Cumpleaños;07/10/2026;\"03:00 p. m.\"", $all, 'Las fórmulas se neutralizan con un apóstrofo.' );
		$this->assertSame( 422, $controller->export( new WP_REST_Request( [ 'orderby' => 'x' ] ) )->get_error_data()['status'] );
	}

	public function test_the_csv_is_served_as_a_file_only_for_the_export_route(): void {
		$controller = $this->controller();
		$csv        = new WP_REST_Response( "\xEF\xBB\xBFEvento\n", 200 );
		$route      = EventRestController::EXPORT_ROUTE;

		$this->assertFalse( $controller->serve_csv( false, $csv, new WP_REST_Request( [], '/eventos/v1/events' ) ) );
		$this->assertTrue( $controller->serve_csv( true, $csv, new WP_REST_Request( [], $route ) ), 'Respeta si otro ya la entregó.' );
		$this->assertFalse( $controller->serve_csv( false, new WP_REST_Response( [ 'code' => 'x' ], 422 ), new WP_REST_Request( [], $route ) ) );

		ob_start();
		$served = $controller->serve_csv( false, $csv, new WP_REST_Request( [], $route ) );
		$output = (string) ob_get_clean();

		$this->assertTrue( $served );
		$this->assertSame( "\xEF\xBB\xBFEvento\n", $output );
	}

	public function test_the_events_page_is_the_menu_root(): void {
		$page = new EventsPage( new View( $this->plugin_dir() . 'templates' ) );

		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		// Contrato con el Frontend: templates/admin/layout.php y templates/partials/mount.php (H-203).
		$this->assertStringContainsString( 'class="wrap ep-app" data-ep-screen="eventos-probolsas"', $html );
		$this->assertStringContainsString( '<i class="fa-solid fa-calendar-days"></i>', $html );
		$this->assertStringContainsString( 'id="ep-events" class="ep-mount" aria-busy="true"', $html );

		$this->assertSame( AdminMenu::ROOT_SLUG, $page->slug(), 'QA-022: Eventos es la pantalla principal del menú.' );
		$this->assertSame( [ 'Eventos', 'Eventos', 'eventos_manage', 10 ], [ $page->page_title(), $page->menu_title(), $page->capability(), $page->position() ] );
	}

	public function test_the_provider_wires_the_domain_and_publishes_the_rules(): void {
		$container = new Container();
		$container->set( View::class, fn(): View => new View( $this->plugin_dir() . 'templates' ) );
		( new EventServiceProvider() )->register( $container );

		$this->assertTrue( $container->has( EventService::class ) );
		$this->assertTrue( $container->has( EventRestController::class ) );
		$this->assertSame( [ EventsPage::class ], array_map( 'get_class', $container->tagged( AdminMenu::PAGES_TAG, AdminPage::class ) ) );

		$config = EventServiceProvider::add_client_rules( [ 'rules' => [ 'event_type' => [ 'name' => [] ] ] ] );
		$this->assertSame( [ 'event_type', 'event' ], array_keys( $config['rules'] ) );
		$this->assertSame( EventService::client_rules(), $config['rules']['event'] );
	}

	/**
	 * Cuerpo válido de un evento, con cambios.
	 *
	 * @param array<string, mixed> $changes Cambios.
	 *
	 * @return array<string, mixed>
	 */
	private function body( array $changes = [] ): array {
		return [
			'title'         => 'Cumpleaños de Ana María',
			'type_id'       => 1,
			'start_date'    => '2026-10-07',
			'start_time'    => '15:00',
			'description'   => 'Celebración en la sala de juntas.',
			'attachment_id' => 315,
			...$changes,
		];
	}

	/**
	 * Controlador con reloj fijo (2026-10-08 01:30 UTC = 7 de octubre, 8:30 p. m. en Bogotá).
	 */
	private function controller(): EventRestController {
		$config = new Config(
			[
				'ui' => [
					'timezone'          => 'America/Bogota',
					'date_format'       => 'd/m/Y',
					'time_format'       => 'h:i',
					'meridiem'          => [
						'am' => 'a. m.',
						'pm' => 'p. m.',
					],
					'page_sizes'        => [ 25, 50, 100 ],
					'default_page_size' => 25,
					'csv_separator'     => ';',
				],
			]
		);
		$clock  = new class() implements Clock {
			public function now(): DateTimeImmutable {
				return new DateTimeImmutable( '2026-10-08 01:30:00', new DateTimeZone( 'UTC' ) );
			}
		};
		$dates  = DateFormatter::from_config( $config, $clock );
		$policy = new MediaPolicy( [ 'image/jpeg', 'application/pdf' ] );

		return new EventRestController(
			new EventService( $this->events, $this->types, $this->attachments, $policy, $dates, $config ),
			$this->types,
			$this->attachments,
			new EventCsvExport( $dates, $config ),
			$dates,
			new ColorContrast()
		);
	}
}
