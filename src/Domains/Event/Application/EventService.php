<?php
/**
 * Casos de uso de los eventos.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Application;

use Generator;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Domains\Event\Domain\Event;
use Probolsas\Eventos\Domains\Event\Domain\EventData;
use Probolsas\Eventos\Domains\Event\Domain\EventPage;
use Probolsas\Eventos\Domains\Event\Domain\EventQuery;
use Probolsas\Eventos\Domains\Event\Domain\EventRepository;
use Probolsas\Eventos\Domains\Event\Domain\EventSchedule;
use Probolsas\Eventos\Domains\EventType\Domain\EventType;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeRepository;
use Probolsas\Eventos\Domains\Media\Domain\Attachment;
use Probolsas\Eventos\Domains\Media\Domain\AttachmentGateway;
use Probolsas\Eventos\Domains\Media\Domain\MediaPolicy;
use Probolsas\Eventos\Shared\Errors\NotFoundException;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Validation\ValidationException;
use Probolsas\Eventos\Shared\Validation\Validator;

/**
 * Reglas de negocio de los eventos (contrato en docs/api/events.md):
 * - Título de 3 a 150 caracteres, tipo existente, fecha real y hora opcional (sin hora = todo el día).
 * - Se permiten fechas pasadas (D-3); `is_past` lo calcula el servidor con la fecha de Colombia.
 * - Adjunto: imagen o PDF de la Biblioteca de Medios según su MIME real (R-09); obligatorio si el tipo
 *   lo exige. Eliminar un evento nunca borra el archivo (D-4).
 * - La hora de fin y los eventos de varios días llegan en v1.1 (D-7): en v1 se rechazan.
 */
final class EventService {

	public const TITLE_MIN_LENGTH       = 3;
	public const TITLE_MAX_LENGTH       = 150;
	public const DESCRIPTION_MAX_LENGTH = 2000;
	public const SEARCH_MAX_LENGTH      = 100;

	/**
	 * Tamaño de los lotes con que se recorre una exportación.
	 */
	private const EXPORT_BATCH = 500;

	/**
	 * Crea el servicio.
	 *
	 * @param EventRepository     $events      Repositorio de eventos.
	 * @param EventTypeRepository $types       Repositorio de tipos.
	 * @param AttachmentGateway   $attachments Biblioteca de Medios.
	 * @param MediaPolicy         $media       Archivos permitidos.
	 * @param DateFormatter       $dates       Fechas.
	 * @param Config              $config      Configuración (tamaños de página).
	 */
	public function __construct(
		private readonly EventRepository $events,
		private readonly EventTypeRepository $types,
		private readonly AttachmentGateway $attachments,
		private readonly MediaPolicy $media,
		private readonly DateFormatter $dates,
		private readonly Config $config
	) {}

	/**
	 * Reglas de validación para el navegador (`epConfig.rules.event`, R-24). Salen de las mismas
	 * constantes que usa validate().
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function client_rules(): array {
		return [
			'title'         => [
				'required'  => true,
				'minLength' => self::TITLE_MIN_LENGTH,
				'maxLength' => self::TITLE_MAX_LENGTH,
			],
			'type_id'       => [
				'required' => true,
				'oneOf'    => 'event_types',
			],
			'start_date'    => [
				'required' => true,
				'format'   => 'date',
			],
			'start_time'    => [ 'format' => 'time' ],
			'description'   => [ 'maxLength' => self::DESCRIPTION_MAX_LENGTH ],
			'attachment_id' => [
				'requiredWhen' => 'type.requires_attachment',
				'mimes'        => 'media.allowed_mimes',
			],
		];
	}

	/**
	 * Evento por ID.
	 *
	 * @param int $id ID.
	 *
	 * @throws NotFoundException Si no existe.
	 */
	public function get( int $id ): Event {
		$event = $this->events->find( $id );

		if ( null === $event ) {
			throw new NotFoundException( esc_html__( 'El evento no existe o fue eliminado.', 'eventos-probolsas' ) );
		}

		return $event;
	}

	/**
	 * Eventos del mismo día que uno dado (navegación del modal del calendario y del detalle).
	 *
	 * @param Event $event Evento.
	 *
	 * @return list<Event>
	 */
	public function same_day( Event $event ): array {
		return $this->events->on_date( $event->schedule->start_date );
	}

	/**
	 * Indica si un evento ya pasó, con la fecha de hoy en Colombia.
	 *
	 * @param Event $event Evento.
	 */
	public function is_past( Event $event ): bool {
		return $event->schedule->is_past( $this->dates->today() );
	}

