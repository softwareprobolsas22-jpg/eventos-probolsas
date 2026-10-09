<?php
/**
 * Resumen de eventos para el dashboard.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Dashboard\Application;

use DateTimeImmutable;
use DateTimeZone;
use Probolsas\Eventos\Domains\Event\Domain\EventRepository;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeRepository;
use Probolsas\Eventos\Shared\Time\DateFormatter;

/**
 * Cifras de las tarjetas de «Eventos» (H-206, D-17), con la fecha de Colombia (R-08):
 * - `today`: eventos que ocupan hoy.
 * - `next_30_days`: eventos que ocupan algún día desde hoy hasta hoy + 29 (30 días con hoy).
 * - `by_type`: todos los tipos en su orden, también los que no tienen eventos.
 * - `total`: todos los eventos.
 */
final class DashboardService {

	/**
	 * Días del rango «próximos 30 días», contando hoy.
	 */
	public const UPCOMING_DAYS = 30;

	/**
	 * Crea el servicio.
	 *
	 * @param EventRepository     $events Eventos.
	 * @param EventTypeRepository $types  Tipos (con su conteo de eventos).
	 * @param DateFormatter       $dates  Fechas (el «hoy» de Colombia).
	 */
	public function __construct(
		private readonly EventRepository $events,
		private readonly EventTypeRepository $types,
		private readonly DateFormatter $dates
	) {}

	/**
	 * Resumen.
	 *
	 * @return array{today: int, next_30_days: int, by_type: list<array{type_id: int, count: int}>, total: int, date_from: string, date_to: string}
	 */
	public function summary(): array {
		$today   = $this->dates->today();
		$last    = ( new DateTimeImmutable( $today . ' 00:00:00', new DateTimeZone( 'UTC' ) ) )->modify( '+' . ( self::UPCOMING_DAYS - 1 ) . ' days' )->format( 'Y-m-d' );
		$by_type = [];
		$total   = 0;

		foreach ( $this->types->all_with_event_counts() as $summary ) {
			$by_type[] = [
				'type_id' => (int) $summary->type->id,
				'count'   => $summary->events_count,
			];
			$total    += $summary->events_count;
		}

		return [
			'today'        => $this->events->count_in_range( $today, $today ),
			'next_30_days' => $this->events->count_in_range( $today, $last ),
			'by_type'      => $by_type,
			'total'        => $total,
			'date_from'    => $today,
			'date_to'      => $last,
		];
	}
}
