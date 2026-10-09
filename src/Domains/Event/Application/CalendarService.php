<?php
/**
 * Consultas del calendario de los colaboradores.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Application;

use DateTimeImmutable;
use DateTimeZone;
use Probolsas\Eventos\Domains\Event\Domain\Event;
use Probolsas\Eventos\Domains\Event\Domain\EventRepository;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Validation\ValidationException;
use Probolsas\Eventos\Shared\Validation\Validator;

/**
 * Feed por rango de FullCalendar y próximos eventos (contrato en docs/api/events.md, «Calendario»):
 * - El rango llega como lo envía FullCalendar: `start` incluido y `end` exclusivo, ambos `Y-m-d` sin zona.
 *   Se consulta por solapamiento (§5.5) y como máximo 62 días, suficiente para la vista de mes (6 semanas).
 * - Los próximos eventos empiezan hoy en Colombia (DateFormatter::today(), R-08), no en la fecha UTC.
 */
final class CalendarService {

	/**
	 * Días que puede abarcar una consulta del feed.
	 */
	public const MAX_RANGE_DAYS = 62;

	public const UPCOMING_DEFAULT_LIMIT = 5;
	public const UPCOMING_MAX_LIMIT     = 20;

	/**
	 * Último día que pueden pedir los próximos eventos (no hay eventos más allá en una intranet).
	 */
	private const FAR_FUTURE = '9999-12-31';

	/**
	 * Crea el servicio.
	 *
	 * @param EventRepository $events Repositorio de eventos.
	 * @param DateFormatter   $dates  Fechas (el «hoy» de Colombia).
	 */
	public function __construct(
		private readonly EventRepository $events,
		private readonly DateFormatter $dates
	) {}

	/**
	 * Eventos que ocupan algún día del rango visible del calendario.
	 *
	 * @param array<string, mixed> $params `start`, `end` (exclusivo) y `types` (IDs de tipo).
	 *
	 * @return list<Event>
	 *
	 * @throws ValidationException Si el rango o los tipos no son válidos.
	 */
	public function feed( array $params ): array {
		$validator = new Validator( $params );
		$start     = $validator->field( 'start', __( 'Inicio', 'eventos-probolsas' ) )->required()->calendar_date();
		$end       = $validator->field( 'end', __( 'Fin', 'eventos-probolsas' ) )->required()->calendar_date();
		$types     = $this->type_ids( $validator, $params['types'] ?? [] );

		if ( ! $validator->has_error( 'start' ) ) {
			$end->rule(
				fn(): bool => $end->text() > $start->text(),
				__( 'La fecha «Fin» debe ser posterior a la fecha «Inicio».', 'eventos-probolsas' )
			)->rule(
				fn(): bool => $this->days_between( $start->text(), $end->text() ) <= self::MAX_RANGE_DAYS,
				/* translators: %d: número máximo de días. */
				sprintf( __( 'El calendario consulta máximo %d días a la vez.', 'eventos-probolsas' ), self::MAX_RANGE_DAYS )
			);
		}

		$validator->validate();

		return $this->events->in_range( $start->text(), $this->previous_day( $end->text() ), $types );
	}

	/**
	 * Próximos eventos, desde hoy en Colombia.
	 *
	 * @param array<string, mixed> $params `limit` (1 a 20, 5 por defecto) y `types` (IDs de tipo).
	 *
	 * @return list<Event>
	 *
	 * @throws ValidationException Si el límite o los tipos no son válidos.
	 */
	public function upcoming( array $params ): array {
		$validator = new Validator( $params );
		$limit     = $validator->field( 'limit', __( 'Cantidad', 'eventos-probolsas' ) )->integer( 1, self::UPCOMING_MAX_LIMIT );
		$types     = $this->type_ids( $validator, $params['types'] ?? [] );

		$validator->validate();

		return $this->events->in_range(
			$this->dates->today(),
			self::FAR_FUTURE,
			$types,
			'' === $limit->text() ? self::UPCOMING_DEFAULT_LIMIT : (int) $limit->text()
		);
	}

	/**
	 * IDs de tipo del filtro `types[]`. Vacío = todos los tipos.
	 *
	 * @param Validator $validator Validador donde se anota el error.
	 * @param mixed     $value     Valor recibido.
	 *
	 * @return list<int>
	 */
	private function type_ids( Validator $validator, mixed $value ): array {
		$values = is_array( $value ) ? array_values( $value ) : ( '' === $value || null === $value ? [] : [ $value ] );
		$ids    = [];

		foreach ( $values as $id ) {
			if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || 0 === (int) $id ) {
				$validator->add_error( 'types', __( 'El filtro de tipos no es válido.', 'eventos-probolsas' ) );

				return [];
			}

			$ids[] = (int) $id;
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Días entre dos fechas de calendario válidas.
	 *
	 * @param string $from Fecha `Y-m-d`.
	 * @param string $to   Fecha `Y-m-d`.
	 */
	private function days_between( string $from, string $to ): int {
		return (int) $this->date( $from )->diff( $this->date( $to ) )->days;
	}

	/**
	 * Día anterior a una fecha (el fin de FullCalendar es exclusivo; la consulta, inclusiva).
	 *
	 * @param string $date Fecha `Y-m-d`.
	 */
	private function previous_day( string $date ): string {
		return $this->date( $date )->modify( '-1 day' )->format( 'Y-m-d' );
	}

	/**
	 * Fecha de calendario como objeto, en UTC para que los días duren siempre 24 horas.
	 *
	 * @param string $date Fecha `Y-m-d` ya validada.
	 */
	private function date( string $date ): DateTimeImmutable {
		return new DateTimeImmutable( $date . ' 00:00:00', new DateTimeZone( 'UTC' ) );
	}
}
