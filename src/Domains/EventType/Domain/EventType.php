<?php
/**
 * Entidad Tipo de evento.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\EventType\Domain;

/**
 * Tipo de evento (Cumpleaños, Capacitaciones…) con su identidad visual. Es inmutable: los cambios crean
 * una copia.
 */
final class EventType {

	/**
	 * Crea el tipo.
	 *
	 * @param int|null $id                  ID (null si aún no se ha guardado).
	 * @param string   $name                Nombre visible.
	 * @param string   $name_key            Nombre normalizado (minúsculas, sin tildes) para garantizar la unicidad.
	 * @param string   $slug                Identificador para los atributos de los shortcodes. No cambia al renombrar.
	 * @param string   $color               Color `#RRGGBB` en mayúsculas.
	 * @param string   $icon                Clave del ícono (config/icons.php).
	 * @param bool     $requires_attachment Si los eventos de este tipo exigen imagen o PDF.
	 * @param string   $description         Descripción opcional.
	 * @param int      $sort_order          Orden en filtros, leyenda y selector.
	 * @param string   $created_at_gmt      Fecha de creación en UTC.
	 * @param string   $updated_at_gmt      Fecha de modificación en UTC.
	 */
	public function __construct(
		public readonly ?int $id,
		public readonly string $name,
		public readonly string $name_key,
		public readonly string $slug,
		public readonly string $color,
		public readonly string $icon,
		public readonly bool $requires_attachment,
		public readonly string $description,
		public readonly int $sort_order,
		public readonly string $created_at_gmt,
		public readonly string $updated_at_gmt
	) {}

	/**
	 * Copia con el ID asignado al guardarlo.
	 *
	 * @param int $id ID.
	 */
	public function with_id( int $id ): self {
		return new self( $id, $this->name, $this->name_key, $this->slug, $this->color, $this->icon, $this->requires_attachment, $this->description, $this->sort_order, $this->created_at_gmt, $this->updated_at_gmt );
	}

	/**
	 * Copia con los datos editables cambiados. El slug y la fecha de creación se conservan.
	 *
	 * @param EventTypeData $data           Datos validados.
	 * @param string        $updated_at_gmt Fecha de modificación en UTC.
	 */
	public function with_changes( EventTypeData $data, string $updated_at_gmt ): self {
		return new self( $this->id, $data->name, $data->name_key, $this->slug, $data->color, $data->icon, $data->requires_attachment, $data->description, $data->sort_order, $this->created_at_gmt, $updated_at_gmt );
	}

	/**
	 * Copia en otra posición (reordenar no cuenta como una modificación del tipo).
	 *
	 * @param int $sort_order Posición.
	 */
	public function with_sort_order( int $sort_order ): self {
		return new self( $this->id, $this->name, $this->name_key, $this->slug, $this->color, $this->icon, $this->requires_attachment, $this->description, $sort_order, $this->created_at_gmt, $this->updated_at_gmt );
	}
}
