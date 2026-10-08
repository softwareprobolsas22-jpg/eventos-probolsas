<?php
/**
 * Migración 2: tipos de evento iniciales.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\EventType\Infrastructure;

use Probolsas\Eventos\Core\Database\Migration;
use Probolsas\Eventos\Domains\EventType\Application\EventTypeService;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeRepository;

/**
 * Crea los 4 tipos del plugin anterior en una instalación nueva (tabla publicada en
 * docs/api/event-types.md). Si ya hay tipos, no hace nada.
 *
 * Usa el servicio (no SQL directo): los tipos iniciales cumplen las mismas reglas que los que crea el
 * usuario (slug, nombre normalizado, color en mayúsculas y fechas en UTC). Los colores son la propuesta
 * del Sprint 1; el PO puede cambiarlos desde la pantalla «Tipos de evento».
 */
final class SeedDefaultEventTypes implements Migration {

	/**
	 * Tipos iniciales, en orden: [ nombre, color, ícono, requiere adjunto ].
	 */
	public const DEFAULTS = [
		[ 'Cumpleaños', '#9D174D', 'cake-candles', true ],
		[ 'Capacitaciones', '#155728', 'graduation-cap', true ],
		[ 'Reuniones especiales', '#B45309', 'star', true ],
		[ 'Reuniones laborales', '#1D4ED8', 'briefcase', false ],
	];

	/**
	 * Crea la migración.
	 *
	 * @param EventTypeRepository $repository Repositorio.
	 * @param EventTypeService    $service    Servicio de tipos de evento.
	 */
	public function __construct(
		private readonly EventTypeRepository $repository,
		private readonly EventTypeService $service
	) {}

	/**
	 * Versión del esquema.
	 */
	public function version(): int {
		return 2;
	}

	/**
	 * Crea los tipos si la tabla está vacía.
	 */
	public function up(): void {
		if ( ! $this->repository->is_empty() ) {
			return;
		}

		foreach ( self::DEFAULTS as $index => [ $name, $color, $icon, $requires_attachment ] ) {
			$this->service->create(
				[
					'name'                => $name,
					'color'               => $color,
					'icon'                => $icon,
					'requires_attachment' => $requires_attachment ? '1' : '0',
					'sort_order'          => $index + 1,
				]
			);
		}
	}
}