	/**
	 * Crea un evento.
	 *
	 * @param array<string, mixed> $input   Datos recibidos.
	 * @param int                  $user_id Usuario que lo crea.
	 *
	 * @throws ValidationException Si los datos no son válidos.
	 */
	public function create( array $input, int $user_id ): Event {
		$data = $this->validate( $input );
		$now  = $this->dates->now_for_storage();

		return $this->events->insert( new Event( null, $data->type_id, $data->title, $data->description, $data->schedule, $data->attachment_id, $user_id, $user_id, $now, $now ) );
	}

	/**
	 * Actualiza un evento.
	 *
	 * @param int                  $id      ID.
	 * @param array<string, mixed> $input   Datos recibidos.
	 * @param int                  $user_id Usuario que edita.
	 *
	 * @throws NotFoundException   Si no existe.
	 * @throws ValidationException Si los datos no son válidos.
	 */
	public function update( int $id, array $input, int $user_id ): Event {
		$updated = $this->get( $id )->with_changes( $this->validate( $input ), $user_id, $this->dates->now_for_storage() );
		$this->events->update( $updated );

		return $updated;
	}

	/**
	 * Elimina un evento. Su adjunto se queda en la Biblioteca de Medios (D-4).
	 *
	 * @param int $id ID.
	 *
	 * @throws NotFoundException Si no existe.
	 */
	public function delete( int $id ): void {
		$this->events->delete( (int) $this->get( $id )->id );
	}

	/**
	 * Busca eventos con los filtros de la tabla.
	 *
	 * @param EventQuery $query Búsqueda (ver query()).
	 */
	public function search( EventQuery $query ): EventPage {
		return $this->events->search( $query );
	}

	/**
	 * Todos los eventos que cumplen los filtros, sin paginar, en lotes (exportación CSV).
	 *
	 * @param EventQuery $query Búsqueda; se ignoran su página y su tamaño.
	 *
	 * @return Generator<int, Event>
	 */
	public function all_matching( EventQuery $query ): Generator {
		$page = 1;

		do {
			$result = $this->events->search( $query->with_page( $page, self::EXPORT_BATCH ) );
			yield from $result->events;
			++$page;
		} while ( $page <= $result->total_pages() );
	}

	/**
	 * Construye y valida la búsqueda a partir de los parámetros de `GET /events` (docs/api/events.md).
	 *
	 * @param array<string, mixed> $params Parámetros recibidos.
	 *
	 * @throws ValidationException Si algún filtro no es válido.
	 */
	public function query( array $params ): EventQuery {
		$sizes = $this->page_sizes();
		// `asc`/`desc` sin distinguir mayúsculas.
		$params['order'] = is_scalar( $params['order'] ?? null ) ? strtolower( (string) $params['order'] ) : '';
		$validator       = new Validator( $params );

		$page      = $validator->field( 'page', __( 'Página', 'eventos-probolsas' ) )->integer( 1 );
		$per_page  = $validator->field( 'per_page', __( 'Registros por página', 'eventos-probolsas' ) )->one_of( $sizes );
		$search    = $validator->field( 'search', __( 'Buscar', 'eventos-probolsas' ) )->max_length( self::SEARCH_MAX_LENGTH );
		$type      = $validator->field( 'type', __( 'Tipo', 'eventos-probolsas' ) )->integer( 1 );
		$date_from = $validator->field( 'date_from', __( 'Desde', 'eventos-probolsas' ) )->calendar_date();
		$date_to   = $validator->field( 'date_to', __( 'Hasta', 'eventos-probolsas' ) )->calendar_date();
		$order_by  = $validator->field( 'orderby', __( 'Ordenar por', 'eventos-probolsas' ) )->one_of( EventQuery::ORDER_BY );
		$order     = $validator->field( 'order', __( 'Orden', 'eventos-probolsas' ) )->one_of( [ 'asc', 'desc' ] );

		if ( '' !== $date_from->text() && '' !== $date_to->text() && ! $validator->has_error( 'date_from' ) ) {
			$date_to->rule(
				fn(): bool => $date_to->text() >= $date_from->text(),
				__( 'La fecha «Hasta» no puede ser anterior a la fecha «Desde».', 'eventos-probolsas' )
			);
		}

		$validator->validate();

		$words = preg_split( '/\s+/u', $search->text(), -1, PREG_SPLIT_NO_EMPTY );

		return new EventQuery(
			is_array( $words ) ? $words : [],
			'' === $type->text() ? null : (int) $type->text(),
			'' === $date_from->text() ? null : $date_from->text(),
			'' === $date_to->text() ? null : $date_to->text(),
			'' === $order_by->text() ? 'start_date' : $order_by->text(),
			'desc' !== $order->text(),
			'' === $page->text() ? 1 : (int) $page->text(),
			'' === $per_page->text() ? $this->default_page_size( $sizes ) : (int) $per_page->text()
		);
	}

