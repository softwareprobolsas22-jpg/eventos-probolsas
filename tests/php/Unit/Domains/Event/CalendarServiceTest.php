<?php
/**
 * Pruebas de las consultas del calendario.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Domains\Event;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Domains\Event\Application\CalendarService;
use Probolsas\Eventos\Domains\Event\Domain\Event;
use Probolsas\Eventos\Domains\Event\Domain\EventSchedule;
use Probolsas\Eventos\Shared\Time\Clock;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Validation\ValidationException;
use Probolsas\Eventos\Tests\Unit\Support\InMemoryEventRepository;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * Feed por rango y próximos eventos (docs/api/events.md, «Calendario»). Reloj fijo: 2026-10-08 01:30 UTC,
 * que en Bogotá todavía es el 7 de octubre a las 8:30 p. m. (RL-02).
 *
 * @covers \Probolsas\Eventos\Domains\Event\Application\CalendarService
 */
final class CalendarServiceTest extends UnitTestCase {

	/**
	 * Eventos en memoria.
	 *
	 * @var InMemoryEventRepository
	 */
	private InMemoryEventRepository $events;

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();

		$this->events = new InMemoryEventRepository();
		$this->add( 'Ayer', '2026-10-06', '09:00', 1 );
		$this->add( 'Hoy todo el día', '2026-10-07', null, 2 );
		$this->add( 'Hoy por la tarde', '2026-10-07', '15:00', 1 );
		$this->add( 'Fin de mes', '2026-10-31', '08:00', 2 );
		$this->add( 'Noviembre', '2026-11-01', null, 1 );
	}

	public function test_the_feed_returns_the_events_of_the_range_with_an_exclusive_end(): void {
		$titles = $this->titles( $this->service()->feed( $this->range( '2026-10-07', '2026-11-01' ) ) );

		$this->assertSame( [ 'Hoy todo el día', 'Hoy por la tarde', 'Fin de mes' ], $titles, 'El 1 de noviembre es el fin exclusivo de FullCalendar.' );
	}

	public function test_the_feed_filters_by_type(): void {
		$this->assertSame( [ 'Hoy por la tarde' ], $this->titles( $this->service()->feed( $this->range( '2026-10-07', '2026-10-08', [ '1' ] ) ) ) );
		$this->assertSame( [ 'Hoy todo el día', 'Hoy por la tarde' ], $this->titles( $this->service()->feed( $this->range( '2026-10-07', '2026-10-08', [ '1', '2', '2' ] ) ) ) );
	}

	public function test_the_feed_finds_multi_day_events_by_overlap(): void {
		// D-7 (v1.1): un evento de varios días aparece en cualquier rango que toque.
		$this->events->insert( new Event( null, 1, 'Semana de la salud', '', new EventSchedule( '2026-09-28', null, '2026-10-02' ), null, 1, 1, '2026-09-01 00:00:00', '2026-09-01 00:00:00' ) );

		$this->assertSame( [ 'Semana de la salud' ], $this->titles( $this->service()->feed( $this->range( '2026-10-01', '2026-10-02' ) ) ) );
	}

	public function test_a_full_month_view_of_six_weeks_fits_the_limit(): void {
		// Octubre de 2026 en la vista de mes: del 27 de septiembre al 8 de noviembre (42 días).
		$this->assertCount( 5, $this->service()->feed( $this->range( '2026-09-27', '2026-11-08' ) ) );
		$this->assertCount( 5, $this->service()->feed( $this->range( '2026-09-15', '2026-11-16' ) ), '62 días exactos.' );
	}

	/**
	 * Rangos no válidos.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
	 */
	public static function invalid_ranges(): array {
		return [
			'sin inicio'       => [ [ 'end' => '2026-10-08' ], 'start', 'El campo «Inicio» es obligatorio.' ],
			'sin fin'          => [ [ 'start' => '2026-10-07' ], 'end', 'El campo «Fin» es obligatorio.' ],
			'fecha irreal'     => [
				[
					'start' => '2026-02-30',
					'end'   => '2026-03-02',
				],
				'start',
				'El campo «Inicio» debe ser una fecha válida.',
			],
			'con hora y zona'  => [
				[
					'start' => '2026-10-07T00:00:00Z',
					'end'   => '2026-10-08',
				],
				'start',
				'El campo «Inicio» debe ser una fecha válida.',
			],
			'fin igual'        => [
				[
					'start' => '2026-10-07',
					'end'   => '2026-10-07',
				],
				'end',
				'La fecha «Fin» debe ser posterior a la fecha «Inicio».',
			],
			'fin anterior'     => [
				[
					'start' => '2026-10-07',
					'end'   => '2026-10-01',
				],
				'end',
				'La fecha «Fin» debe ser posterior a la fecha «Inicio».',
			],
			'más de 62 días'   => [
				[
					'start' => '2026-09-15',
					'end'   => '2026-11-17',
				],
				'end',
				'El calendario consulta máximo 62 días a la vez.',
			],
			'tipo no numérico' => [
				[
					'start' => '2026-10-07',
					'end'   => '2026-10-08',
					'types' => [ 'cumpleanos' ],
				],
				'types',
				'El filtro de tipos no es válido.',
			],
			'tipo cero'        => [
				[
					'start' => '2026-10-07',
					'end'   => '2026-10-08',
					'types' => '0',
				],
				'types',
				'El filtro de tipos no es válido.',
			],
		];
	}

	/**
	 * Un rango no válido responde 422 con el error en su campo.
	 *
	 * @dataProvider invalid_ranges
	 *
	 * @param array<string, mixed> $params  Parámetros.
	 * @param string               $field   Campo con error.
	 * @param string               $message Mensaje esperado.
	 */
	public function test_invalid_ranges_are_rejected( array $params, string $field, string $message ): void {
		try {
			$this->service()->feed( $params );
			$this->fail( 'Se esperaba un error de validación.' );
		} catch ( ValidationException $error ) {
			$this->assertSame( [ $message ], $error->errors()[ $field ] ?? null, (string) wp_json_encode( $error->errors() ) );
		}
	}

	public function test_upcoming_starts_today_in_colombia(): void {
		// A las 8:30 p. m. del 7 de octubre en Bogotá ya es el 8 en UTC: los eventos del 7 siguen siendo próximos.
		$this->assertSame( [ 'Hoy todo el día', 'Hoy por la tarde', 'Fin de mes', 'Noviembre' ], $this->titles( $this->service()->upcoming( [] ) ) );
	}

	public function test_upcoming_honours_the_limit_and_the_types(): void {
		$this->assertSame( [ 'Hoy todo el día', 'Hoy por la tarde' ], $this->titles( $this->service()->upcoming( [ 'limit' => '2' ] ) ) );
		$this->assertSame( [ 'Hoy por la tarde', 'Noviembre' ], $this->titles( $this->service()->upcoming( [ 'types' => [ '1' ] ] ) ) );

		for ( $i = 1; $i <= 10; $i++ ) {
			$this->add( "Diciembre {$i}", '2026-12-' . sprintf( '%02d', $i ), null, 1 );
		}
		$this->assertCount( CalendarService::UPCOMING_DEFAULT_LIMIT, $this->service()->upcoming( [] ) );
		$this->assertCount( 14, $this->service()->upcoming( [ 'limit' => '20' ] ) );
	}

	/**
	 * Límites no válidos.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function invalid_limits(): array {
		return [
			'cero'        => [ '0' ],
			'más de 20'   => [ '21' ],
			'texto'       => [ 'cinco' ],
			'con decimal' => [ '2.5' ],
		];
	}

	/**
	 * El límite va de 1 a 20.
	 *
	 * @dataProvider invalid_limits
	 *
	 * @param string $limit Límite.
	 */
	public function test_upcoming_rejects_an_invalid_limit( string $limit ): void {
		try {
			$this->service()->upcoming( [ 'limit' => $limit ] );
			$this->fail( 'Se esperaba un error de validación.' );
		} catch ( ValidationException $error ) {
			$this->assertSame( [ 'El campo «Cantidad» debe ser un número entre 1 y 20.' ], $error->errors()['limit'] );
		}
	}

	/**
	 * Agrega un evento.
	 *
	 * @param string      $title   Título.
	 * @param string      $date    Fecha.
	 * @param string|null $time    Hora.
	 * @param int         $type_id Tipo.
	 */
	private function add( string $title, string $date, ?string $time, int $type_id ): void {
		$this->events->insert( new Event( null, $type_id, $title, '', new EventSchedule( $date, $time ), null, 1, 1, '2026-10-01 00:00:00', '2026-10-01 00:00:00' ) );
	}

	/**
	 * Parámetros del feed.
	 *
	 * @param string       $start Inicio.
	 * @param string       $end   Fin exclusivo.
	 * @param list<string> $types Tipos.
	 *
	 * @return array<string, mixed>
	 */
	private function range( string $start, string $end, array $types = [] ): array {
		return [
			'start' => $start,
			'end'   => $end,
			'types' => $types,
		];
	}

	/**
	 * Títulos de una lista de eventos.
	 *
	 * @param list<Event> $events Eventos.
	 *
	 * @return list<string>
	 */
	private function titles( array $events ): array {
		return array_map( static fn( Event $event ): string => $event->title, $events );
	}

	/**
	 * Servicio con reloj fijo.
	 */
	private function service(): CalendarService {
		$clock = new class() implements Clock {
			public function now(): DateTimeImmutable {
				return new DateTimeImmutable( '2026-10-08 01:30:00', new DateTimeZone( 'UTC' ) );
			}
		};

		return new CalendarService( $this->events, DateFormatter::from_config( new Config( [ 'ui' => [ 'timezone' => 'America/Bogota' ] ] ), $clock ) );
	}
}
