<?php
/**
 * Pruebas del servicio de eventos.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Domains\Event;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Domains\Event\Application\EventService;
use Probolsas\Eventos\Domains\Event\Domain\Event;
use Probolsas\Eventos\Domains\Event\Domain\EventQuery;
use Probolsas\Eventos\Domains\EventType\Domain\EventType;
use Probolsas\Eventos\Domains\Media\Domain\MediaPolicy;
use Probolsas\Eventos\Shared\Errors\NotFoundException;
use Probolsas\Eventos\Shared\Time\Clock;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Validation\ValidationException;
use Probolsas\Eventos\Tests\Unit\Support\InMemoryAttachmentGateway;
use Probolsas\Eventos\Tests\Unit\Support\InMemoryEventRepository;
use Probolsas\Eventos\Tests\Unit\Support\InMemoryEventTypeRepository;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * Reglas de negocio de los eventos (docs/api/events.md). Reloj fijo: 2026-10-08 01:30 UTC, que en Bogotá
 * todavía es el 7 de octubre a las 8:30 p. m. (el desfase del legado, RL-02).
 *
 * @covers \Probolsas\Eventos\Domains\Event\Application\EventService
 * @covers \Probolsas\Eventos\Domains\Event\Domain\Event
 * @covers \Probolsas\Eventos\Domains\Event\Domain\EventData
 */
final class EventServiceTest extends UnitTestCase {

	private const USER = 7;

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
	 * Biblioteca de Medios en memoria.
	 *
	 * @var InMemoryAttachmentGateway
	 */
	private InMemoryAttachmentGateway $attachments;

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();

		$this->events      = new InMemoryEventRepository();
		$this->types       = new InMemoryEventTypeRepository();
		$this->attachments = new InMemoryAttachmentGateway();

