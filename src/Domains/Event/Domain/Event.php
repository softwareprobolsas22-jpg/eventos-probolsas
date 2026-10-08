<?php
/**
 * Entidad Evento.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Domain;

/**
 * Evento del calendario de la intranet. Es inmutable: los cambios crean una copia.
 */
final class Event {

	/**
	 * Crea el evento.
	 *
	 * @param int|null      $id             ID (null si aún no se ha guardado).
	 * @param int           $type_id        Tipo de evento.
	 * @param string        $title          Título.
	 * @param string        $description    Descripción (puede estar vacía).
	 * @param EventSchedule $schedule       Cuándo ocurre.
	 * @param int|null      $attachment_id  Adjunto de la Biblioteca de Medios (imagen o PDF), o null.
	 * @param int           $created_by     Usuario que lo creó.
	 * @param int           $updated_by     Usuario que lo modificó por última vez.
	 * @param string        $created_at_gmt Fecha de creación en UTC.
	 * @param string        $updated_at_gmt Fecha de modificación en UTC.
	 */
	public function __construct(
		public readonly ?int $id,
		public readonly int $type_id,
		public readonly string $title,
		public readonly string $description,
		public readonly EventSchedule $schedule,
		public readonly ?int $attachment_id,
		public readonly int $created_by,
		public readonly int $updated_by,
		public readonly string $created_at_gmt,
		public readonly string $updated_at_gmt
	) {}

	/**
	 * Copia con el ID asignado al guardar.
	 *
	 * @param int $id ID.
	 */
	public function with_id( int $id ): self {
		return new self( $id, $this->type_id, $this->title, $this->description, $this->schedule, $this->attachment_id, $this->created_by, $this->updated_by, $this->created_at_gmt, $this->updated_at_gmt );
	}

	/**
	 * Copia con los datos editados. Conserva el ID, el autor y la fecha de creación.
	 *
	 * @param EventData $data           Datos validados.
	 * @param int       $user_id        Usuario que edita.
	 * @param string    $updated_at_gmt Fecha de la edición en UTC.
	 */
	public function with_changes( EventData $data, int $user_id, string $updated_at_gmt ): self {
		return new self( $this->id, $data->type_id, $data->title, $data->description, $data->schedule, $data->attachment_id, $this->created_by, $user_id, $this->created_at_gmt, $updated_at_gmt );
	}
}
