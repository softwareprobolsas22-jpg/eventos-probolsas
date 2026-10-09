<?php
/**
 * Pruebas unitarias de la API del calendario.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Domains\Event;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Core\Container;
use Probolsas\Eventos\Domains\Event\Application\CalendarService;
use Probolsas\Eventos\Domains\Event\Application\EventService;
use Probolsas\Eventos\Domains\Event\Domain\Event;
use Probolsas\Eventos\Domains\Event\Domain\EventSchedule;
use Probolsas\Eventos\Domains\Event\EventServiceProvider;
use Probolsas\Eventos\Domains\Event\Presentation\CalendarFeedController;
use Probolsas\Eventos\Domains\Event\Presentation\EventPresenter;
use Probolsas\Eventos\Domains\Event\Presentation\IcsCalendar;
use Probolsas\Eventos\Domains\Event\Presentation\IcsController;
use Probolsas\Eventos\Domains\Event\Presentation\UpcomingController;
use Probolsas\Eventos\Domains\EventType\Domain\EventType;
use Probolsas\Eventos\Domains\Media\Domain\MediaPolicy;
use Probolsas\Eventos\Shared\Cache\ResponseCache;
use Probolsas\Eventos\Shared\Time\Clock;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Ui\ColorContrast;
use Probolsas\Eventos\Tests\Unit\Support\InMemoryAttachmentGateway;
use Probolsas\Eventos\Tests\Unit\Support\InMemoryEventRepository;
use Probolsas\Eventos\Tests\Unit\Support\InMemoryEventTypeRepository;
use Probolsas\Eventos\Tests\Unit\Support\TransientStore;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Feed de FullCalendar, próximos eventos y `.ics` (docs/api/events.md, «Calendario»). Permisos contra la
 * API REST real de WordPress: tests/php/Integration/CalendarApiTest.php.
 *
 * @covers \Probolsas\Eventos\Domains\Event\Presentation\CalendarFeedController
 * @covers \Probolsas\Eventos\Domains\Event\Presentation\UpcomingController
 * @covers \Probolsas\Eventos\Domains\Event\Presentation\IcsController
 * @covers \Probolsas\Eventos\Domains\Event\EventServiceProvider
 * @covers \Probolsas\Eventos\Shared\Http\RestController
 */
final class CalendarControllersTest extends UnitTestCase {

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
	 * Reloj fijo: 2026-10-08 01:30 UTC = 7 de octubre, 8:30 p. m. en Bogotá.
	 *
	 * @var Clock
	 */
	private Clock $clock;

	/**
	 * Transients en memoria (caché de respuestas, H-401).
	 *
	 * @var TransientStore
	 */
	private TransientStore $store;

