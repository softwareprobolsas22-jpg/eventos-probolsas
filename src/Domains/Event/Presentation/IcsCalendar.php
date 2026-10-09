<?php
/**
 * Archivo iCalendar (.ics) de un evento.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Presentation;

use DateTimeImmutable;
use DateTimeZone;
use Probolsas\Eventos\Domains\Event\Domain\Event;
use Probolsas\Eventos\Domains\Event\Domain\EventSchedule;

/**
 * Genera el `.ics` de «Añadir a mi calendario» (RFC 5545). Corrige el defecto RL-03 del legado, que
 * marcaba la hora de Colombia con `Z` (UTC) y el evento quedaba corrido 5 horas:
 *
 * - Con hora: `DTSTART;TZID=America/Bogota:20261007T150000` y un `VTIMEZONE` que define la zona, así
 *   Google Calendar, Outlook y Apple la muestran a las 3:00 p. m. de Colombia en cualquier equipo.
 *   Sin hora de fin (v1), dura una hora, la duración por defecto de esos calendarios.
 * - Sin hora (todo el día): `DTSTART;VALUE=DATE:20261007` y `DTEND;VALUE=DATE` del día siguiente (exclusivo).
 * - Líneas terminadas en CRLF, plegadas a 75 octetos sin partir caracteres UTF-8, y texto escapado.
 */
final class IcsCalendar {

	/**
	 * Duración de un evento con hora y sin fin.
	 */
	private const DEFAULT_DURATION = '+1 hour';

	private const LINE_OCTETS = 75;

	/**
	 * Crea el generador.
	 *
	 * @param DateTimeZone $timezone Zona de las horas de los eventos (`config/ui.php`, America/Bogota).
	 * @param string       $domain   Dominio de la intranet, para el `UID` del evento.
	 */
	public function __construct(
		private readonly DateTimeZone $timezone,
		private readonly string $domain
	) {}

	/**
	 * Contenido del archivo.
	 *
	 * @param Event             $event     Evento.
	 * @param string            $type_name Nombre del tipo (categoría).
	 * @param DateTimeImmutable $now       Momento de la descarga (`DTSTAMP`).
	 */
	public function build( Event $event, string $type_name, DateTimeImmutable $now ): string {
		$schedule = $event->schedule;
		$lines    = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Probolsas//Eventos Probolsas//ES',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			...( $schedule->is_all_day() ? [] : $this->timezone_component( $schedule->start_date ) ),
			'BEGIN:VEVENT',
			'UID:evento-' . $event->id . '@' . $this->domain,
			'DTSTAMP:' . $now->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' ),
			...$this->dates( $schedule ),
			'SUMMARY:' . self::escape( $event->title ),
		];

		if ( '' !== $event->description ) {
			$lines[] = 'DESCRIPTION:' . self::escape( $event->description );
		}

		if ( '' !== $type_name ) {
			$lines[] = 'CATEGORIES:' . self::escape( $type_name );
		}

		$lines[] = 'END:VEVENT';
		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", array_map( [ self::class, 'fold' ], $lines ) ) . "\r\n";
	}

	/**
	 * Nombre del archivo descargado.
	 *
	 * @param Event $event Evento.
	 */
	public function filename( Event $event ): string {
		return 'evento-' . $event->id . '.ics';
	}

	/**
	 * `DTSTART` y `DTEND` del evento.
	 *
	 * @param EventSchedule $schedule Horario.
	 *
	 * @return list<string>
	 */
	private function dates( EventSchedule $schedule ): array {
		if ( $schedule->is_all_day() ) {
			$after_last = $this->local( $schedule->last_date(), '00:00' )->modify( '+1 day' );

			return [
				'DTSTART;VALUE=DATE:' . str_replace( '-', '', $schedule->start_date ),
				'DTEND;VALUE=DATE:' . $after_last->format( 'Ymd' ),
			];
		}

		$start = $this->local( $schedule->start_date, (string) $schedule->start_time );
		$end   = null === $schedule->end_time
			? $start->modify( self::DEFAULT_DURATION )
			: $this->local( $schedule->last_date(), $schedule->end_time );
		$tzid  = 'TZID=' . $this->timezone->getName();

		return [
			"DTSTART;{$tzid}:" . $start->format( 'Ymd\THis' ),
			"DTEND;{$tzid}:" . $end->format( 'Ymd\THis' ),
		];
	}

	/**
	 * `VTIMEZONE` de la zona de los eventos, con el desplazamiento vigente en la fecha del evento.
	 * Colombia no tiene horario de verano desde 1993: un solo componente `STANDARD` la describe.
	 *
	 * @param string $date Fecha del evento `Y-m-d`.
	 *
	 * @return list<string>
	 */
	private function timezone_component( string $date ): array {
		$moment = $this->local( $date, '12:00' );
		$offset = $moment->format( 'O' );

		return [
			'BEGIN:VTIMEZONE',
			'TZID:' . $this->timezone->getName(),
			'BEGIN:STANDARD',
			'DTSTART:19700101T000000',
			'TZOFFSETFROM:' . $offset,
			'TZOFFSETTO:' . $offset,
			'TZNAME:' . $moment->format( 'T' ),
			'END:STANDARD',
			'END:VTIMEZONE',
		];
	}

	/**
	 * Fecha y hora de pared en la zona de los eventos.
	 *
	 * @param string $date Fecha `Y-m-d`.
	 * @param string $time Hora `H:i`.
	 */
	private function local( string $date, string $time ): DateTimeImmutable {
		return new DateTimeImmutable( "{$date} {$time}:00", $this->timezone );
	}

	/**
	 * Escapa un texto según RFC 5545 (§3.3.11): barra invertida, punto y coma, coma y saltos de línea.
	 *
	 * @param string $text Texto.
	 */
	private static function escape( string $text ): string {
		$text = str_replace( [ "\r\n", "\r" ], "\n", $text );

		return str_replace( [ '\\', ';', ',', "\n" ], [ '\\\\', '\\;', '\\,', '\\n' ], $text );
	}

	/**
	 * Pliega una línea a 75 octetos (RFC 5545 §3.1): las siguientes empiezan con un espacio. No parte un
	 * carácter UTF-8 de varios bytes.
	 *
	 * @param string $line Línea.
	 */
	private static function fold( string $line ): string {
		$parts  = [];
		$limit  = self::LINE_OCTETS;
		$length = strlen( $line );

		while ( $length > $limit ) {
			$chunk   = mb_strcut( $line, 0, $limit, 'UTF-8' );
			$parts[] = $chunk;
			$line    = substr( $line, strlen( $chunk ) );
			$length  = strlen( $line );
			$limit   = self::LINE_OCTETS - 1; // El espacio inicial de la continuación cuenta.
		}

		$parts[] = $line;

		return implode( "\r\n ", $parts );
	}
}
