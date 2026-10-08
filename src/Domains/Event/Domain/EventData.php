<?php
/**
 * Datos validados de un evento.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Domain;

/**
 * Datos de un evento ya validados y normalizados por EventService.
 */
final class EventData {

	/**
	 * Crea los datos.
	 *
	 * @param int           $type_id       Tipo de evento existente.
	 * @param string        $title         Título.
	 * @param string        $description   Descripción.
	 * @param EventSchedule $schedule      Cuándo ocurre.
	 * @param int|null      $attachment_id Adjunto permitido, o null.
	 */
	public function __construct(
		public readonly int $type_id,
		public readonly string $title,
		public readonly string $description,
		public readonly EventSchedule $schedule,
		public readonly ?int $attachment_id
	) {}
}
