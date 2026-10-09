<?php
/**
 * Representación de un evento en la API.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Presentation;

use Probolsas\Eventos\Domains\Event\Application\EventService;
use Probolsas\Eventos\Domains\Event\Domain\Event;
use Probolsas\Eventos\Domains\EventType\Domain\EventType;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeRepository;
use Probolsas\Eventos\Domains\Media\Domain\AttachmentGateway;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Ui\ColorContrast;

/**
 * Única definición de la representación de un evento (docs/api/events.md): la usan la gestión, el
 * detalle, los próximos eventos y el feed del calendario. Lee cada tipo y cada usuario una sola vez por
 * petición (un listado de 100 eventos no consulta 100 veces el mismo tipo).
 */
final class EventPresenter {

	/**
	 * Tipos ya leídos en esta petición.
	 *
	 * @var array<int, EventType|null>
	 */
	private array $types_cache = [];

	/**
	 * Usuarios ya leídos en esta petición.
	 *
	 * @var array<int, array{id: int, name: string}|null>
	 */
	private array $users_cache = [];

	/**
	 * Crea el presentador.
	 *
	 * @param EventService        $service     Servicio de eventos (`is_past` con la fecha de Colombia).
	 * @param EventTypeRepository $types       Tipos de evento.
	 * @param AttachmentGateway   $attachments Biblioteca de Medios.
	 * @param DateFormatter       $dates       Fechas.
	 * @param ColorContrast       $contrast    Tono de texto legible del tipo.
	 */
	public function __construct(
		private readonly EventService $service,
		private readonly EventTypeRepository $types,
		private readonly AttachmentGateway $attachments,
		private readonly DateFormatter $dates,
		private readonly ColorContrast $contrast
	) {}

	/**
	 * Representación pública de un evento.
	 *
	 * @param Event $event Evento.
	 *
	 * @return array<string, mixed>
	 */
	public function present( Event $event ): array {
		$schedule   = $event->schedule;
		$type       = $this->type( $event->type_id );
		$attachment = null === $event->attachment_id ? null : $this->attachments->find( $event->attachment_id );

		return [
			'id'          => $event->id,
			'title'       => $event->title,
			'description' => $event->description,
			'type'        => null === $type ? null : [
				'id'        => $type->id,
				'name'      => $type->name,
				'slug'      => $type->slug,
				'color'     => $type->color,
				'text_tone' => $this->contrast->readable_tone( $type->color ),
				'icon'      => $type->icon,
			],
			'start_date'  => $schedule->start_date,
			'start_time'  => $schedule->start_time,
			'end_date'    => $schedule->end_date,
			'end_time'    => $schedule->end_time,
			'all_day'     => $schedule->is_all_day(),
			'is_past'     => $this->service->is_past( $event ),
			'attachment'  => $attachment?->to_array(),
			'created_by'  => $this->user( $event->created_by ),
			'updated_by'  => $this->user( $event->updated_by ),
			'created_at'  => $this->dates->to_iso( $event->created_at_gmt ),
			'updated_at'  => $this->dates->to_iso( $event->updated_at_gmt ),
		];
	}

	/**
	 * Tipo por ID, leído una sola vez por petición.
	 *
	 * @param int $id ID del tipo.
	 */
	public function type( int $id ): ?EventType {
		if ( ! array_key_exists( $id, $this->types_cache ) ) {
			$this->types_cache[ $id ] = $this->types->find( $id );
		}

		return $this->types_cache[ $id ];
	}

	/**
	 * Usuario de WordPress por ID (`null` si fue eliminado), leído una sola vez por petición.
	 *
	 * @param int $id ID del usuario.
	 *
	 * @return array{id: int, name: string}|null
	 */
	public function user( int $id ): ?array {
		if ( ! array_key_exists( $id, $this->users_cache ) ) {
			$user                     = $id > 0 ? get_userdata( $id ) : false;
			$this->users_cache[ $id ] = false === $user ? null : [
				'id'   => $id,
				'name' => (string) $user->display_name,
			];
		}

		return $this->users_cache[ $id ];
	}
}
