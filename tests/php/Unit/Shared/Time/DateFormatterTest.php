<?php
/**
 * Pruebas del formateador de fechas (casos CP-1.1 a CP-1.5).
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Shared\Time;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Shared\Time\Clock;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * @covers \Probolsas\Eventos\Shared\Time\DateFormatter
 */
final class DateFormatterTest extends UnitTestCase {

	/**
	 * Casos compartidos con date.test.js.
	 *
	 * @return array<string, mixed>
	 */
	private static function fixture(): array {
		return json_decode( (string) file_get_contents( dirname( __DIR__, 4 ) . '/fixtures/date-formatting.json' ), true );
	}

	/**
	 * @return array<string, array{array<string, string>}>
	 */
	public static function datetimes(): array {
		$cases = [];
		foreach ( self::fixture()['datetimes'] as $case ) {
			$cases[ $case['case'] ] = [ $case ];
		}
		return $cases;
	}

	/**
	 * @dataProvider datetimes
	 *
	 * @param array<string, string> $sample Caso del fixture.
	 */
	public function test_formats_utc_values_in_colombian_time( array $sample ): void {
		$formatter = $this->formatter();

		$this->assertSame( $sample['date'], $formatter->format_date( $sample['utc'] ) );
		$this->assertSame( $sample['datetime'], $formatter->format_datetime( $sample['utc'] ) );
		$this->assertSame( $sample['iso'], $formatter->to_iso( $sample['utc'] ) );
	}

	public function test_calendar_dates_are_not_shifted_by_timezone(): void {
		foreach ( self::fixture()['calendar_dates'] as $case ) {
			$this->assertSame( $case['formatted'], $this->formatter()->format_calendar_date( $case['value'] ) );
		}
	}

	public function test_calendar_times_use_12_hours_without_timezone_shift(): void {
		foreach ( self::fixture()['calendar_times'] as $case ) {
			$this->assertSame( $case['formatted'], $this->formatter()->format_calendar_time( $case['value'] ), $case['value'] );
		}
	}

	public function test_rejects_invalid_calendar_times(): void {
		foreach ( self::fixture()['invalid_calendar_times'] as $value ) {
			try {
				$this->formatter()->format_calendar_time( $value );
				$this->fail( "Se aceptó una hora no válida: {$value}" );
			} catch ( InvalidArgumentException $error ) {
				$this->assertSame( 'Hora no válida.', $error->getMessage(), 'QA-004: el mensaje habla de la hora.' );
			}
		}
	}

	public function test_today_is_the_colombian_date(): void {
		foreach ( self::fixture()['today'] as $case ) {
			$clock = new class( $case['now_utc'] ) implements Clock {
				public function __construct( private readonly string $now ) {}

				public function now(): DateTimeImmutable {
					return new DateTimeImmutable( $this->now, new DateTimeZone( 'UTC' ) );
				}
			};

			$this->assertSame( $case['today'], $this->formatter( $clock )->today(), $case['case'] );
		}
	}

	public function test_now_for_storage_is_in_utc(): void {
		$clock = new class() implements Clock {
			public function now(): DateTimeImmutable {
				return new DateTimeImmutable( '2026-10-01 23:30:00', new DateTimeZone( 'America/Bogota' ) );
			}
		};

		$this->assertSame( '2026-10-02 04:30:00', $this->formatter( $clock )->now_for_storage() );
	}

	public function test_output_does_not_depend_on_php_default_timezone(): void {
		$previous = date_default_timezone_get();
		date_default_timezone_set( 'Asia/Tokyo' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set -- Se simula un servidor con otra zona.

		try {
			$this->assertSame( '01/10/2026 11:30 p. m.', $this->formatter()->format_datetime( '2026-10-02 04:30:00' ) );
		} finally {
			date_default_timezone_set( $previous ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set -- Restaura la zona original.
		}
	}

	public function test_rejects_invalid_values(): void {
		foreach ( self::fixture()['invalid_utc'] as $value ) {
			try {
				$this->formatter()->format_datetime( $value );
				$this->fail( "Se aceptó un valor no válido: {$value}" );
			} catch ( InvalidArgumentException $error ) {
				$this->assertSame( 'Fecha no válida.', $error->getMessage() );
			}
		}
	}

	/**
	 * Formateador con la configuración del fixture.
	 *
	 * @param Clock|null $clock Reloj (opcional).
	 */
	private function formatter( ?Clock $clock = null ): DateFormatter {
		$clock ??= new class() implements Clock {
			public function now(): DateTimeImmutable {
				return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
			}
		};

		return DateFormatter::from_config( new Config( [ 'ui' => self::fixture()['ui'] ] ), $clock );
	}
}
