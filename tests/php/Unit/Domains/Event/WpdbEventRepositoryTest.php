<?php
/**
 * Pruebas del repositorio de eventos sobre $wpdb.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Domains\Event;

use Mockery\MockInterface;
use Probolsas\Eventos\Core\Database\Tables;
use Probolsas\Eventos\Domains\Event\Domain\Event;
use Probolsas\Eventos\Domains\Event\Domain\EventQuery;
use Probolsas\Eventos\Domains\Event\Domain\EventSchedule;
use Probolsas\Eventos\Domains\Event\Infrastructure\WpdbEventRepository;
use Probolsas\Eventos\Tests\Unit\Support\FakeWpdb;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * SQL de la búsqueda (solapamiento de rango, palabras, orden y paginación) y conversión de filas. Contra
 * MySQL real: tests/php/Integration/EventApiTest.php.
 *
 * @covers \Probolsas\Eventos\Domains\Event\Infrastructure\WpdbEventRepository
 */
final class WpdbEventRepositoryTest extends UnitTestCase {

	/**
	 * Conexión simulada.
	 *
	 * @var MockInterface
	 */
	private MockInterface $wpdb;

	protected function set_up(): void {
		parent::set_up();
		$this->wpdb = $this->fake_wpdb();
	}

	/**
	 * Conexión simulada con esc_like() como el de WordPress.
	 */
	private function fake_wpdb(): MockInterface {
		$wpdb = FakeWpdb::create();
		$wpdb->shouldReceive( 'esc_like' )->andReturnUsing( static fn( string $text ): string => addcslashes( $text, '_%\\' ) );

		return $wpdb;
	}

	public function test_search_without_filters_orders_by_date_and_time(): void {
		$sql = $this->capture_search( new EventQuery() );

		$this->assertSame( 'SELECT COUNT(*) FROM `wp_eventos_events` e WHERE 1 = 1', $sql['count'] );
		$this->assertSame(
			'SELECT e.* FROM `wp_eventos_events` e LEFT JOIN `wp_eventos_event_types` t ON t.id = e.type_id WHERE 1 = 1 ORDER BY e.start_date ASC, e.start_time ASC, e.id ASC LIMIT 25 OFFSET 0',
			$sql['rows']
		);
	}

	public function test_search_filters_by_words_type_and_overlapping_range(): void {
		$sql = $this->capture_search( new EventQuery( [ 'ana', '50%' ], 3, '2026-10-01', '2026-10-31', 'type', false, 3, 50 ) );

		$where = "WHERE 1 = 1 AND (e.title LIKE '%ana%' OR e.description LIKE '%ana%') AND (e.title LIKE '%50\\%%' OR e.description LIKE '%50\\%%')"
			. " AND e.type_id = 3 AND e.start_date <= '2026-10-31' AND COALESCE(e.end_date, e.start_date) >= '2026-10-01'";
		$this->assertSame( "SELECT COUNT(*) FROM `wp_eventos_events` e {$where}", $sql['count'] );
		$this->assertStringContainsString( "{$where} ORDER BY t.sort_order DESC, t.name DESC, e.start_date ASC, e.id ASC LIMIT 50 OFFSET 100", $sql['rows'] );
	}

	public function test_every_order_column_has_its_sql(): void {
		$this->assertStringContainsString( 'ORDER BY e.title ASC, e.start_date ASC, e.id ASC', $this->capture_search( new EventQuery( [], null, null, null, 'title' ) )['rows'] );
		$this->assertStringContainsString( 'ORDER BY e.created_at_gmt DESC, e.id DESC', $this->capture_search( new EventQuery( [], null, null, null, 'created_at', false ) )['rows'] );
		$this->assertStringContainsString( 'ORDER BY e.start_date ASC', $this->capture_search( new EventQuery( [], null, null, null, 'otra' ) )['rows'], 'Una columna desconocida no llega al SQL.' );
	}

	public function test_search_hydrates_the_rows_and_the_total(): void {
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '2' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn(
			[
				$this->row(),
				$this->row(
					[
						'id'            => '43',
						'start_time'    => null,
						'attachment_id' => null,
					]
				),
			]
		);

		$page = $this->repository()->search( new EventQuery() );

		$this->assertSame( 2, $page->total );
		$this->assertSame( '15:00', $page->events[0]->schedule->start_time );
		$this->assertSame( 315, $page->events[0]->attachment_id );
		$this->assertTrue( $page->events[1]->schedule->is_all_day() );
		$this->assertNull( $page->events[1]->attachment_id );
	}

	public function test_events_on_a_date_use_the_overlap_condition(): void {
		$sql = null;
		$this->wpdb->shouldReceive( 'get_results' )->andReturnUsing(
			static function ( string $query ) use ( &$sql ): array {
				$sql = $query;
				return [];
			}
		);

		$this->assertSame( [], $this->repository()->on_date( '2026-10-07' ) );
		$this->assertSame( "SELECT * FROM `wp_eventos_events` WHERE start_date <= '2026-10-07' AND COALESCE(end_date, start_date) >= '2026-10-07' ORDER BY start_time ASC, id ASC", $sql );
	}

