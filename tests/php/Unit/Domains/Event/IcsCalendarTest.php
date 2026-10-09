<?php
/**
 * Pruebas del archivo iCalendar.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Domains\Event;

use DateTimeImmutable;
use DateTimeZone;
use Probolsas\Eventos\Domains\Event\Domain\Event;
use Probolsas\Eventos\Domains\Event\Domain\EventSchedule;
use Probolsas\Eventos\Domains\Event\Presentation\IcsCalendar;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * `.ics` de «Añadir a mi calendario» (RFC 5545). Regresión RL-03: el legado marcaba la hora de Colombia
 * con `Z` y el evento quedaba corrido 5 horas.
 *
 * @covers \Probolsas\Eventos\Domains\Event\Presentation\IcsCalendar
 */
final class IcsCalendarTest extends UnitTestCase {

	public function test_an_event_with_time_keeps_the_colombian_wall_time(): void {
		$ics = $this->build( new EventSchedule( '2026-10-07', '15:00' ) );

		$this->assertSame(
			[
				'BEGIN:VCALENDAR',
				'VERSION:2.0',
				'PRODID:-//Probolsas//Eventos Probolsas//ES',
				'CALSCALE:GREGORIAN',
				'METHOD:PUBLISH',
				'BEGIN:VTIMEZONE',
				'TZID:America/Bogota',
				'BEGIN:STANDARD',
				'DTSTART:19700101T000000',
				'TZOFFSETFROM:-0500',
				'TZOFFSETTO:-0500',
				'TZNAME:-05',
				'END:STANDARD',
				'END:VTIMEZONE',
				'BEGIN:VEVENT',
				'UID:evento-42@intranet.probolsas.com',
				'DTSTAMP:20261008T013000Z',
				'DTSTART;TZID=America/Bogota:20261007T150000',
				'DTEND;TZID=America/Bogota:20261007T160000',
				'SUMMARY:Cumpleaños de Ana María',
				'DESCRIPTION:Celebración en la sala de juntas.',
				'CATEGORIES:Cumpleaños',
				'END:VEVENT',
				'END:VCALENDAR',
				'',
			],
			explode( "\r\n", $ics )
		);
		$this->assertStringNotContainsString( 'T150000Z', $ics, 'RL-03: la hora de Colombia no se marca como UTC.' );
		$this->assertDoesNotMatchRegularExpression( "/[^\r]\n/", $ics, 'Todas las líneas terminan en CRLF.' );
	}

	public function test_an_all_day_event_uses_dates_with_an_exclusive_end(): void {
		$lines = explode( "\r\n", $this->build( new EventSchedule( '2026-12-31', null ) ) );

		$this->assertContains( 'DTSTART;VALUE=DATE:20261231', $lines );
		$this->assertContains( 'DTEND;VALUE=DATE:20270101', $lines );
		$this->assertNotContains( 'BEGIN:VTIMEZONE', $lines, 'Un evento de todo el día no depende de la zona.' );
	}

	public function test_a_late_event_ends_on_the_next_day(): void {
		$lines = explode( "\r\n", $this->build( new EventSchedule( '2026-10-07', '23:30' ) ) );

		$this->assertContains( 'DTSTART;TZID=America/Bogota:20261007T233000', $lines );
		$this->assertContains( 'DTEND;TZID=America/Bogota:20261008T003000', $lines );
	}

	public function test_the_end_of_v1_1_events_is_used_when_present(): void {
		// D-7 (v1.1): el .ics ya calcula DTEND desde EventSchedule.
		$timed   = explode( "\r\n", $this->build( new EventSchedule( '2026-10-07', '09:00', '2026-10-09', '17:00' ) ) );
		$all_day = explode( "\r\n", $this->build( new EventSchedule( '2026-10-07', null, '2026-10-09' ) ) );

		$this->assertContains( 'DTEND;TZID=America/Bogota:20261009T170000', $timed );
		$this->assertContains( 'DTEND;VALUE=DATE:20261010', $all_day );
	}

	public function test_text_is_escaped_and_long_lines_are_folded_without_breaking_characters(): void {
		$description = "Primera línea; con coma, y barra \\\r\nSegunda línea " . str_repeat( 'ñandú ', 20 );
		$ics         = $this->build( new EventSchedule( '2026-10-07', null ), $description, '' );

		$this->assertStringContainsString( 'DESCRIPTION:Primera línea\\; con coma\\, y barra \\\\\\nSegunda línea ñandú', $ics );
		$this->assertStringNotContainsString( 'CATEGORIES', $ics, 'Sin tipo, sin categoría.' );

		foreach ( explode( "\r\n", $ics ) as $line ) {
			$this->assertLessThanOrEqual( 75, strlen( $line ), $line );
			$this->assertTrue( mb_check_encoding( $line, 'UTF-8' ), 'El plegado no parte caracteres: ' . $line );
		}

		$unfolded = str_replace( "\r\n ", '', $ics );
		$this->assertStringContainsString( 'Segunda línea ' . trim( str_repeat( 'ñandú ', 20 ) ), $unfolded );
	}

	public function test_the_filename_names_the_event(): void {
		$this->assertSame( 'evento-42.ics', $this->ics()->filename( $this->event( new EventSchedule( '2026-10-07' ) ) ) );
	}

	/**
	 * Archivo de un evento.
	 *
	 * @param EventSchedule $schedule    Horario.
	 * @param string        $description Descripción.
	 * @param string        $type_name   Tipo.
	 */
	private function build( EventSchedule $schedule, string $description = 'Celebración en la sala de juntas.', string $type_name = 'Cumpleaños' ): string {
		return $this->ics()->build( $this->event( $schedule, $description ), $type_name, new DateTimeImmutable( '2026-10-07 20:30:00', new DateTimeZone( 'America/Bogota' ) ) );
	}

	/**
	 * Evento de prueba.
	 *
	 * @param EventSchedule $schedule    Horario.
	 * @param string        $description Descripción.
	 */
	private function event( EventSchedule $schedule, string $description = '' ): Event {
		return new Event( 42, 1, 'Cumpleaños de Ana María', $description, $schedule, null, 3, 3, '2026-10-01 13:30:00', '2026-10-01 13:30:00' );
	}

	/**
	 * Generador para la zona de Colombia.
	 */
	private function ics(): IcsCalendar {
		return new IcsCalendar( new DateTimeZone( 'America/Bogota' ), 'intranet.probolsas.com' );
	}
}
