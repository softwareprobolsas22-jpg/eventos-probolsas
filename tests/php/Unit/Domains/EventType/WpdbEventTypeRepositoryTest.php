<?php
/**
 * Pruebas unitarias del repositorio de tipos de evento sobre $wpdb.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Domains\EventType;

use Brain\Monkey\Functions;
use Mockery;
use Mockery\MockInterface;
use Probolsas\Eventos\Core\Database\Tables;
use Probolsas\Eventos\Domains\EventType\Domain\EventType;
use Probolsas\Eventos\Domains\EventType\Infrastructure\WpdbEventTypeRepository;
use Probolsas\Eventos\Tests\Unit\Support\FakeWpdb;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * Consultas, conversión fila ↔ entidad y conteo de eventos. El comportamiento contra MySQL real está en
 * tests/php/Integration/EventTypeApiTest.php.
 *
 * @covers \Probolsas\Eventos\Domains\EventType\Infrastructure\WpdbEventTypeRepository
 */
final class WpdbEventTypeRepositoryTest extends UnitTestCase {

	/**
	 * Conexión simulada.
	 *
	 * @var MockInterface
	 */
	private MockInterface $wpdb;

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		$this->wpdb = FakeWpdb::create();
	}

	public function test_find_hydrates_the_row_into_an_entity(): void {
		$this->wpdb->shouldReceive( 'get_row' )->once()->andReturn( $this->row() );

		$type = $this->repository()->find( 1 );

		$this->assertInstanceOf( EventType::class, $type );
		$this->assertSame( 1, $type->id );
		$this->assertSame( 'Cumpleaños', $type->name );
		$this->assertTrue( $type->requires_attachment );
		$this->assertSame( 2, $type->sort_order );
	}

	public function test_find_returns_null_when_missing(): void {
		$this->wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );

		$this->assertNull( $this->repository()->find( 9 ) );
	}

	public function test_list_counts_events_and_orders_by_position_then_name(): void {
		$this->wpdb->shouldReceive( 'get_results' )->once()
			->with(
				Mockery::on(
					static fn( string $sql ): bool => str_contains( $sql, 'FROM `wp_eventos_events` e WHERE e.type_id = t.id' )
						&& str_contains( $sql, 'FROM `wp_eventos_event_types` t' )
						&& str_contains( $sql, 'ORDER BY t.sort_order ASC, t.name ASC' )
				),
				ARRAY_A
			)
			->andReturn( [ $this->row( [ 'events_count' => '4' ] ) ] );

		$summaries = $this->repository()->all_with_event_counts();

		$this->assertCount( 1, $summaries );
		$this->assertSame( 4, $summaries[0]->events_count );
	}

	public function test_list_tolerates_a_failed_query(): void {
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( null );

		$this->assertSame( [], $this->repository()->all_with_event_counts() );
	}

	public function test_lookups_use_the_unique_columns(): void {
		$this->wpdb->shouldReceive( 'get_var' )->once()->with( 'SELECT 1 FROM `wp_eventos_event_types` LIMIT 1' )->andReturn( null );
		$this->wpdb->shouldReceive( 'get_var' )->once()->with( "SELECT 1 FROM `wp_eventos_event_types` WHERE `name_key` = 'cumpleaños' AND id <> 2 LIMIT 1" )->andReturn( '1' );
		$this->wpdb->shouldReceive( 'get_var' )->once()->with( "SELECT 1 FROM `wp_eventos_event_types` WHERE `slug` = 'cumpleanos' AND id <> 0 LIMIT 1" )->andReturn( null );
		$this->wpdb->shouldReceive( 'get_var' )->once()->with( 'SELECT COUNT(*) FROM `wp_eventos_events` WHERE type_id = 2' )->andReturn( '3' );

		$repository = $this->repository();

		$this->assertTrue( $repository->is_empty() );
		$this->assertTrue( $repository->name_key_exists( 'cumpleaños', 2 ) );
		$this->assertFalse( $repository->slug_exists( 'cumpleanos' ) );
		$this->assertSame( 3, $repository->count_events( 2 ) );
	}

	public function test_insert_update_and_delete_write_every_column(): void {
		$expected = [
			'name'                => 'Cumpleaños',
			'name_key'            => 'cumpleaños',
			'slug'                => 'cumpleanos',
			'color'               => '#9D174D',
			'icon'                => 'cake-candles',
			'requires_attachment' => 1,
			'description'         => '',
			'sort_order'          => 2,
			'created_at_gmt'      => '2026-10-08 01:30:00',
			'updated_at_gmt'      => '2026-10-08 01:30:00',
		];
		$this->wpdb->shouldReceive( 'insert' )->once()->with( 'wp_eventos_event_types', $expected )->andReturnUsing(
			function (): int {
				$this->wpdb->insert_id = 5;
				return 1;
			}
		);
		$this->wpdb->shouldReceive( 'update' )->once()->with( 'wp_eventos_event_types', array_merge( $expected, [ 'requires_attachment' => 0 ] ), [ 'id' => 5 ] )->andReturn( 1 );
		$this->wpdb->shouldReceive( 'delete' )->once()->with( 'wp_eventos_event_types', [ 'id' => 5 ] )->andReturn( 1 );

		$repository = $this->repository();
		$saved      = $repository->insert( $this->entity( null, true ) );
		$repository->update( $this->entity( 5, false ) );
		$repository->delete( 5 );

		$this->assertSame( 5, $saved->id );
	}

	public function test_positions(): void {
		$this->wpdb->shouldReceive( 'get_var' )->once()->andReturn( '3' );
		$this->wpdb->shouldReceive( 'query' )->once()->with( 'UPDATE `wp_eventos_event_types` SET sort_order = CASE id WHEN 2 THEN 1 END WHERE id IN (2)' )->andReturn( 1 );

		$repository = $this->repository();

		$this->assertSame( 3, $repository->next_sort_order() );
		$repository->reorder( [ 2 => 1 ] );
	}

	/**
	 * Repositorio sobre la conexión simulada.
	 */
	private function repository(): WpdbEventTypeRepository {
		return new WpdbEventTypeRepository( $this->wpdb, new Tables( $this->wpdb ) );
	}

	/**
	 * Fila de la base de datos.
	 *
	 * @param array<string, string> $changes Cambios.
	 *
	 * @return array<string, string>
	 */
	private function row( array $changes = [] ): array {
		return array_merge(
			[
				'id'                  => '1',
				'name'                => 'Cumpleaños',
				'name_key'            => 'cumpleaños',
				'slug'                => 'cumpleanos',
				'color'               => '#9D174D',
				'icon'                => 'cake-candles',
				'requires_attachment' => '1',
				'description'         => '',
				'sort_order'          => '2',
				'created_at_gmt'      => '2026-10-08 01:30:00',
				'updated_at_gmt'      => '2026-10-08 01:30:00',
			],
			$changes
		);
	}

	/**
	 * Entidad de prueba.
	 *
	 * @param int|null $id                  ID.
	 * @param bool     $requires_attachment Si requiere adjunto.
	 */
	private function entity( ?int $id, bool $requires_attachment ): EventType {
		return new EventType( $id, 'Cumpleaños', 'cumpleaños', 'cumpleanos', '#9D174D', 'cake-candles', $requires_attachment, '', 2, '2026-10-08 01:30:00', '2026-10-08 01:30:00' );
	}
}