	/**
	 * Valida y normaliza los datos de entrada.
	 *
	 * @param array<string, mixed> $input Datos recibidos.
	 *
	 * @throws ValidationException Si los datos no son válidos.
	 */
	private function validate( array $input ): EventData {
		$validator = new Validator( $input );
		$type      = null;

		$title = $validator->field( 'title', __( 'Título', 'eventos-probolsas' ) )
			->required()
			->min_length( self::TITLE_MIN_LENGTH )
			->max_length( self::TITLE_MAX_LENGTH );

		$validator->field( 'type_id', __( 'Tipo', 'eventos-probolsas' ) )
			->required()
			->rule(
				function () use ( $input, &$type ): bool {
					$id   = is_scalar( $input['type_id'] ?? null ) ? (string) $input['type_id'] : '';
					$type = ctype_digit( $id ) ? $this->types->find( (int) $id ) : null;
					return null !== $type;
				},
				/* translators: %s: nombre del campo. */
				sprintf( __( 'Selecciona una opción válida en el campo «%s».', 'eventos-probolsas' ), __( 'Tipo', 'eventos-probolsas' ) )
			);

		$date        = $validator->field( 'start_date', __( 'Fecha', 'eventos-probolsas' ) )->required()->calendar_date();
		$time        = $validator->field( 'start_time', __( 'Hora', 'eventos-probolsas' ) )->calendar_time();
		$description = $validator->field( 'description', __( 'Descripción', 'eventos-probolsas' ) )->max_length( self::DESCRIPTION_MAX_LENGTH );
		$attachment  = $this->validate_attachment( $validator );

		// D-7 (v1.1): la hora de fin y los eventos de varios días aún no se admiten.
		foreach ( [ 'end_date', 'end_time' ] as $field ) {
			if ( is_scalar( $input[ $field ] ?? null ) && '' !== trim( (string) $input[ $field ] ) ) {
				$validator->add_error( $field, __( 'La hora de fin estará disponible en una próxima versión.', 'eventos-probolsas' ) );
			}
		}

		if ( $type instanceof EventType && $type->requires_attachment && null === $attachment && ! $validator->has_error( 'attachment_id' ) ) {
			$validator->add_error( 'attachment_id', __( 'Este tipo de evento requiere una imagen o un PDF.', 'eventos-probolsas' ) );
		}

		$validator->validate();

		return new EventData(
			(int) $type?->id,
			$title->text(),
			$description->text(),
			new EventSchedule( $date->text(), '' === $time->text() ? null : substr( $time->text(), 0, 5 ) ),
			$attachment?->id
		);
	}

	/**
	 * Valida el adjunto: existe en la Biblioteca de Medios y es una imagen o un PDF por su MIME real (R-09).
	 *
	 * @param Validator $validator Validador.
	 */
	private function validate_attachment( Validator $validator ): ?Attachment {
		$attachment = null;
		$field      = $validator->field( 'attachment_id', __( 'Adjunto', 'eventos-probolsas' ) );

		$field->rule(
			function () use ( $field, &$attachment ): bool {
				$attachment = ctype_digit( $field->text() ) ? $this->attachments->find( (int) $field->text() ) : null;
				return null !== $attachment;
			},
			__( 'El archivo elegido ya no existe en la Biblioteca de Medios.', 'eventos-probolsas' )
		)->rule(
			fn(): bool => $attachment instanceof Attachment && $this->media->allows( $attachment ),
			__( 'El archivo debe ser una imagen o un PDF.', 'eventos-probolsas' )
		);

		return $validator->has_error( 'attachment_id' ) ? null : $attachment;
	}

	/**
	 * Tamaños de página permitidos (`config/ui.php`, R-23).
	 *
	 * @return list<int>
	 */
	private function page_sizes(): array {
		$sizes = $this->config->get( 'ui.page_sizes', [ 25, 50, 100 ] );

		return is_array( $sizes ) && [] !== $sizes ? array_values( array_map( 'intval', $sizes ) ) : [ 25, 50, 100 ];
	}

	/**
	 * Tamaño de página por defecto.
	 *
	 * @param int[] $sizes Tamaños permitidos (no vacío).
	 *
	 * @phpstan-param non-empty-list<int> $sizes
	 */
	private function default_page_size( array $sizes ): int {
		$default = (int) $this->config->get( 'ui.default_page_size', $sizes[0] );

		return in_array( $default, $sizes, true ) ? $default : $sizes[0];
	}
}
