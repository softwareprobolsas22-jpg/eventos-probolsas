<?php
/**
 * Pruebas del migrador de esquema.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Core\Database;

use Brain\Monkey\Functions;
use LogicException;
use Mockery;
use Probolsas\Eventos\Core\Database\Migration;
use Probolsas\Eventos\Core\Database\Migrator;
use Probolsas\Eventos\Core\Database\Tables;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * @covers \Probolsas\Eventos\Core\Database\Migrator
 */
final class MigratorTest extends UnitTestCase {

	/**
	 * Versiones aplicadas, en orden, por las migraciones falsas.
	 *
	 * @var list<int>
	 */
	private array $applied = [];

	public function test_migrate_applies_pending_migrations_in_version_order(): void {
		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\expect( 'update_option' )->once()->ordered()->with( Migrator::OPTION, 1, true );
		Functions\expect( 'update_option' )->once()->ordered()->with( Migrator::OPTION, 2, true );

		$migrator = new Migrator( [ $this->migration( 2 ), $this->migration( 1 ) ], $this->tables() );
		$migrator->migrate();

		$this->assertSame( [ 1, 2 ], $this->applied );
	}

	public function test_migrate_skips_already_applied_migrations(): void {
		Functions\when( 'get_option' )->justReturn( '1' );
		Functions\expect( 'update_option' )->once()->with( Migrator::OPTION, 2, true );

		$migrator = new Migrator( [ $this->migration( 1 ), $this->migration( 2 ) ], $this->tables() );
		$migrator->migrate();

		$this->assertSame( [ 2 ], $this->applied );
	}

	public function test_needs_migration_compares_stored_and_latest_versions(): void {
		Functions\when( 'get_option' )->justReturn( 1 );

		$up_to_date = new Migrator( [ $this->migration( 1 ) ], $this->tables() );
		$outdated   = new Migrator( [ $this->migration( 1 ), $this->migration( 2 ) ], $this->tables() );

		$this->assertFalse( $up_to_date->needs_migration() );
		$this->assertTrue( $outdated->needs_migration() );
		$this->assertSame( 2, $outdated->latest_version() );
	}

	public function test_latest_version_is_zero_without_migrations(): void {
		$this->assertSame( 0, ( new Migrator( [], $this->tables() ) )->latest_version() );
	}

	public function test_constructor_rejects_duplicated_versions(): void {
		$this->expectException( LogicException::class );

		new Migrator( [ $this->migration( 1 ), $this->migration( 1 ) ], $this->tables() );
	}

	public function test_reset_drops_every_table_and_the_version_option(): void {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		// El nombre de la tabla pasa por prepare() con %i, como cualquier identificador.
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing( static fn( string $query, string $table ): string => str_replace( '%i', "`{$table}`", $query ) );
		$wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'DROP TABLE IF EXISTS `wp_eventos_events`' );
		$wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'DROP TABLE IF EXISTS `wp_eventos_event_types`' );
		Functions\expect( 'delete_option' )->once()->with( Migrator::OPTION );

		( new Migrator( [], new Tables( $wpdb ) ) )->reset();
	}

	/**
	 * Migración falsa que registra su ejecución.
	 *
	 * @param int $version Versión de la migración.
	 */
	private function migration( int $version ): Migration {
		$applied = &$this->applied;

		return new class( $version, $applied ) implements Migration {

			/**
			 * Lista compartida de versiones aplicadas.
			 *
			 * @var list<int>
			 */
			private array $applied;

			/**
			 * @param int       $version Versión.
			 * @param list<int> $applied Lista compartida de versiones aplicadas.
			 */
			public function __construct( private int $version, array &$applied ) {
				$this->applied = &$applied;
			}

			public function version(): int {
				return $this->version;
			}

			public function up(): void {
				$this->applied[] = $this->version;
			}
		};
	}

	/**
	 * Catálogo de tablas con una conexión simulada.
	 */
	private function tables(): Tables {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		return new Tables( $wpdb );
	}
}
