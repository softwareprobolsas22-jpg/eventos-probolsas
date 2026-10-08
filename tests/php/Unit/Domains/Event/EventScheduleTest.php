<?php
/**
 * Pruebas del horario de un evento.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Domains\Event;

use InvalidArgumentException;
use Probolsas\Eventos\Domains\Event\Domain\EventPage;
use Probolsas\Eventos\Domains\Event\Domain\EventQuery;
use Probolsas\Eventos\Domains\Event\Domain\EventSchedule;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * Invariantes de EventSchedule, preparado para D-7 (§5.6), y objetos de la búsqueda.
 *
 * @covers \Probolsas\Eventos\Domains\Event\Domain\EventSchedule
 * @covers \Probolsas\Eventos\Domains\Event\Domain\EventQuery
 * @covers \Probolsas\Eventos\Domains\Event\Domain\EventPage
 */
final class EventScheduleTest extends UnitTestCase {

	public function test_without_time_the_event_lasts_all_day(): void {
		$this->assertTrue( ( new EventSchedule( '2026-10-07' ) )->is_all_day() );
		$this->assertFalse( ( new EventSchedule( '2026-10-07', '15:00' ) )->is_all_day() );
	}

	public function test_is_past_compares_with_today_in_colombia(): void {
		$schedule = new EventSchedule( '2026-10-07', '23:59' );

		$this->assertFalse( $schedule->is_past( '2026-10-07' ), 'Hoy no ha pasado, aunque la hora ya haya pasado.' );
		$this->assertTrue( $schedule->is_past( '2026-10-08' ) );
		$this->assertFalse( ( new EventSchedule( '2026-10-06', null, '2026-10-08' ) )->is_past( '2026-10-08' ), 'D-7: un evento de varios días sigue vigente hasta su último día.' );
	}

	public function test_overlaps_a_range(): void {
		$single = new EventSchedule( '2026-10-07' );
		$multi  = new EventSchedule( '2026-10-05', null, '2026-10-09' );

		$this->assertTrue( $single->overlaps( '2026-10-01', '2026-10-31' ) );
		$this->assertTrue( $single->overlaps( '2026-10-07', '2026-10-07' ) );
		$this->assertFalse( $single->overlaps( '2026-10-08', '2026-10-31' ) );
		$this->assertTrue( $multi->overlaps( '2026-10-08', '2026-10-08' ), 'Aparece en cada día que ocupa.' );
		$this->assertSame( '2026-10-09', $multi->last_date() );
	}

	public function test_reads_the_time_columns_with_seconds(): void {
		$schedule = EventSchedule::from_storage( '2026-10-07', '15:00:00', '', null );

		$this->assertSame( '15:00', $schedule->start_time );
		$this->assertNull( $schedule->end_date );
		$this->assertNull( EventSchedule::from_storage( '2026-10-07', null, null, '' )->start_time );
	}

	/**
	 * Horarios imposibles.
	 *
	 * @return array<string, list<string|null>>
	 */
	public static function invalid_schedules(): array {
		return [
			'fecha con otro formato'       => [ '07/10/2026', null, null, null ],
			'hora con segundos'            => [ '2026-10-07', '15:00:00', null, null ],
			'fin antes del inicio'         => [ '2026-10-07', null, '2026-10-06', null ],
			'hora de fin antes del inicio' => [ '2026-10-07', '15:00', null, '14:00' ],
			'hora de fin sin inicio'       => [ '2026-10-07', null, null, '14:00' ],
		];
	}

	/**
	 * @dataProvider invalid_schedules
	 *
	 * @param string      $start_date Inicio.
	 * @param string|null $start_time Hora de inicio.
	 * @param string|null $end_date   Fin.
	 * @param string|null $end_time   Hora de fin.
	 */
	public function test_rejects_impossible_schedules( string $start_date, ?string $start_time, ?string $end_date, ?string $end_time ): void {
		$this->expectException( InvalidArgumentException::class );

		new EventSchedule( $start_date, $start_time, $end_date, $end_time );
	}

	public function test_an_end_on_the_same_day_after_the_start_is_valid(): void {
		$schedule = new EventSchedule( '2026-10-07', '14:00', '2026-10-07', '16:00' );

		$this->assertSame( '16:00', $schedule->end_time );
	}

	public function test_query_and_page_helpers(): void {
		$query = new EventQuery( [ 'ana' ], 3, null, null, 'title', false, 3, 25 );

		$this->assertSame( 50, $query->offset() );
		$other = $query->with_page( 1, 500 );
		$this->assertSame( [ 1, 500, [ 'ana' ], 3, 'title', false ], [ $other->page, $other->per_page, $other->words, $other->type_id, $other->order_by, $other->ascending ] );

		$this->assertSame( 4, ( new EventPage( [], 76, 25 ) )->total_pages() );
		$this->assertSame( 1, ( new EventPage( [], 0, 25 ) )->total_pages(), 'Sin resultados hay una página vacía.' );
	}
}