	/**
	 * Caché compartida por los controladores (en el plugin, una sola instancia del contenedor).
	 *
	 * @var ResponseCache
	 */
	private ResponseCache $cache;

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $value ): string => trim( strip_tags( $value ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Simula sanitize_text_field.
		Functions\when( 'get_userdata' )->justReturn( false );
		$this->store = TransientStore::install();
		$this->cache = new ResponseCache();

		$this->clock  = new class() implements Clock {
			public function now(): DateTimeImmutable {
				return new DateTimeImmutable( '2026-10-08 01:30:00', new DateTimeZone( 'UTC' ) );
			}
		};
		$this->events = new InMemoryEventRepository();
		$this->types  = new InMemoryEventTypeRepository();
		$this->types->insert( new EventType( null, 'Cumpleaños', 'cumpleaños', 'cumpleanos', '#9D174D', 'cake-candles', false, '', 1, '2026-10-01 00:00:00', '2026-10-01 00:00:00' ) );
		$this->types->insert( new EventType( null, 'Pausas', 'pausas', 'pausas', '#FDE68A', 'mug-hot', false, '', 2, '2026-10-01 00:00:00', '2026-10-01 00:00:00' ) );

		$this->add( 1, 'Cumpleaños de Ana María', '2026-10-07', '15:00', 315 );
		$this->add( 2, 'Pausa activa', '2026-10-07', null );
		$this->add( 1, 'Cumpleaños de Luis', '2026-10-20', '09:30' );
	}

	public function test_routes_are_readable_by_any_collaborator(): void {
		$routes = [];
		Functions\when( 'register_rest_route' )->alias(
			static function ( string $route_namespace, string $route, array $endpoint ) use ( &$routes ): void {
				$routes[] = $endpoint['methods'] . ' /' . $route_namespace . $route . ' → ' . $endpoint['permission_callback'][1];
			}
		);

		[ $feed, $upcoming, $ics ] = $this->controllers();
		$feed->register_routes();
		$upcoming->register_routes();
		$ics->register_routes();

		$this->assertSame(
			[
				'GET /eventos/v1/calendar → can_view',
				'GET /eventos/v1/upcoming → can_view',
				'GET /eventos/v1/events/(?P<id>\d+)/ics → can_view',
			],
			$routes
		);
	}

	public function test_the_feed_returns_full_calendar_event_inputs_without_time_zone(): void {
		[ $feed ] = $this->controllers();

		$response = $feed->index(
			new WP_REST_Request(
				[
					'start' => '2026-09-27',
					'end'   => '2026-11-08',
				]
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 'no-store, private', $response->get_headers()['Cache-Control'] );
		$data = $response->get_data()['data'];
		$this->assertSame(
			[
				'id'              => '1',
				'title'           => 'Cumpleaños de Ana María',
				'start'           => '2026-10-07T15:00:00',
				'end'             => null,
				'allDay'          => false,
				'backgroundColor' => '#9D174D',
				'borderColor'     => '#9D174D',
				'textColor'       => '#FFFFFF',
				'extendedProps'   => [
					'typeId'        => 1,
					'icon'          => 'cake-candles',
					'hasAttachment' => true,
				],
			],
			$data[1]
		);
		$this->assertSame( [ '2026-10-07', true, '#000000', false ], [ $data[0]['start'], $data[0]['allDay'], $data[0]['textColor'], $data[0]['extendedProps']['hasAttachment'] ], 'Todo el día, con texto oscuro sobre un color claro.' );
		$this->assertCount( 3, $data );
	}

	public function test_the_feed_filters_by_type_and_validates_the_range(): void {
		[ $feed ] = $this->controllers();

		$data = $feed->index(
			new WP_REST_Request(
				[
					'start' => '2026-10-01',
					'end'   => '2026-11-01',
					'types' => [ '2' ],
				]
			)
		)->get_data()['data'];
		$this->assertSame( [ 'Pausa activa' ], array_column( $data, 'title' ) );

		$error = $feed->index(
			new WP_REST_Request(
				[
					'start' => '2026-01-01',
					'end'   => '2026-12-31',
				]
			)
		);
		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 422, $error->get_error_data()['status'] );
		$this->assertArrayHasKey( 'end', $error->get_error_data()['errors'] );

		$error = $feed->index(
			new WP_REST_Request(
				[
					'start' => [ 'x' ],
					'end'   => '2026-10-08',
				]
			)
		);
		$this->assertSame( [ 'El campo «Inicio» es obligatorio.' ], $error->get_error_data()['errors']['start'], 'Un valor que no es texto se trata como vacío.' );
	}

	public function test_multi_day_events_get_an_exclusive_end(): void {
		// D-7 (v1.1): el feed ya calcula el fin; en v1 siempre es null.
		$this->events->insert( new Event( null, 1, 'Semana de la salud', '', new EventSchedule( '2026-10-12', null, '2026-10-16' ), null, 1, 1, '2026-10-01 00:00:00', '2026-10-01 00:00:00' ) );
		$this->events->insert( new Event( null, 1, 'Congreso', '', new EventSchedule( '2026-10-13', '08:00', '2026-10-14', '17:00' ), null, 1, 1, '2026-10-01 00:00:00', '2026-10-01 00:00:00' ) );
		[ $feed ] = $this->controllers();

		$data = $feed->index(
			new WP_REST_Request(
				[
					'start' => '2026-10-12',
					'end'   => '2026-10-19',
				]
			)
		)->get_data()['data'];

		$this->assertSame( [ '2026-10-12', '2026-10-17' ], [ $data[0]['start'], $data[0]['end'] ] );
		$this->assertSame( [ '2026-10-13T08:00:00', '2026-10-14T17:00:00' ], [ $data[1]['start'], $data[1]['end'] ] );
	}

	public function test_upcoming_returns_the_event_representation(): void {
		[ , $upcoming ] = $this->controllers();

		$data = $upcoming->index( new WP_REST_Request( [ 'limit' => '2' ] ) )->get_data()['data'];

		$this->assertSame( [ 'Pausa activa', 'Cumpleaños de Ana María' ], array_column( $data, 'title' ) );
		$this->assertSame( [ 'id', 'name', 'slug', 'color', 'text_tone', 'icon' ], array_keys( $data[0]['type'] ) );
		$this->assertArrayNotHasKey( 'same_day', $data[0] );
		$this->assertFalse( $data[0]['is_past'], 'Hoy en Colombia, aunque en UTC ya sea mañana.' );

		$types = $upcoming->index( new WP_REST_Request( [ 'types' => '1' ] ) )->get_data()['data'];
		$this->assertSame( [ 'Cumpleaños de Ana María', 'Cumpleaños de Luis' ], array_column( $types, 'title' ), 'Un solo tipo puede llegar sin corchetes.' );

		$this->assertSame( 422, $upcoming->index( new WP_REST_Request( [ 'limit' => '50' ] ) )->get_error_data()['status'] );
	}

	public function test_the_ics_is_a_calendar_file_and_404_for_a_missing_event(): void {
		[ , , $ics ] = $this->controllers();

		$response = $ics->show( new WP_REST_Request( [ 'id' => '1' ] ) );

		$this->assertSame( 'text/calendar; charset=utf-8', $response->get_headers()['Content-Type'] );
		$this->assertSame( 'attachment; filename="evento-1.ics"', $response->get_headers()['Content-Disposition'] );
		$this->assertStringContainsString( "DTSTART;TZID=America/Bogota:20261007T150000\r\n", $response->get_data() );
		$this->assertStringContainsString( "CATEGORIES:Cumpleaños\r\n", $response->get_data() );
		$this->assertStringContainsString( "DTSTAMP:20261008T013000Z\r\n", $response->get_data() );

		$missing = $ics->show( new WP_REST_Request( [ 'id' => '99' ] ) );
		$this->assertInstanceOf( WP_Error::class, $missing );
		$this->assertSame( 404, $missing->get_error_data()['status'] );
	}

	public function test_the_ics_is_served_as_a_file_only_for_its_route(): void {
		[ , , $ics ] = $this->controllers();
		$file        = new WP_REST_Response( "BEGIN:VCALENDAR\r\n", 200 );

		ob_start();
		$served = $ics->serve( false, $file, new WP_REST_Request( [], '/eventos/v1/events/1/ics' ) );
		$output = ob_get_clean();

		$this->assertTrue( $served );
		$this->assertSame( "BEGIN:VCALENDAR\r\n", $output );
		$this->assertFalse( $ics->serve( false, $file, new WP_REST_Request( [], '/eventos/v1/events/1' ) ) );
		$this->assertFalse( $ics->serve( false, new WP_REST_Response( [ 'data' => [] ], 404 ), new WP_REST_Request( [], '/eventos/v1/events/9/ics' ) ) );
		$this->assertTrue( $ics->serve( true, $file, new WP_REST_Request( [], '/eventos/v1/events/1/ics' ) ), 'Respeta a otra extensión que ya respondió.' );
	}

	public function test_the_ics_controller_registers_its_file_delivery(): void {
		Functions\expect( 'add_action' )->once()->with( 'rest_api_init', \Mockery::type( 'array' ) );
		Functions\expect( 'add_filter' )->once()->with( 'rest_pre_serve_request', \Mockery::type( 'array' ), 10, 3 );

		$this->controllers()[2]->register();
	}

	public function test_the_feed_and_upcoming_answer_from_the_cache_until_a_write(): void {
		$range               = [
			'start' => '2026-10-01',
			'end'   => '2026-11-01',
		];
		[ $feed, $upcoming ] = $this->controllers();

		$first = $feed->index( new WP_REST_Request( $range ) )->get_data()['data'];
		$this->add( 2, 'Nueva pausa', '2026-10-08', null );
		$this->assertSame( $first, $feed->index( new WP_REST_Request( $range ) )->get_data()['data'], 'Sin invalidar, la respuesta sale de la caché.' );
		$this->assertCount( 1, $this->store->transients );

		$upcoming->index( new WP_REST_Request( [ 'limit' => '2' ] ) );
		$this->assertCount( 2, $this->store->transients, 'Los próximos tienen su propia entrada (con «hoy» en la clave).' );

		$this->cache->flush();
		$this->assertCount( 4, $feed->index( new WP_REST_Request( $range ) )->get_data()['data'], 'Después de invalidar se lee otra vez.' );
	}

	public function test_an_invalid_range_is_not_cached(): void {
		[ $feed ] = $this->controllers();

		$feed->index(
			new WP_REST_Request(
				[
					'start' => '2026-01-01',
					'end'   => '2026-12-31',
				]
			)
		);

		$this->assertSame( 0, $this->store->writes );
	}

	public function test_the_provider_wires_the_calendar(): void {
		Functions\when( 'home_url' )->justReturn( 'https://intranet.probolsas.com/' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$container = new Container();
		$container->set( Config::class, static fn(): Config => new Config( [ 'ui' => [ 'timezone' => 'America/Bogota' ] ] ) );
		( new EventServiceProvider() )->register( $container );

		foreach ( [ CalendarService::class, CalendarFeedController::class, UpcomingController::class, IcsController::class, EventPresenter::class ] as $id ) {
			$this->assertTrue( $container->has( $id ), $id );
		}

		$event = new Event( 7, 1, 'Reunión', '', new EventSchedule( '2026-10-07', '15:00' ), null, 1, 1, '', '' );
		$this->assertStringContainsString( "UID:evento-7@intranet.probolsas.com\r\n", $container->get( IcsCalendar::class )->build( $event, '', $this->clock->now() ), 'El UID usa el dominio de la intranet.' );
	}

	/**
	 * Agrega un evento.
	 *
	 * @param int         $type_id    Tipo.
	 * @param string      $title      Título.
	 * @param string      $date       Fecha.
	 * @param string|null $time       Hora.
	 * @param int|null    $attachment Adjunto.
	 */
	private function add( int $type_id, string $title, string $date, ?string $time, ?int $attachment = null ): void {
		$this->events->insert( new Event( null, $type_id, $title, '', new EventSchedule( $date, $time ), $attachment, 3, 3, '2026-10-01 13:30:00', '2026-10-01 13:30:00' ) );
	}

	/**
	 * Controladores del feed, de los próximos y del `.ics`.
	 *
	 * @return array{0: CalendarFeedController, 1: UpcomingController, 2: IcsController}
	 */
	private function controllers(): array {
		$config      = new Config( [ 'ui' => [ 'timezone' => 'America/Bogota' ] ] );
		$dates       = DateFormatter::from_config( $config, $this->clock );
		$attachments = new InMemoryAttachmentGateway();
		$attachments->add( 315, 'image/jpeg' );
		$service   = new EventService( $this->events, $this->types, $attachments, new MediaPolicy( [ 'image/jpeg' ] ), $dates, $config );
		$presenter = new EventPresenter( $service, $this->types, $attachments, $dates, new ColorContrast() );
		$calendar  = new CalendarService( $this->events, $dates );

		return [
			new CalendarFeedController( $calendar, $presenter, new ColorContrast(), $this->cache ),
			new UpcomingController( $calendar, $presenter, $this->cache, $dates ),
			new IcsController( $service, $presenter, new IcsCalendar( new DateTimeZone( 'America/Bogota' ), 'intranet.probolsas.com' ), $this->clock ),
		];
	}
}
