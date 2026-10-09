<?php
/**
 * Pruebas del dashboard.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Domains\Dashboard;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Core\Container;
use Probolsas\Eventos\Domains\Dashboard\Application\DashboardService;
use Probolsas\Eventos\Domains\Dashboard\DashboardServiceProvider;
use Probolsas\Eventos\Domains\Dashboard\Presentation\DashboardRestController;
use Probolsas\Eventos\Domains\Event\Domain\Event;
use Probolsas\Eventos\Domains\Event\Domain\EventRepository;
use Probolsas\Eventos\Domains\Event\Domain\EventSchedule;
use Probolsas\Eventos\Domains\EventType\Domain\EventType;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeRepository;
use Probolsas\Eventos\Shared\Time\Clock;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Tests\Unit\Support\InMemoryEventRepository;
use Probolsas\Eventos\Tests\Unit\Support\InMemoryEventTypeRepository;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * Cifras de las tarjetas de «Eventos» (H-206). Reloj fijo: 2026-10-08 01:30 UTC = 7 de octubre a las
 * 8:30 p. m. en Bogotá (RL-02: «hoy» es el 7, no el 8).
 *
 * @covers \Probolsas\Eventos\Domains\Dashboard\Application\DashboardService
 * @covers \Probolsas\Eventos\Domains\Dashboard\Presentation\DashboardRestController
 * @covers \Probolsas\Eventos\Domains\Dashboard\DashboardServiceProvider
 */
final class DashboardTest extends UnitTestCase {

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

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();

		$this->events = new InMemoryEventRepository();
		$this->types  = new InMemoryEventTypeRepository();
		$this->types->insert( new EventType( null, 'Cumpleaños', 'cumpleaños', 'cumpleanos', '#9D174D', 'cake-candles', true, '', 1, '2026-10-01 00:00:00', '2026-10-01 00:00:00' ) );
		$this->types->insert( new EventType( null, 'Pausas', 'pausas', 'pausas', '#FDE68A', 'mug-hot', false, '', 2, '2026-10-01 00:00:00', '2026-10-01 00:00:00' ) );
		$this->types->insert( new EventType( null, 'Sin eventos', 'sin eventos', 'sin-eventos', '#155728', 'star', false, '', 3, '2026-10-01 00:00:00', '2026-10-01 00:00:00' ) );
	}

	public function test_the_summary_uses_the_date_of_colombia_and_lists_every_type(): void {
		$this->add( 1, '2026-10-06' ); // Ayer: solo cuenta en el total.
		$this->add( 1, '2026-10-07' ); // Hoy.
		$this->add( 2, '2026-10-07' ); // Hoy.
		$this->add( 2, '2026-11-05' ); // Último día de los 30 (7 de octubre + 29).
		$this->add( 1, '2026-11-06' ); // Fuera de los 30 días.
		// D-7 (v1.1): un evento que empezó antes y sigue hoy cuenta por solapamiento.
		$this->events->insert( new Event( null, 2, 'Semana', '', new EventSchedule( '2026-10-05', null, '2026-10-09' ), null, 1, 1, '', '' ) );
		$this->types->events[2] = ( $this->types->events[2] ?? 0 ) + 1;

		$this->assertSame(
			[
				'today'        => 3,
				'next_30_days' => 4,
				'by_type'      => [
					[
						'type_id' => 1,
						'count'   => 3,
					],
					[
						'type_id' => 2,
						'count'   => 3,
					],
					[
						'type_id' => 3,
						'count'   => 0,
					],
				],
				'total'        => 6,
				'date_from'    => '2026-10-07',
				'date_to'      => '2026-11-05',
			],
			$this->service()->summary()
		);
	}

	public function test_an_empty_install_has_zeros(): void {
		$summary = $this->service()->summary();

		$this->assertSame( [ 0, 0, 0 ], [ $summary['today'], $summary['next_30_days'], $summary['total'] ] );
		$this->assertCount( 3, $summary['by_type'] );
	}

	public function test_the_route_is_only_for_managers(): void {
		$routes = [];
		Functions\when( 'register_rest_route' )->alias(
			static function ( string $route_namespace, string $route, array $endpoint ) use ( &$routes ): void {
				$routes[] = $endpoint['methods'] . ' /' . $route_namespace . $route . ' → ' . $endpoint['permission_callback'][1];
			}
		);

		$controller = new DashboardRestController( $this->service() );
		$controller->register_routes();

		$this->assertSame( [ 'GET /eventos/v1/dashboard → can_manage' ], $routes );
		$response = $controller->show();
		$this->assertSame( 'no-store, private', $response->get_headers()['Cache-Control'] );
		$this->assertSame( 0, $response->get_data()['data']['total'] );
	}

	public function test_the_provider_registers_the_service_and_the_api(): void {
		$container = new Container();
		$container->set( EventRepository::class, fn(): EventRepository => $this->events );
		$container->set( EventTypeRepository::class, fn(): EventTypeRepository => $this->types );
		$container->set( DateFormatter::class, fn(): DateFormatter => $this->dates() );
		( new DashboardServiceProvider() )->register( $container );

		Functions\expect( 'add_action' )->once()->with( 'rest_api_init', \Mockery::type( 'array' ) );
		( new DashboardServiceProvider() )->boot( $container );

		$this->assertInstanceOf( DashboardService::class, $container->get( DashboardService::class ) );
	}

	/**
	 * Agrega un evento de todo el día.
	 *
	 * @param int    $type_id Tipo.
	 * @param string $date    Fecha.
	 */
	private function add( int $type_id, string $date ): void {
		$this->events->insert( new Event( null, $type_id, 'Evento', '', new EventSchedule( $date ), null, 1, 1, '', '' ) );
		$this->types->events[ $type_id ] = ( $this->types->events[ $type_id ] ?? 0 ) + 1;
	}

	/**
	 * Formateador con reloj fijo.
	 */
	private function dates(): DateFormatter {
		$clock = new class() implements Clock {
			public function now(): DateTimeImmutable {
				return new DateTimeImmutable( '2026-10-08 01:30:00', new DateTimeZone( 'UTC' ) );
			}
		};

		return DateFormatter::from_config( new Config( [ 'ui' => [ 'timezone' => 'America/Bogota' ] ] ), $clock );
	}

	/**
	 * Servicio con reloj fijo.
	 */
	private function service(): DashboardService {
		return new DashboardService( $this->events, $this->types, $this->dates() );
	}
}