		$this->type( 'Cumpleaños', true );        // ID 1: exige adjunto.
		$this->type( 'Reuniones laborales', false ); // ID 2.
		$this->attachments->add( 315, 'image/jpeg' );
		$this->attachments->add( 316, 'application/pdf' );
		$this->attachments->add( 317, 'application/x-dosexec' );
	}

	public function test_creates_an_event_with_its_author_and_utc_dates(): void {
		$event = $this->service()->create( $this->input(), self::USER );

		$this->assertSame( 1, $event->id );
		$this->assertSame( 'Cumpleaños de Ana María', $event->title );
		$this->assertSame( 1, $event->type_id );
		$this->assertSame( '2026-10-07', $event->schedule->start_date );
		$this->assertSame( '15:00', $event->schedule->start_time );
		$this->assertSame( 315, $event->attachment_id );
		$this->assertSame( [ self::USER, self::USER ], [ $event->created_by, $event->updated_by ] );
		$this->assertSame( '2026-10-08 01:30:00', $event->created_at_gmt );
		$this->assertSame( $event, $this->events->find( 1 ) );
	}

	public function test_without_time_the_event_is_all_day_and_seconds_are_ignored(): void {
		$service = $this->service();

		$this->assertTrue( $service->create( $this->input( [ 'start_time' => '' ] ), self::USER )->schedule->is_all_day() );
		$this->assertSame( '09:30', $service->create( $this->input( [ 'start_time' => '09:30:00' ] ), self::USER )->schedule->start_time );
	}

	public function test_past_dates_are_allowed_and_flagged_with_the_date_of_colombia(): void {
		$service = $this->service();

		$yesterday = $service->create( $this->input( [ 'start_date' => '2026-10-06' ] ), self::USER );
		$today     = $service->create( $this->input( [ 'start_date' => '2026-10-07' ] ), self::USER );

		$this->assertTrue( $service->is_past( $yesterday ), 'D-3: se permite, con aviso.' );
		$this->assertFalse( $service->is_past( $today ), 'En UTC ya es el 8, pero en Colombia sigue siendo el 7 (RL-02).' );
	}

	public function test_reports_every_invalid_field_with_the_contract_messages(): void {
		$errors = $this->errors_for(
			[
				'title'       => 'Ab',
				'type_id'     => '99',
				'start_date'  => '2026-02-30',
				'start_time'  => '25:00',
				'description' => str_repeat( 'á', 2001 ),
			]
		);

		$this->assertSame( [ 'El campo «Título» debe tener al menos 3 caracteres.' ], $errors['title'] );
		$this->assertSame( [ 'Selecciona una opción válida en el campo «Tipo».' ], $errors['type_id'] );
		$this->assertSame( [ 'El campo «Fecha» debe ser una fecha válida.' ], $errors['start_date'] );
		$this->assertSame( [ 'El campo «Hora» debe ser una hora válida.' ], $errors['start_time'] );
		$this->assertSame( [ 'El campo «Descripción» admite máximo 2000 caracteres.' ], $errors['description'] );
	}

	public function test_required_fields(): void {
		$errors = $this->errors_for(
			[
				'title'         => '  ',
				'type_id'       => '',
				'start_date'    => '',
				'attachment_id' => '',
			]
		);

		$this->assertSame( [ 'title', 'type_id', 'start_date' ], array_keys( $errors ), 'Sin tipo válido no se exige el adjunto.' );
		$this->assertSame( [ 'El campo «Título» es obligatorio.' ], $errors['title'] );
		$this->assertSame( [ 'El campo «Tipo» es obligatorio.' ], $errors['type_id'] );
	}

	public function test_title_length_counts_characters_not_bytes(): void {
		$event = $this->service()->create( $this->input( [ 'title' => str_repeat( 'ñ', 150 ) ] ), self::USER );

		$this->assertSame( 150, mb_strlen( $event->title ) );
		$this->assertSame( [ 'El campo «Título» admite máximo 150 caracteres.' ], $this->errors_for( [ 'title' => str_repeat( 'ñ', 151 ) ] )['title'] );
	}

	public function test_the_attachment_is_required_when_the_type_requires_it(): void {
		$this->assertSame( [ 'Este tipo de evento requiere una imagen o un PDF.' ], $this->errors_for( [ 'attachment_id' => '' ] )['attachment_id'] );

		$meeting = $this->service()->create(
			$this->input(
				[
					'type_id'       => '2',
					'attachment_id' => '',
				]
			),
			self::USER
		);
		$this->assertNull( $meeting->attachment_id, 'Las reuniones laborales no lo exigen.' );
	}

	public function test_only_existing_images_or_pdfs_by_their_real_mime(): void {
		$this->assertSame( 316, $this->service()->create( $this->input( [ 'attachment_id' => '316' ] ), self::USER )->attachment_id );
		$this->assertSame( [ 'El archivo debe ser una imagen o un PDF.' ], $this->errors_for( [ 'attachment_id' => '317' ] )['attachment_id'], 'R-09: .exe renombrado.' );
		$this->assertSame( [ 'El archivo elegido ya no existe en la Biblioteca de Medios.' ], $this->errors_for( [ 'attachment_id' => '999' ] )['attachment_id'] );
		$this->assertSame( [ 'El archivo elegido ya no existe en la Biblioteca de Medios.' ], $this->errors_for( [ 'attachment_id' => 'abc' ] )['attachment_id'] );
	}

	public function test_end_date_and_time_are_reserved_for_version_1_1(): void {
		$errors = $this->errors_for(
			[
				'end_date' => '2026-10-08',
				'end_time' => '18:00',
			]
		);

		$this->assertSame( [ 'La hora de fin estará disponible en una próxima versión.' ], $errors['end_date'] );
		$this->assertSame( [ 'La hora de fin estará disponible en una próxima versión.' ], $errors['end_time'] );
	}

	public function test_updates_keep_the_author_and_creation_date(): void {
		$service = $this->service();
		$created = $service->create( $this->input(), self::USER );

		$updated = $service->update( (int) $created->id, $this->input( [ 'title' => 'Cumpleaños de Ana' ] ), 9 );

		$this->assertSame( 'Cumpleaños de Ana', $updated->title );
		$this->assertSame( [ self::USER, 9 ], [ $updated->created_by, $updated->updated_by ] );
		$this->assertSame( $created->created_at_gmt, $updated->created_at_gmt );
		$this->assertSame( $updated, $this->events->find( 1 ) );
	}

	public function test_missing_events_are_not_found(): void {
		$service = $this->service();

		foreach ( [ fn() => $service->get( 5 ), fn() => $service->update( 5, $this->input(), self::USER ), fn() => $service->delete( 5 ) ] as $action ) {
			try {
				$action();
				$this->fail( 'Debió responder 404.' );
			} catch ( NotFoundException $error ) {
				$this->assertSame( 'El evento no existe o fue eliminado.', $error->getMessage() );
			}
		}
	}

	public function test_deleting_keeps_the_attachment_in_the_library(): void {
		$service = $this->service();
		$event   = $service->create( $this->input(), self::USER );

		$service->delete( (int) $event->id );

		$this->assertNull( $this->events->find( (int) $event->id ) );
		$this->assertNotNull( $this->attachments->find( 315 ), 'D-4: el archivo sigue en la biblioteca.' );
	}

	public function test_same_day_lists_the_events_of_that_date_by_time(): void {
		$service = $this->service();
		$late    = $service->create( $this->input( [ 'start_time' => '16:00' ] ), self::USER );
		$all_day = $service->create( $this->input( [ 'start_time' => '' ] ), self::USER );
		$service->create( $this->input( [ 'start_date' => '2026-10-08' ] ), self::USER );

		$this->assertSame( [ $all_day->id, $late->id ], array_map( static fn( Event $event ): ?int => $event->id, $service->same_day( $late ) ) );
	}

	public function test_builds_the_query_with_defaults(): void {
		$query = $this->service()->query( [] );

		$this->assertSame( [], $query->words );
		$this->assertNull( $query->type_id );
		$this->assertSame( [ 'start_date', true, 1, 25 ], [ $query->order_by, $query->ascending, $query->page, $query->per_page ] );
	}

	public function test_builds_the_query_from_the_filters(): void {
		$query = $this->service()->query(
			[
				'page'      => '2',
				'per_page'  => '50',
				'search'    => '  reunión   de  planeación ',
				'type'      => '3',
				'date_from' => '2026-10-01',
				'date_to'   => '2026-10-31',
				'orderby'   => 'title',
				'order'     => 'DESC',
			]
		);

		$this->assertSame( [ 'reunión', 'de', 'planeación' ], $query->words );
		$this->assertSame( [ 3, '2026-10-01', '2026-10-31' ], [ $query->type_id, $query->date_from, $query->date_to ] );
		$this->assertSame( [ 'title', false, 2, 50 ], [ $query->order_by, $query->ascending, $query->page, $query->per_page ] );
	}

	public function test_rejects_invalid_filters(): void {
		try {
			$this->service()->query(
				[
					'page'      => '0',
					'per_page'  => '200',
					'type'      => 'x',
					'date_from' => '2026-10-31',
					'date_to'   => '2026-10-01',
					'orderby'   => 'id',
					'order'     => 'up',
				]
			);
			$this->fail( 'Debió responder 422.' );
		} catch ( ValidationException $error ) {
			$this->assertEqualsCanonicalizing( [ 'page', 'per_page', 'type', 'date_to', 'orderby', 'order' ], array_keys( $error->errors() ) );
			$this->assertSame( [ 'Selecciona una opción válida en el campo «Registros por página».' ], $error->errors()['per_page'], 'R-23: solo 25, 50 o 100.' );
			$this->assertSame( [ 'La fecha «Hasta» no puede ser anterior a la fecha «Desde».' ], $error->errors()['date_to'] );
		}
	}

	public function test_the_page_sizes_come_from_the_configuration(): void {
		$service = $this->service(
			[
				'ui' => [
					'page_sizes'        => [ 10, 20 ],
					'default_page_size' => 99,
				],
			]
		);

		$this->assertSame( 10, $service->query( [] )->per_page, 'Un tamaño por defecto inválido cae en el primero.' );
		$this->assertSame( 20, $service->query( [ 'per_page' => '20' ] )->per_page );
	}

	public function test_search_and_export_use_the_same_filters(): void {
		$service = $this->service();
		foreach ( range( 1, 3 ) as $day ) {
			$service->create( $this->input( [ 'start_date' => "2026-10-0{$day}" ] ), self::USER );
		}
		$service->create( $this->input( [ 'title' => 'Otra cosa' ] ), self::USER );
		$query = $service->query(
			[
				'search'   => 'ANA',
				'per_page' => '25',
			]
		);

		$this->assertSame( 3, $service->search( $query )->total );
		$this->assertCount( 3, iterator_to_array( $service->all_matching( $query ), false ) );
		$this->assertSame( 500, end( $this->events->queries )->per_page, 'La exportación recorre lotes de 500, sin el límite de la tabla.' );
	}

	public function test_publishes_the_validation_rules_for_the_browser(): void {
		$rules = EventService::client_rules();

		$this->assertSame(
			[
				'required'  => true,
				'minLength' => 3,
				'maxLength' => 150,
			],
			$rules['title']
		);
		$this->assertSame( 'event_types', $rules['type_id']['oneOf'] );
		$this->assertSame( 'type.requires_attachment', $rules['attachment_id']['requiredWhen'] );
		$this->assertSame( [ 'maxLength' => 2000 ], $rules['description'] );
	}

	/**
	 * Datos válidos de un evento, con cambios.
	 *
	 * @param array<string, string> $changes Cambios.
	 *
	 * @return array<string, string>
	 */
	private function input( array $changes = [] ): array {
		return [
			'title'         => 'Cumpleaños de Ana María',
			'type_id'       => '1',
			'start_date'    => '2026-10-07',
			'start_time'    => '15:00',
			'description'   => 'Celebración en la sala de juntas.',
			'attachment_id' => '315',
			...$changes,
		];
	}

	/**
	 * Errores por campo al crear con cambios.
	 *
	 * @param array<string, string> $changes Cambios.
	 *
	 * @return array<string, list<string>>
	 */
	private function errors_for( array $changes ): array {
		try {
			$this->service()->create( $this->input( $changes ), self::USER );
		} catch ( ValidationException $error ) {
			return $error->errors();
		}

		$this->fail( 'Debió responder 422.' );
	}

	/**
	 * Crea un tipo de evento.
	 *
	 * @param string $name                Nombre.
	 * @param bool   $requires_attachment Si exige adjunto.
	 */
	private function type( string $name, bool $requires_attachment ): void {
		$this->types->insert( new EventType( null, $name, mb_strtolower( $name ), (string) preg_replace( '/[^a-z]+/', '-', strtolower( $name ) ), '#155728', 'star', $requires_attachment, '', count( $this->types->types ) + 1, '2026-10-01 00:00:00', '2026-10-01 00:00:00' ) );
	}

	/**
	 * Servicio con reloj fijo.
	 *
	 * @param array<string, mixed> $items Configuración.
	 */
	private function service( array $items = [] ): EventService {
		$config = new Config(
			[
				'ui' => [
					'timezone'          => 'America/Bogota',
					'page_sizes'        => [ 25, 50, 100 ],
					'default_page_size' => 25,
				],
				...$items,
			]
		);
		$clock  = new class() implements Clock {
			public function now(): DateTimeImmutable {
				return new DateTimeImmutable( '2026-10-08 01:30:00', new DateTimeZone( 'UTC' ) );
			}
		};

		return new EventService(
			$this->events,
			$this->types,
			$this->attachments,
			new MediaPolicy( [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf' ] ),
			DateFormatter::from_config( $config, $clock ),
			$config
		);
	}
}
