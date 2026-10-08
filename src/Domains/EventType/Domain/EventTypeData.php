<?php
/**
 * Datos validados de un tipo de evento.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\EventType\Domain;

/**
 * Resultado de validar la entrada de la API. Agrupa los campos editables para no repetir listas largas
 * de parámetros entre el servicio y la entidad.
 */
final class EventTypeData {

	/**
	 * Crea los datos.
	 *
	 * @param string $name                Nombre.
	 * @param string $name_key            Nombre normalizado.
	 * @param string $color               Color `#RRGGBB` en mayúsculas.
	 * @param string $icon                Clave del ícono.
	 * @param bool   $requires_attachment Si exige adjunto.
	 * @param string $description         Descripción.
	 * @param int    $sort_order          Orden.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $name_key,
		public readonly string $color,
		public readonly string $icon,
		public readonly bool $requires_attachment,
		public readonly string $description,
		public readonly int $sort_order
	) {}
}
