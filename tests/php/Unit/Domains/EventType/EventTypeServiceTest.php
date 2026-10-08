<?php
/**
 * Pruebas del servicio de tipos de evento.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Domains\EventType;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Domains\EventType\Application\EventTypeService;
use Probolsas\Eventos\Shared\Errors\ConflictException;
use Probolsas\Eventos\Shared\Errors\NotFoundException;
use Probolsas\Eventos\Shared\Text\Slugger;
use Probolsas\Eventos\Shared\Text\TextNormalizer;
use Probolsas\Eventos\Shared\Time\Clock;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Ui\IconCatalog;
use Probolsas\Eventos\Shared\Validation\ValidationException;
use Probolsas\Eventos\Tests\Unit\Support\InMemoryEventTypeRepository;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * Reglas de negocio de los tipos de evento (D-2, docs/api/event-types.md).
 *
 * @covers \Probolsas\Eventos\Domains\EventType\Application\EventTypeService
 * @covers \Probolsas\Eventos\Domains\EventType\Domain\EventType
 * @covers \Probolsas\Eventos\Domains\EventType\Domain\EventTypeData
 * @covers \Probolsas\Eventos\Domains\EventType\Domain\EventTypeSummary
 */
final class EventTypeServiceTest extends UnitTestCase {

	/**
	 * Repositorio en memoria.
	 *
	 * @var InMemoryEventTypeRepository
	 */
	private InMemoryEventTypeRepository $repository;

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		$this->repository = new InMemoryEventTypeRepository();
	}

	public function test_creates_a_type_with_normalized_data(): void {
		$summary = $this->service()->create( $this->input( [ 'color' => '#9d174d' ] ) );
		$type    = $summary->type;

		$this->assertSame( 1, $type->id );
		$this->assertSame( 'Cumpleaños', $type->name );
		$this->assertSame( 'cumpleaños', $type->name_key );
		$this->assertSame( 'cumpleanos', $type->slug );
		$this->assertSame( '#9D174D', $type->color, 'El color se guarda en mayúsculas.' );
		$this->assertSame( 'cake-candles', $type->icon );
		$this->assertTrue( $type->requires_attachment );
		$this->assertSame( 1, $type->sort_order, 'Sin orden recibido, va al final.' );
		$this->assertSame( '2026-10-08 01:30:00', $type->created_at_gmt );
		$this->assertSame( 0, $summary->events_count );
	}

	public function test_requires_attachment_defaults_to_no_and_accepts_common_boolean_forms(): void {
		$service = $this->service();

		$this->assertFalse(
			$service->create(
				$this->input(
					[
						'name'                => 'Uno',
						'requires_attachment' => '',
					]
				)
			)->type->requires_attachment
		);
		$this->assertFalse(
			$service->create(
				$this->input(
					[
						'name'                => 'Dos',
						'requires_attachment' => 'false',
					]
				)
			)->type->requires_attachment
		);
		$this->assertTrue(
			$service->create(
				$this->input(
					[
						'name'                => 'Tres',
						'requires_attachment' => 'true',
					]
				)
			)->type->requires_attachment
		);
		$this->assertFalse(
			$service->create(
				$this->input(
					[
						'name'                => 'Cuatro',
						'requires_attachment' => '0',
					]
				)
			)->type->requires_attachment
		);
	}

	/**
	 * @return array<string, array{array<string, mixed>, string, string}>
	 */
	public static function invalid_inputs(): array {
		return [
			'sin nombre'           => [ [ 'name' => '  ' ], 'name', 'El campo «Nombre» es obligatorio.' ],
			'nombre largo'         => [ [ 'name' => str_repeat( 'a', 101 ) ], 'name', 'El campo «Nombre» admite máximo 100 caracteres.' ],
			'sin color'            => [ [ 'color' => '' ], 'color', 'El campo «Color» es obligatorio.' ],
			'color no hexadecimal' => [ [ 'color' => 'rojo' ], 'color', 'El campo «Color» debe ser un color hexadecimal, por ejemplo #155728.' ],
			'color corto'          => [ [ 'color' => '#FFF' ], 'color', 'El campo «Color» debe ser un color hexadecimal, por ejemplo #155728.' ],
			'sin ícono'            => [ [ 'icon' => '' ], 'icon', 'El campo «Ícono» es obligatorio.' ],
			'ícono no permitido'   => [ [ 'icon' => 'skull' ], 'icon', 'Selecciona una opción válida en el campo «Ícono».' ],
			'adjunto no booleano'  => [ [ 'requires_attachment' => 'quizás' ], 'requires_attachment', 'Selecciona una opción válida en el campo «Requiere adjunto».' ],
			'descripción larga'    => [ [ 'description' => str_repeat( 'a', 501 ) ], 'description', 'El campo «Descripción» admite máximo 500 caracteres.' ],
			'orden negativo'       => [ [ 'sort_order' => '-1' ], 'sort_order', 'El campo «Orden» debe ser un número entre 0 y 9999.' ],
		];
	}

	/**
	 * @dataProvider invalid_inputs
	 *
	 * @param array<string, mixed> $changes Cambios sobre una entrada válida.
	 * @param string               $field   Campo con error.
	 * @param string               $message Mensaje esperado.
	 */
	public function test_rejects_invalid_input( array $changes, string $field, string $message ): void {
		try {
			$this->service()->create( $this->input( $changes ) );
			$this->fail( 'Se aceptó una entrada no válida.' );
		} catch ( ValidationException $error ) {
			$this->assertSame( [ $field => [ $message ] ], $error->errors() );
			$this->assertTrue( $this->repository->is_empty() );
		}
	}

	public function test_names_are_unique_ignoring_case_and_accents(): void {
		$service = $this->service();
		$service->create( $this->input() );
		$service->create( $this->input( [ 'name' => 'Capacitación' ] ) );

		foreach ( [ 'cumpleaños', 'CUMPLEAÑOS', '  Cumpleaños  ', 'CAPACITACION', 'capacitación' ] as $name ) {
			try {
				$service->create( $this->input( [ 'name' => $name ] ) );
				$this->fail( "Se aceptó un nombre repetido: {$name}" );
			} catch ( ValidationException $error ) {
				$this->assertStringStartsWith( 'Ya existe un tipo de evento llamado', $error->errors()['name'][0] );
			}
		}
	}

	public function test_the_letter_n_with_tilde_is_its_own_letter(): void {
		$service = $this->service();
		$service->create( $this->input() );

		// Como en el resto de la intranet (TextNormalizer): «año» ≠ «ano». Los slugs sí la convierten.
		$this->assertSame( 'cumpleanos-2', $service->create( $this->input( [ 'name' => 'Cumpleanos' ] ) )->type->slug );
	}

	public function test_update_keeps_slug_creation_date_and_position(): void {
		$service = $this->service();
		$created = $service->create( $this->input( [ 'sort_order' => '3' ] ) )->type;

		$updated = $service->update(
			(int) $created->id,
			$this->input(
				[
					'name'       => 'Cumpleaños del mes',
					'sort_order' => '',
				]
			)
		)->type;

		$this->assertSame( 'Cumpleaños del mes', $updated->name );
		$this->assertSame( 'cumpleanos', $updated->slug, 'El slug no cambia al renombrar: lo usan los shortcodes.' );
		$this->assertSame( 3, $updated->sort_order );
		$this->assertSame( $created->created_at_gmt, $updated->created_at_gmt );
	}

	public function test_update_allows_keeping_its_own_name(): void {
		$service = $this->service();
		$created = $service->create( $this->input() )->type;

		$this->assertSame( 'Cumpleaños', $service->update( (int) $created->id, $this->input( [ 'color' => '#155728' ] ) )->type->name );
	}

	public function test_slugs_stay_unique_when_names_differ_only_in_symbols(): void {
		$service = $this->service();

		$this->assertSame( 'reuniones', $service->create( $this->input( [ 'name' => 'Reuniones' ] ) )->type->slug );
		$this->assertSame( 'reuniones-2', $service->create( $this->input( [ 'name' => 'Reuniones!' ] ) )->type->slug );
	}

	public function test_get_and_update_of_a_missing_type_return_not_found(): void {
		$this->expectException( NotFoundException::class );
		$this->expectExceptionMessage( 'El tipo de evento no existe o fue eliminado.' );

		$this->service()->get( 99 );
	}

	public function test_types_with_events_cannot_be_deleted(): void {
		$service                  = $this->service();
		$id                       = (int) $service->create( $this->input() )->type->id;
		$this->repository->events = [ $id => 3 ];

		try {
			$service->delete( $id );
			$this->fail( 'Se eliminó un tipo con eventos.' );
		} catch ( ConflictException $error ) {
			$this->assertSame( 'No se puede eliminar «Cumpleaños» porque tiene 3 eventos asociados.', $error->getMessage() );
			$this->assertNotNull( $this->repository->find( $id ) );
		}
	}

	public function test_types_without_events_are_deleted(): void {
		$service = $this->service();
		$id      = (int) $service->create( $this->input() )->type->id;

		$service->delete( $id );

		$this->assertNull( $this->repository->find( $id ) );
	}

	public function test_reorder_saves_consecutive_positions(): void {
		$service = $this->service();
		$first   = (int) $service->create( $this->input( [ 'name' => 'A' ] ) )->type->id;
		$second  = (int) $service->create( $this->input( [ 'name' => 'B' ] ) )->type->id;

		$ordered = $service->reorder( [ $second, $first ] );

		$this->assertSame( [ 'B', 'A' ], array_map( static fn( $summary ): string => $summary->type->name, $ordered ) );
	}

	public function test_reorder_rejects_lists_that_do_not_include_every_type(): void {
		$service = $this->service();
		$id      = (int) $service->create( $this->input() )->type->id;
		$service->create( $this->input( [ 'name' => 'Otro' ] ) );

		$this->expectException( ValidationException::class );

		$service->reorder( [ $id ] );
	}

	public function test_simultaneous_duplicate_writes_become_a_general_validation_error(): void {
		$this->repository->fail_next_write_as_duplicate = true;

		try {
			$this->service()->create( $this->input() );
			$this->fail( 'No se informó la escritura simultánea.' );
		} catch ( ValidationException $error ) {
			$this->assertStringStartsWith( 'Otro usuario acaba de guardar', $error->getMessage() );
		}
	}

	public function test_client_rules_come_from_the_same_limits_as_the_validation(): void {
		$rules = EventTypeService::client_rules();

		$this->assertSame( EventTypeService::NAME_MAX_LENGTH, $rules['name']['maxLength'] );
		$this->assertSame( EventTypeService::DESCRIPTION_MAX_LENGTH, $rules['description']['maxLength'] );
		$this->assertSame( 1, preg_match( '/' . $rules['color']['pattern'] . '/', '#155728' ) );
		$this->assertSame( 0, preg_match( '/' . $rules['color']['pattern'] . '/', '#FFF' ) );
		$this->assertSame( 'icons', $rules['icon']['oneOf'] );
	}

	/**
	 * Entrada válida con cambios.
	 *
	 * @param array<string, mixed> $changes Cambios.
	 *
	 * @return array<string, mixed>
	 */
	private function input( array $changes = [] ): array {
		return array_merge(
			[
				'name'                => 'Cumpleaños',
				'color'               => '#9D174D',
				'icon'                => 'cake-candles',
				'requires_attachment' => '1',
				'description'         => '',
				'sort_order'          => '',
			],
			$changes
		);
	}

	/**
	 * Servicio con reloj fijo (8:30 p. m. del 7 de octubre en Bogotá).
	 */
	private function service(): EventTypeService {
		$config = new Config(
			[
				'ui'    => [ 'timezone' => 'America/Bogota' ],
				'icons' => [
					'cake-candles' => [ 'Pastel', '' ],
					'star'         => [ 'Estrella', '' ],
				],
			]
		);
		$clock  = new class() implements Clock {
			public function now(): DateTimeImmutable {
				return new DateTimeImmutable( '2026-10-08 01:30:00', new DateTimeZone( 'UTC' ) );
			}
		};

		return new EventTypeService(
			$this->repository,
			new TextNormalizer(),
			new Slugger( new TextNormalizer() ),
			DateFormatter::from_config( $config, $clock ),
			new IconCatalog( $config )
		);
	}
}
