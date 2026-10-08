<?php
/**
 * Casos de uso de los tipos de evento.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\EventType\Application;

use Probolsas\Eventos\Domains\EventType\Domain\EventType;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeData;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeRepository;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeSummary;
use Probolsas\Eventos\Shared\Errors\ConflictException;
use Probolsas\Eventos\Shared\Errors\NotFoundException;
use Probolsas\Eventos\Shared\Ordering\Reordering;
use Probolsas\Eventos\Shared\Persistence\DuplicateEntryException;
use Probolsas\Eventos\Shared\Text\Slugger;
use Probolsas\Eventos\Shared\Text\TextNormalizer;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Ui\IconCatalog;
use Probolsas\Eventos\Shared\Validation\ValidationException;
use Probolsas\Eventos\Shared\Validation\Validator;

/**
 * Reglas de negocio de los tipos de evento (D-2, contrato en docs/api/event-types.md):
 * - Nombre obligatorio y único sin distinguir mayúsculas ni tildes; slug automático que no cambia.
 * - Color hexadecimal (se guarda en mayúsculas) e ícono de la lista permitida.
 * - «Requiere adjunto» es un sí/no; por defecto, no.
 * - No se puede eliminar un tipo que tenga eventos.
 */
final class EventTypeService {

	public const NAME_MAX_LENGTH        = 100;
	public const DESCRIPTION_MAX_LENGTH = 500;
	public const SORT_ORDER_MAX         = 9999;
	public const COLOR_PATTERN          = '^#[0-9A-Fa-f]{6}$';

	/**
	 * Valores aceptados para «Requiere adjunto» (la API recibe booleanos, números o texto).
	 */
	private const BOOLEAN_VALUES = [
		'1'     => true,
		'true'  => true,
		'0'     => false,
		'false' => false,
	];

	/**
	 * Crea el servicio.
	 *
	 * @param EventTypeRepository $repository Repositorio.
	 * @param TextNormalizer      $normalizer Normalizador de texto.
	 * @param Slugger             $slugger    Generador de slugs.
	 * @param DateFormatter       $dates      Fechas.
	 * @param IconCatalog         $icons      Íconos permitidos.
	 */
	public function __construct(
		private readonly EventTypeRepository $repository,
		private readonly TextNormalizer $normalizer,
		private readonly Slugger $slugger,
		private readonly DateFormatter $dates,
		private readonly IconCatalog $icons
	) {}

	/**
	 * Reglas de validación para el navegador (`epConfig.rules.event_type`, R-24). Salen de las mismas
	 * constantes que usa validate(): el formulario y la API validan lo mismo.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function client_rules(): array {
		return [
			'name'        => [
				'required'  => true,
				'maxLength' => self::NAME_MAX_LENGTH,
			],
			'color'       => [
				'required' => true,
				'pattern'  => self::COLOR_PATTERN,
			],
			'icon'        => [
				'required' => true,
				'oneOf'    => 'icons',
			],
			'description' => [ 'maxLength' => self::DESCRIPTION_MAX_LENGTH ],
		];
	}

	/**
	 * Todos los tipos con su conteo de eventos.
	 *
	 * @return list<EventTypeSummary>
	 */
	public function list(): array {
		return $this->repository->all_with_event_counts();
	}

	/**
	 * Un tipo con su conteo de eventos.
	 *
	 * @param int $id ID.
	 *
	 * @throws NotFoundException Si no existe.
	 */
	public function get( int $id ): EventTypeSummary {
		return new EventTypeSummary( $this->find_or_fail( $id ), $this->repository->count_events( $id ) );
	}

	/**
	 * Crea un tipo.
	 *
	 * @param array<string, mixed> $input Datos recibidos.
	 *
	 * @throws ValidationException Si los datos no son válidos.
	 */
	public function create( array $input ): EventTypeSummary {
		$data = $this->validate( $input );
		$now  = $this->dates->now_for_storage();
		$slug = $this->slugger->unique( $data->name, fn( string $slug ): bool => $this->repository->slug_exists( $slug ) );

		$type = new EventType( null, $data->name, $data->name_key, $slug, $data->color, $data->icon, $data->requires_attachment, $data->description, $data->sort_order, $now, $now );

		try {
			$type = $this->repository->insert( $type );
		} catch ( DuplicateEntryException ) {
			throw $this->concurrent_duplicate_error(); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Se entrega como JSON; la interfaz lo muestra como texto.
		}

		return new EventTypeSummary( $type, 0 );
	}

	/**
	 * Actualiza un tipo. El slug no cambia.
	 *
	 * @param int                  $id    ID.
	 * @param array<string, mixed> $input Datos recibidos.
	 *
	 * @throws NotFoundException   Si no existe.
	 * @throws ValidationException Si los datos no son válidos.
	 */
	public function update( int $id, array $input ): EventTypeSummary {
		$type    = $this->find_or_fail( $id );
		$data    = $this->validate( $input, $type );
		$updated = $type->with_changes( $data, $this->dates->now_for_storage() );

		try {
			$this->repository->update( $updated );
		} catch ( DuplicateEntryException ) {
			throw $this->concurrent_duplicate_error(); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Se entrega como JSON; la interfaz lo muestra como texto.
		}

		return new EventTypeSummary( $updated, $this->repository->count_events( $id ) );
	}

