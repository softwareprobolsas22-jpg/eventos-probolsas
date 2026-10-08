<?php
/**
 * Pruebas del contraste de colores.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Shared\Ui;

use InvalidArgumentException;
use Probolsas\Eventos\Shared\Ui\ColorContrast;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * Tono de texto legible sobre el color de cada tipo de evento (R-02). Casos compartidos con color.test.js.
 *
 * @covers \Probolsas\Eventos\Shared\Ui\ColorContrast
 */
final class ColorContrastTest extends UnitTestCase {

	/**
	 * Casos compartidos con el JS.
	 *
	 * @return array<string, mixed>
	 */
	private static function fixture(): array {
		return json_decode( (string) file_get_contents( dirname( __DIR__, 4 ) . '/fixtures/color-contrast.json' ), true );
	}

	/**
	 * @return array<string, array{array<string, mixed>}>
	 */
	public static function cases(): array {
		$cases = [];
		foreach ( self::fixture()['cases'] as $case ) {
			$cases[ $case['case'] ] = [ $case ];
		}
		return $cases;
	}

	/**
	 * @dataProvider cases
	 *
	 * @param array<string, mixed> $sample Caso del fixture.
	 */
	public function test_readable_tone_and_best_ratio( array $sample ): void {
		$contrast = new ColorContrast();

		$this->assertSame( $sample['tone'], $contrast->readable_tone( $sample['color'] ) );
		$this->assertEqualsWithDelta( $sample['best_ratio'], $contrast->best_ratio( $sample['color'] ), 0.01 );
	}

	public function test_lowercase_colors_are_accepted(): void {
		$this->assertSame( 'light', ( new ColorContrast() )->readable_tone( '#9d174d' ) );
	}

	public function test_any_background_reaches_aa_with_the_chosen_tone(): void {
		$contrast = new ColorContrast();
		$minimum  = self::fixture()['minimum_best_ratio'];

		// Recorre la escala de grises completa (donde está el peor caso) y una muestra de colores.
		for ( $value = 0; $value <= 255; $value++ ) {
			$gray = sprintf( '#%1$02X%1$02X%1$02X', $value );
			$this->assertGreaterThanOrEqual( $minimum, $contrast->best_ratio( $gray ), $gray );
		}
		for ( $value = 0; $value <= 0xFFFFFF; $value += 0x0F0F0F ) {
			$color = sprintf( '#%06X', $value );
			$this->assertGreaterThanOrEqual( $minimum, $contrast->best_ratio( $color ), $color );
		}
	}

	public function test_known_ratios(): void {
		$contrast = new ColorContrast();

		$this->assertEqualsWithDelta( 21.0, $contrast->ratio( '#000000', '#FFFFFF' ), 0.001 );
		$this->assertEqualsWithDelta( 1.0, $contrast->ratio( '#155728', '#155728' ), 0.001 );
		$this->assertEqualsWithDelta( $contrast->ratio( '#155728', '#FFFFFF' ), $contrast->ratio( '#FFFFFF', '#155728' ), 0.001 );
	}

	public function test_rejects_invalid_colors(): void {
		foreach ( self::fixture()['invalid'] as $value ) {
			try {
				( new ColorContrast() )->readable_tone( $value );
				$this->fail( "Se aceptó un color no válido: {$value}" );
			} catch ( InvalidArgumentException $error ) {
				$this->assertSame( 'Color no válido.', $error->getMessage() );
			}
		}
	}
}
