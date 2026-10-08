<?php
/**
 * Cuándo ocurre un evento.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Domain;

use InvalidArgumentException;

/**
 * Fecha y hora de un evento en la hora de pared de Colombia: se guardan y se entregan sin conversión de
 * zona (R-08). Preparado para la hora de fin y los eventos de varios días (D-7, v1.1, §5.6): el fin ya
 * existe con sus invariantes, aunque en v1 el servicio no lo admite.
 *
 * - Inicio obligatorio (`Y-m-d`); hora opcional (`H:i`): sin hora es un evento de todo el día.
 * - El fin no es anterior al inicio y, si lleva hora, el inicio también.
 */
final class EventSchedule {

	private const DATE = '/^\d{4}-\d{2}-\d{2}$/';
	private const TIME = '/^\d{2}:\d{2}$/';

	/**
	 * Crea el horario.
	 *
	 * @param string      $start_date Fecha de inicio `Y-m-d`.
	 * @param string|null $start_time Hora de inicio `H:i`, o null si es todo el día.
	 * @param string|null $end_date   Fecha de fin `Y-m-d` (v1.1; null = mismo día).
	 * @param string|null $end_time   Hora de fin `H:i` (v1.1).
	 *
	 * @throws InvalidArgumentException Si los valores no tienen el formato esperado o el fin es anterior al inicio.
	 */
	public function __construct(
		public readonly string $start_date,
		public readonly ?string $start_time = null,
		public readonly ?string $end_date = null,
		public readonly ?string $end_time = null
	) {
		self::assert_format( $start_date, self::DATE );
		self::assert_format( $start_time, self::TIME );
		self::assert_format( $end_date, self::DATE );
		self::assert_format( $end_time, self::TIME );

		if ( null !== $end_time && null === $start_time ) {
			throw new InvalidArgumentException( 'Un evento con hora de fin necesita hora de inicio.' );
		}

		// Las fechas y horas con formato fijo se comparan bien como texto.
		if ( $this->last_date() < $start_date || ( null !== $end_time && $this->last_date() === $start_date && $end_time < (string) $start_time ) ) {
			throw new InvalidArgumentException( 'El fin del evento no puede ser anterior a su inicio.' );
		}
	}

	/**
	 * Crea el horario a partir de las columnas de la base de datos (`TIME` con segundos).
	 *
	 * @param string      $start_date Fecha de inicio.
	 * @param string|null $start_time Hora de inicio `H:i:s` o null.
	 * @param string|null $end_date   Fecha de fin o null.
	 * @param string|null $end_time   Hora de fin `H:i:s` o null.
	 */
	public static function from_storage( string $start_date, ?string $start_time, ?string $end_date, ?string $end_time ): self {
		$short = static fn( ?string $time ): ?string => null === $time || '' === $time ? null : substr( $time, 0, 5 );

		return new self( $start_date, $short( $start_time ), '' === (string) $end_date ? null : $end_date, $short( $end_time ) );
	}

	/**
	 * Indica si el evento dura todo el día (sin hora de inicio).
	 */
	public function is_all_day(): bool {
		return null === $this->start_time;
	}

	/**
	 * Último día que ocupa el evento (el de inicio mientras no haya eventos de varios días).
	 */
	public function last_date(): string {
		return $this->end_date ?? $this->start_date;
	}

	/**
	 * Indica si el evento ya terminó respecto a una fecha de Colombia (`epConfig.today`, R-08).
	 *
	 * @param string $today Fecha de hoy `Y-m-d`.
	 */
	public function is_past( string $today ): bool {
		return $this->last_date() < $today;
	}

	/**
	 * Indica si el evento ocupa algún día del rango [desde, hasta] (consulta por solapamiento, §5.5).
	 *
	 * @param string $from Primer día `Y-m-d`.
	 * @param string $to   Último día `Y-m-d`.
	 */
	public function overlaps( string $from, string $to ): bool {
		return $this->start_date <= $to && $this->last_date() >= $from;
	}

	/**
	 * Verifica el formato de un valor opcional.
	 *
	 * @param string|null $value   Valor.
	 * @param string      $pattern Expresión regular.
	 *
	 * @throws InvalidArgumentException Si no cumple el formato.
	 */
	private static function assert_format( ?string $value, string $pattern ): void {
		if ( null !== $value && 1 !== preg_match( $pattern, $value ) ) {
			throw new InvalidArgumentException( sprintf( 'Formato de fecha u hora no válido: %s', $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Error de programación, no se muestra al usuario.
		}
	}
}