	/**
	 * Guarda el orden elegido por el usuario: los tipos quedan en las posiciones 1, 2, 3… Es el orden de
	 * los filtros, la leyenda del calendario y el selector de tipo.
	 *
	 * @param mixed $ids IDs de todos los tipos, en el orden deseado.
	 *
	 * @return list<EventTypeSummary> Tipos en el orden nuevo.
	 *
	 * @throws ValidationException Si la lista no incluye cada tipo exactamente una vez.
	 */
	public function reorder( mixed $ids ): array {
		$existing = array_map( static fn( EventTypeSummary $summary ): int => (int) $summary->type->id, $this->list() );

		$this->repository->reorder(
			Reordering::positions( $ids, $existing, __( 'El orden debe incluir cada tipo de evento una sola vez. Recarga la página e inténtalo de nuevo.', 'eventos-probolsas' ) )
		);

		return $this->list();
	}

	/**
	 * Elimina un tipo sin eventos. Si no existe, lanza NotFoundException.
	 *
	 * @param int $id ID.
	 *
	 * @throws ConflictException Si tiene eventos asociados.
	 */
	public function delete( int $id ): void {
		$type   = $this->find_or_fail( $id );
		$events = $this->repository->count_events( $id );

		if ( $events > 0 ) {
			$message = sprintf(
				/* translators: 1: nombre del tipo de evento, 2: cantidad de eventos. */
				_n(
					'No se puede eliminar «%1$s» porque tiene %2$d evento asociado.',
					'No se puede eliminar «%1$s» porque tiene %2$d eventos asociados.',
					$events,
					'eventos-probolsas'
				),
				$type->name,
				$events
			);

			throw new ConflictException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Se entrega como JSON; la interfaz lo muestra como texto.
		}

		$this->repository->delete( $id );
	}

	/**
	 * Valida y normaliza los datos de entrada.
	 *
	 * @param array<string, mixed> $input   Datos recibidos.
	 * @param EventType|null       $current Tipo que se edita (null al crear).
	 *
	 * @throws ValidationException Si los datos no son válidos.
	 */
	private function validate( array $input, ?EventType $current = null ): EventTypeData {
		$validator = new Validator( $input );

		$name = $validator->field( 'name', __( 'Nombre', 'eventos-probolsas' ) )->required()->max_length( self::NAME_MAX_LENGTH );
		$name->rule(
			fn(): bool => ! $this->repository->name_key_exists( $this->normalizer->normalize( $name->text() ), $current?->id ),
			/* translators: %s: nombre del tipo de evento. */
			sprintf( __( 'Ya existe un tipo de evento llamado «%s».', 'eventos-probolsas' ), $name->text() )
		);

		$color       = $validator->field( 'color', __( 'Color', 'eventos-probolsas' ) )->required()->hex_color();
		$icon        = $validator->field( 'icon', __( 'Ícono', 'eventos-probolsas' ) )->required()->one_of( $this->icons->keys() );
		$attachment  = $validator->field( 'requires_attachment', __( 'Requiere adjunto', 'eventos-probolsas' ) )->one_of( array_keys( self::BOOLEAN_VALUES ) );
		$description = $validator->field( 'description', __( 'Descripción', 'eventos-probolsas' ) )->max_length( self::DESCRIPTION_MAX_LENGTH );
		$sort_order  = $validator->field( 'sort_order', __( 'Orden', 'eventos-probolsas' ) )->integer( 0, self::SORT_ORDER_MAX );

		$validator->validate();

		return new EventTypeData(
			$name->text(),
			$this->normalizer->normalize( $name->text() ),
			strtoupper( $color->text() ),
			$icon->text(),
			self::BOOLEAN_VALUES[ strtolower( $attachment->text() ) ] ?? false,
			$description->text(),
			// Sin orden recibido: al editar conserva su posición y al crear va al final.
			'' === $sort_order->text() ? ( $current->sort_order ?? $this->repository->next_sort_order() ) : (int) $sort_order->text()
		);
	}

	/**
	 * Tipo por ID o error 404.
	 *
	 * @param int $id ID.
	 *
	 * @throws NotFoundException Si no existe.
	 */
	private function find_or_fail( int $id ): EventType {
		$type = $this->repository->find( $id );

		if ( null === $type ) {
			throw new NotFoundException( esc_html__( 'El tipo de evento no existe o fue eliminado.', 'eventos-probolsas' ) );
		}

		return $type;
	}

	/**
	 * Error cuando otro usuario guardó a la vez el mismo nombre (lo detecta el índice único).
	 */
	private function concurrent_duplicate_error(): ValidationException {
		return new ValidationException(
			[],
			__( 'Otro usuario acaba de guardar un tipo de evento con el mismo nombre. Revisa los datos e inténtalo de nuevo.', 'eventos-probolsas' )
		);
	}
}
