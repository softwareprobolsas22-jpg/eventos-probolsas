<?php
/**
 * Página de resultados de una búsqueda de eventos.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Domain;

/**
 * Eventos de una página y total de coincidencias (para `X-WP-Total` y `X-WP-TotalPages`).
 */
final class EventPage {

	/**
	 * Crea la página.
	 *
	 * @param Event[] $events   Eventos de la página.
	 * @param int     $total    Total de eventos que cumplen los filtros.
	 * @param int     $per_page Registros por página.
	 *
	 * @phpstan-param list<Event> $events
	 */
	public function __construct(
		public readonly array $events,
		public readonly int $total,
		public readonly int $per_page
	) {}

	/**
	 * Cantidad de páginas (al menos 1).
	 */
	public function total_pages(): int {
		return max( 1, (int) ceil( $this->total / max( 1, $this->per_page ) ) );
	}
}
