<?php
/**
 * Pruebas del esquema base y de los tipos iniciales.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Domains\EventType;

use Brain\Monkey\Functions;
use Mockery;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Core\Database\Migrations\CreateBaseSchema;
use Probolsas\Eventos\Core\Database\Tables;
use Probolsas\Eventos\Domains\EventType\Application\EventTypeService;
use Probolsas\Eventos\Domains\EventType\Infrastructure\SeedDefaultEventTypes;
use Probolsas\Eventos\Shared\Text\Slugger;
use Probolsas\Eventos\Shared\Text\TextNormalizer;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Time\SystemClock;
use Probolsas\Eventos\Shared\Ui\ColorContrast;
use Probolsas\Eventos\Shared\Ui\IconCatalog;
use Probolsas\Eventos\Tests\Unit\Support\InMemoryEventTypeRepository;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * @covers \Probolsas\Eventos\Core\Database\Migrations\CreateBaseSchema
 * @covers \Probolsas\Eventos\Domains\EventType\Infrastructure\SeedDefaultEventTypes
 */
final class SeedAndSchemaTest extends UnitTestCase {

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
	}

	public function test_schema_creates_both_tables_with_the_agreed_columns(): void {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'get_charset_collate' )->andReturn( 'DEFAULT CHARSET=utf8mb4' );

		$migration          = new CreateBaseSchema( new Tables( $wpdb ) );
		[ $types, $events ] = $migration->statements();

		$this->assertSame( 1, $migration->version() );
		$this->assertStringStartsWith( "CREATE TABLE wp_eventos_event_types (\n", $types );
		$this->assertStringContainsString( 'UNIQUE KEY name_key (name_key)', $types );
		$this->assertStringContainsString( 'requires_attachment tinyint(1) unsigned NOT NULL DEFAULT 0', $types );
		$this->assertStringStartsWith( "CREATE TABLE wp_eventos_events (\n", $events );
		// D-7 (v1.1): fin reservado desde v1 e índice para la consulta por solapamiento de rango.
		$this->assertStringContainsString( 'end_date date NULL', $events );
		$this->assertStringContainsString( 'end_time time NULL', $events );
		$this->assertStringContainsString( 'KEY start_end (start_date,end_date)', $events );
		$this->assertStringEndsWith( ') DEFAULT CHARSET=utf8mb4;', $events );
	}

	public function test_seed_creates_the_four_legacy_types_once(): void {
		$repository = new InMemoryEventTypeRepository();
		$seed       = new SeedDefaultEventTypes( $repository, $this->service( $repository ) );

		$seed->up();
		$seed->up();

		$summaries = $repository->all_with_event_counts();
		$this->assertSame( 2, $seed->version() );
		$this->assertSame( [ 'Cumpleaños', 'Capacitaciones', 'Reuniones especiales', 'Reuniones laborales' ], array_map( static fn( $s ): string => $s->type->name, $summaries ) );
		$this->assertSame( [ true, true, true, false ], array_map( static fn( $s ): bool => $s->type->requires_attachment, $summaries ) );
		$this->assertSame( [ 1, 2, 3, 4 ], array_map( static fn( $s ): int => $s->type->sort_order, $summaries ) );
	}

	public function test_seed_respects_existing_types(): void {
		$repository = new InMemoryEventTypeRepository();
		$service    = $this->service( $repository );
		$service->create(
			[
				'name'  => 'Propio',
				'color' => '#155728',
				'icon'  => 'star',
			]
		);

		( new SeedDefaultEventTypes( $repository, $service ) )->up();

		$this->assertCount( 1, $repository->types );
	}

	public function test_initial_colors_have_white_text_with_aa_contrast(): void {
		$contrast = new ColorContrast();

		foreach ( SeedDefaultEventTypes::DEFAULTS as [ $name, $color ] ) {
			$this->assertSame( 'light', $contrast->readable_tone( $color ), $name );
			$this->assertGreaterThanOrEqual( ColorContrast::AA_TEXT, $contrast->ratio( $color, '#FFFFFF' ), $name );
		}
	}

	/**
	 * Servicio con los íconos de la configuración real.
	 *
	 * @param InMemoryEventTypeRepository $repository Repositorio.
	 */
	private function service( InMemoryEventTypeRepository $repository ): EventTypeService {
		$config = Config::from_directory( $this->plugin_dir() . 'config' );

		return new EventTypeService(
			$repository,
			new TextNormalizer(),
			new Slugger( new TextNormalizer() ),
			DateFormatter::from_config( $config, new SystemClock() ),
			new IconCatalog( $config )
		);
	}
}
