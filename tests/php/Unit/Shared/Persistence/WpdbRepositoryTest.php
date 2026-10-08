<?php
/**
 * Pruebas unitarias de la base de los repositorios.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Shared\Persistence;

use Brain\Monkey\Functions;
use Mockery;
use Mockery\MockInterface;
use Probolsas\Eventos\Shared\Persistence\DuplicateEntryException;
use Probolsas\Eventos\Shared\Persistence\PersistenceException;
use Probolsas\Eventos\Tests\Unit\Support\FakeWpdb;
use Probolsas\Eventos\Tests\Unit\Support\FakeWpdbRepository;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * Consultas preparadas (sin interpolar valores), conversión de errores de MySQL y orden manual.
 * El comportamiento contra MySQL real se prueba en las pruebas de integración de cada dominio.
 *
 * @covers \Probolsas\Eventos\Shared\Persistence\WpdbRepository
 */
final class WpdbRepositoryTest extends UnitTestCase {

	private const TABLE = 'wp_eventos_event_types';

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

	public function test_find_returns_the_row_or_null(): void {
		$this->wpdb->shouldReceive( 'get_row' )->once()->with( 'SELECT * FROM `wp_eventos_event_types` WHERE id = 7', ARRAY_A )->andReturn( [ 'id' => '7' ] );
		$this->wpdb->shouldReceive( 'get_row' )->once()->with( 'SELECT * FROM `wp_eventos_event_types` WHERE id = 8', ARRAY_A )->andReturn( null );

		$this->assertSame( [ 'id' => '7' ], $this->repository()->find( 7 ) );
		$this->assertNull( $this->repository()->find( 8 ) );
	}

	public function test_exists_excludes_the_record_being_edited(): void {
		$this->wpdb->shouldReceive( 'get_var' )->once()
			->with( "SELECT 1 FROM `wp_eventos_event_types` WHERE `name_key` = 'cumpleanos' AND id <> 0 LIMIT 1" )
			->andReturn( '1' );
		$this->wpdb->shouldReceive( 'get_var' )->once()
			->with( "SELECT 1 FROM `wp_eventos_event_types` WHERE `name_key` = 'cumpleanos' AND id <> 3 LIMIT 1" )
			->andReturn( null );

		$this->assertTrue( $this->repository()->exists( 'name_key', 'cumpleanos' ) );
		$this->assertFalse( $this->repository()->exists( 'name_key', 'cumpleanos', 3 ) );
	}

	public function test_insert_returns_the_new_id(): void {
		$this->wpdb->shouldReceive( 'insert' )->once()->with( self::TABLE, [ 'name' => 'Cumpleaños' ] )->andReturnUsing(
			function (): int {
				$this->wpdb->insert_id = 12;
				return 1;
			}
		);

		$this->assertSame( 12, $this->repository()->insert( [ 'name' => 'Cumpleaños' ] ) );
	}

	public function test_duplicate_entries_become_a_specific_exception(): void {
		$this->wpdb->shouldReceive( 'insert' )->once()->andReturnUsing(
			function (): bool {
				$this->wpdb->last_error = "Duplicate entry 'cumpleanos' for key 'name_key'";
				return false;
			}
		);

		try {
			$this->repository()->insert( [ 'name' => 'Cumpleaños' ] );
			$this->fail( 'No se lanzó la excepción.' );
		} catch ( DuplicateEntryException $error ) {
			$this->assertSame( 'Ya existe un registro con esos datos.', $error->getMessage() );
			$this->assertStringContainsString( 'Duplicate entry', $error->technical_detail() );
		}
	}

	public function test_other_database_errors_keep_the_detail_out_of_the_user_message(): void {
		$this->wpdb->shouldReceive( 'update' )->once()->with( self::TABLE, [ 'name' => 'X' ], [ 'id' => 4 ] )->andReturnUsing(
			function (): bool {
				$this->wpdb->last_error = "Unknown column 'nombre'";
				return false;
			}
		);

		try {
			$this->repository()->update( 4, [ 'name' => 'X' ] );
			$this->fail( 'No se lanzó la excepción.' );
		} catch ( PersistenceException $error ) {
			$this->assertNotInstanceOf( DuplicateEntryException::class, $error );
			$this->assertSame( 'No se pudo guardar la información. Inténtalo de nuevo.', $error->getMessage() );
			// El detalle se guarda escapado (exigencia de PHPCS sobre los argumentos de una excepción).
			$this->assertSame( esc_html( "Unknown column 'nombre'" ), $error->technical_detail() );
		}
	}

	public function test_update_and_delete_target_the_given_id(): void {
		$this->wpdb->shouldReceive( 'update' )->once()->with( self::TABLE, [ 'name' => 'Capacitaciones' ], [ 'id' => 2 ] )->andReturn( 1 );
		$this->wpdb->shouldReceive( 'delete' )->once()->with( self::TABLE, [ 'id' => 2 ] )->andReturn( 1 );

		$this->repository()->update( 2, [ 'name' => 'Capacitaciones' ] );
		$this->repository()->delete( 2 );
	}

	public function test_failed_delete_throws(): void {
		$this->wpdb->shouldReceive( 'delete' )->once()->andReturn( false );

		$this->expectException( PersistenceException::class );

		$this->repository()->delete( 2 );
	}

	public function test_next_position_comes_after_the_last_one(): void {
		$this->wpdb->shouldReceive( 'get_var' )->once()->with( 'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM `wp_eventos_event_types`' )->andReturn( '5' );

		$this->assertSame( 5, $this->repository()->next_position() );
	}

	public function test_positions_are_saved_in_a_single_statement(): void {
		$this->wpdb->shouldReceive( 'query' )->once()
			->with( 'UPDATE `wp_eventos_event_types` SET sort_order = CASE id WHEN 3 THEN 1 WHEN 1 THEN 2 END WHERE id IN (3,1)' )
			->andReturn( 2 );

		$this->repository()->reorder(
			[
				3 => 1,
				1 => 2,
			]
		);
	}

	public function test_empty_order_does_not_touch_the_database(): void {
		$this->wpdb->shouldNotReceive( 'query' );

		$this->repository()->reorder( [] );
	}

	public function test_failed_reorder_throws(): void {
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( false );

		$this->expectException( PersistenceException::class );

		$this->repository()->reorder( [ 1 => 1 ] );
	}

	/**
	 * Repositorio sobre la conexión simulada.
	 */
	private function repository(): FakeWpdbRepository {
		return new FakeWpdbRepository( $this->wpdb, self::TABLE );
	}
}