	public function test_events_in_a_range_filter_by_overlap_types_and_limit(): void {
		$sql = [];
		$this->wpdb->shouldReceive( 'get_results' )->andReturnUsing(
			static function ( string $query ) use ( &$sql ): array {
				$sql[] = $query;
				return [];
			}
		);

		$repository = $this->repository();
		$repository->in_range( '2026-09-28', '2026-11-08' );
		$repository->in_range( '2026-10-07', '9999-12-31', [ 1, 3 ], 5 );

		$this->assertSame(
			[
				"SELECT * FROM `wp_eventos_events` WHERE start_date <= '2026-11-08' AND COALESCE(end_date, start_date) >= '2026-09-28' ORDER BY start_date ASC, start_time ASC, id ASC",
				"SELECT * FROM `wp_eventos_events` WHERE start_date <= '9999-12-31' AND COALESCE(end_date, start_date) >= '2026-10-07' AND type_id IN (1, 3) ORDER BY start_date ASC, start_time ASC, id ASC LIMIT 5",
			],
			$sql
		);
	}

	public function test_find_hydrates_a_row(): void {
		$this->wpdb->shouldReceive( 'get_row' )->andReturn( $this->row(), null );

		$event = $this->repository()->find( 42 );

		$this->assertNotNull( $event );
		$this->assertSame( [ 42, 1, 'Cumpleaños de Ana', '2026-10-07', 3, 4 ], [ $event->id, $event->type_id, $event->title, $event->schedule->start_date, $event->created_by, $event->updated_by ] );
		$this->assertNull( $this->repository()->find( 99 ) );
	}

	public function test_writes_times_with_seconds_and_nulls_for_empty_values(): void {
		$written = [];
		$this->wpdb->shouldReceive( 'insert' )->andReturnUsing(
			function ( string $table, array $data ) use ( &$written ): int {
				$written[]             = $data;
				$this->wpdb->insert_id = 42;
				return 1;
			}
		);
		$this->wpdb->shouldReceive( 'update' )->andReturnUsing(
			static function ( string $table, array $data, array $where ) use ( &$written ): int {
				$written[] = $data + $where;
				return 1;
			}
		);
		$this->wpdb->shouldReceive( 'delete' )->once()->with( 'wp_eventos_events', [ 'id' => 42 ] )->andReturn( 1 );

		$repository = $this->repository();
		$saved      = $repository->insert( $this->event( '15:00' ) );
		$repository->update( $this->event( null )->with_id( 42 ) );
		$repository->delete( 42 );

		$this->assertSame( 42, $saved->id );
		$this->assertSame( '15:00:00', $written[0]['start_time'] );
		$this->assertNull( $written[0]['end_date'] );
		$this->assertNull( $written[1]['start_time'], 'Todo el día.' );
		$this->assertNull( $written[1]['attachment_id'] );
		$this->assertSame( 42, $written[1]['id'] );
	}

	/**
	 * Ejecuta una búsqueda y devuelve el SQL del conteo y de las filas.
	 *
	 * @param EventQuery $query Búsqueda.
	 *
	 * @return array{count: string, rows: string}
	 */
	private function capture_search( EventQuery $query ): array {
		$sql = [
			'count' => '',
			'rows'  => '',
		];
		// Conexión nueva por búsqueda: Mockery usaría la primera expectativa en todas.
		$this->wpdb = $this->fake_wpdb();
		$this->wpdb->shouldReceive( 'get_var' )->andReturnUsing(
			static function ( string $query ) use ( &$sql ): string {
				$sql['count'] = $query;
				return '0';
			}
		);
		$this->wpdb->shouldReceive( 'get_results' )->andReturnUsing(
			static function ( string $query ) use ( &$sql ): array {
				$sql['rows'] = $query;
				return [];
			}
		);

		$this->repository()->search( $query );

		return $sql;
	}

	/**
	 * Fila de la tabla.
	 *
	 * @param array<string, mixed> $changes Cambios.
	 *
	 * @return array<string, mixed>
	 */
	private function row( array $changes = [] ): array {
		return [
			'id'             => '42',
			'type_id'        => '1',
			'title'          => 'Cumpleaños de Ana',
			'description'    => null,
			'start_date'     => '2026-10-07',
			'start_time'     => '15:00:00',
			'end_date'       => null,
			'end_time'       => null,
			'attachment_id'  => '315',
			'created_by'     => '3',
			'updated_by'     => '4',
			'created_at_gmt' => '2026-10-01 13:30:00',
			'updated_at_gmt' => '2026-10-01 13:30:00',
			...$changes,
		];
	}

	/**
	 * Evento sin guardar.
	 *
	 * @param string|null $time Hora.
	 */
	private function event( ?string $time ): Event {
		return new Event( null, 1, 'Cumpleaños de Ana', '', new EventSchedule( '2026-10-07', $time ), null === $time ? null : 315, 3, 3, '2026-10-01 13:30:00', '2026-10-01 13:30:00' );
	}

	/**
	 * Repositorio con la conexión simulada.
	 */
	private function repository(): WpdbEventRepository {
		return new WpdbEventRepository( $this->wpdb, new Tables( $this->wpdb ) );
	}
}
