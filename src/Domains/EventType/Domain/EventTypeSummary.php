<?php
/**
 * Tipo de evento con su conteo de eventos.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\EventType\Domain;

/**
 * Modelo de lectura para listados: el tipo y cuántos eventos lo usan.
 */
final class EventTypeSummary {

	/**
	 * Crea el resumen.
	 *
	 * @param EventType $type         Tipo.
	 * @param int       $events_count Eventos que usan el tipo.
	 */
	public function __construct(
		public readonly EventType $type,
		public readonly int $events_count
	) {}
}
