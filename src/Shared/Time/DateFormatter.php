<?php
/**
 * Formato de fechas en la zona horaria de Colombia.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Time;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Probolsas\Eventos\Core\Config;

/**
 * Única puerta para guardar y mostrar fechas. Hay dos clases de fecha:
 *
 * - **Momentos de auditoría** (creado, actualizado): se guardan en UTC con el formato `Y-m-d H:i:s`, se
 *   muestran en la zona configurada (America/Bogota) y la API los entrega en ISO 8601 con desplazamiento
 *   (`2026-10-01T23:30:00-05:00`).
 * - **Fechas y horas de calendario** (cuándo ocurre un evento): son la hora de pared de Colombia. Se guardan,
 *   se entregan y se muestran sin ninguna conversión de zona; así un evento de las 3:00 p. m. del 7 de octubre
 *   nunca aparece en otro día ni a otra hora.
 *
 * No usa wp_date(): el resultado no depende del idioma del sitio ni de su zona horaria. El formateador JS
 * (assets/src/js/core/date.js) aplica las mismas reglas y comparte casos de prueba con este.
 */
final class DateFormatter {

	private const STORAGE_FORMAT             = 'Y-m-d H:i:s';
	private const CALENDAR_FORMAT            = 'Y-m-d';
	private const CALENDAR_TIME_FORMAT       = 'H:i:s';
	private const CALENDAR_SHORT_TIME_FORMAT = 'H:i';

	/**
	 * Zona horaria UTC.
	 *
	 * @var DateTimeZone
	 */
	private DateTimeZone $utc;

	/**
	 * Crea el formateador.
	 *
	 * @param Clock        $clock       Reloj.
	 * @param DateTimeZone $timezone    Zona horaria de visualización.
	 * @param string       $date_format Formato de fecha (sintaxis de PHP).
	 * @param string       $time_format Formato de hora (sintaxis de PHP, 12 h).
	 * @param string       $am          Texto para antes del mediodía.
	 * @param string       $pm          Texto para después del mediodía.
	 */
	public function __construct(
		private readonly Clock $clock,
		private readonly DateTimeZone $timezone,
		private readonly string $date_format,
		private readonly string $time_format,
		private readonly string $am,
		private readonly string $pm
	) {
		$this->utc = new DateTimeZone( 'UTC' );
	}

	/**
	 * Crea el formateador con la configuración de `config/ui.php`.
	 *
	 * @param Config $config Configuración del plugin.
	 * @param Clock  $clock  Reloj.
	 */
	public static function from_config( Config $config, Clock $clock ): self {
		$meridiem = $config->get( 'ui.meridiem', [] );
		$meridiem = is_array( $meridiem ) ? $meridiem : [];

		return new self(
			$clock,
			new DateTimeZone( (string) $config->get( 'ui.timezone', 'America/Bogota' ) ),
			(string) $config->get( 'ui.date_format', 'd/m/Y' ),
			(string) $config->get( 'ui.time_format', 'h:i' ),
			(string) ( $meridiem['am'] ?? 'a. m.' ),
			(string) ( $meridiem['pm'] ?? 'p. m.' )
		);
	}

	/**
	 * Momento actual en UTC, listo para guardar en la base de datos.
	 */
	public function now_for_storage(): string {
		return $this->clock->now()->setTimezone( $this->utc )->format( self::STORAGE_FORMAT );
	}

	/**
	 * Fecha local de un valor guardado en UTC. Ejemplo: `01/10/2026`.
	 *
	 * @param string $utc Fecha y hora UTC con el formato `Y-m-d H:i:s`.
	 */
	public function format_date( string $utc ): string {
		return $this->to_local( $utc )->format( $this->date_format );
	}

	/**
	 * Fecha y hora local de un valor guardado en UTC. Ejemplo: `01/10/2026 11:30 p. m.`.
	 *
	 * @param string $utc Fecha y hora UTC con el formato `Y-m-d H:i:s`.
	 */
	public function format_datetime( string $utc ): string {
		$local = $this->to_local( $utc );

		return $local->format( $this->date_format ) . ' ' . $this->format_clock( $local );
	}

	/**
	 * Fecha de hoy en Colombia (`Y-m-d`). Es la que usa el navegador para «hoy»: en UTC, después de las
	 * 7:00 p. m. de Colombia ya es el día siguiente.
	 */
	public function today(): string {
		return $this->clock->now()->setTimezone( $this->timezone )->format( self::CALENDAR_FORMAT );
	}

	/**
	 * Representación ISO 8601 en la zona local, para la API. Ejemplo: `2026-10-01T23:30:00-05:00`.
	 *
	 * @param string $utc Fecha y hora UTC con el formato `Y-m-d H:i:s`.
	 */
	public function to_iso( string $utc ): string {
		return $this->to_local( $utc )->format( DATE_ATOM );
	}

	/**
	 * Formatea una fecha de calendario (por ejemplo, el día de un evento). No aplica conversión de zona.
	 *
	 * @param string $date Fecha con el formato `Y-m-d`.
	 *
	 * @throws InvalidArgumentException Si la fecha no es válida.
	 */
	public function format_calendar_date( string $date ): string {
		return $this->parse( $date, self::CALENDAR_FORMAT, $this->timezone )->format( $this->date_format );
	}

	/**
	 * Formatea una hora de calendario en 12 h. Ejemplo: `15:00:00` → `03:00 p. m.`. No aplica conversión de zona.
	 *
	 * @param string $time Hora con el formato `H:i:s` (columna TIME) o `H:i` (campo del formulario).
	 *
	 * @throws InvalidArgumentException Si la hora no es válida.
	 */
	public function format_calendar_time( string $time ): string {
		$format = 5 === strlen( $time ) ? self::CALENDAR_SHORT_TIME_FORMAT : self::CALENDAR_TIME_FORMAT;

		return $this->format_clock( $this->parse( $time, $format, $this->timezone ) );
	}

	/**
	 * Hora en 12 h con su indicador, por ejemplo `11:30 p. m.`.
	 *
	 * @param DateTimeImmutable $moment Momento ya expresado en la zona que se quiere mostrar.
	 */
	private function format_clock( DateTimeImmutable $moment ): string {
		$meridiem = 'am' === $moment->format( 'a' ) ? $this->am : $this->pm;

		return $moment->format( $this->time_format ) . ' ' . $meridiem;
	}

	/**
	 * Convierte un valor UTC de la base de datos a la zona local.
	 *
	 * @param string $utc Fecha y hora UTC con el formato `Y-m-d H:i:s`.
	 *
	 * @throws InvalidArgumentException Si el valor no es válido.
	 */
	private function to_local( string $utc ): DateTimeImmutable {
		return $this->parse( $utc, self::STORAGE_FORMAT, $this->utc )->setTimezone( $this->timezone );
	}

	/**
	 * Interpreta una fecha con un formato exacto. Rechaza valores desbordados como el 30 de febrero.
	 *
	 * @param string       $value    Valor a interpretar.
	 * @param string       $format   Formato esperado.
	 * @param DateTimeZone $timezone Zona del valor.
	 *
	 * @throws InvalidArgumentException Si el valor no cumple el formato o no es una fecha real.
	 */
	private function parse( string $value, string $format, DateTimeZone $timezone ): DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat( '!' . $format, $value, $timezone );

		if ( false === $date || $date->format( $format ) !== $value ) {
			throw new InvalidArgumentException( 'Fecha no válida.' );
		}

		return $date;
	}
}
