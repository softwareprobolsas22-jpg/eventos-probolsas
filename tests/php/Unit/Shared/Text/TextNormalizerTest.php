<?php
/**
 * Pruebas del normalizador de texto (casos CP-1.6 a CP-1.9).
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Shared\Text;

use Normalizer;
use Probolsas\Eventos\Shared\Text\TextNormalizer;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * @covers \Probolsas\Eventos\Shared\Text\TextNormalizer
 */
final class TextNormalizerTest extends UnitTestCase {

	/**
	 * Casos compartidos con search.test.js.
	 *
	 * @return array<string, mixed>
	 */
	private static function fixture(): array {
		return json_decode( (string) file_get_contents( dirname( __DIR__, 4 ) . '/fixtures/text-normalization.json' ), true );
	}

	/**
	 * Con y sin la extensión intl (el servidor de producción podría no tenerla).
	 *
	 * @return array<string, array{bool}>
	 */
	public static function implementations(): array {
		return [
			'con intl'          => [ true ],
			'respaldo sin intl' => [ false ],
		];
	}

	/**
	 * @dataProvider implementations
	 *
	 * @param bool $use_intl Usar intl.
	 */
	public function test_normalize_matches_shared_cases( bool $use_intl ): void {
		$normalizer = new TextNormalizer( $use_intl );

		foreach ( self::fixture()['normalize'] as $case ) {
			$this->assertSame( $case['expected'], $normalizer->normalize( $case['input'] ), "Entrada: «{$case['input']}»" );
		}
	}

	/**
	 * @dataProvider implementations
	 *
	 * @param bool $use_intl Usar intl.
	 */
	public function test_matches_shared_cases( bool $use_intl ): void {
		$normalizer = new TextNormalizer( $use_intl );

		foreach ( self::fixture()['matches'] as $case ) {
			$this->assertSame(
				$case['expected'],
				$normalizer->matches( $case['text'], $case['query'] ),
				"«{$case['query']}» en «{$case['text']}»"
			);
		}
	}

	public function test_decomposed_characters_are_composed_when_intl_is_available(): void {
		if ( ! class_exists( Normalizer::class ) ) {
			$this->markTestSkipped( 'La extensión intl no está disponible.' );
		}

		$normalizer = new TextNormalizer();

		foreach ( self::fixture()['normalize_decomposed'] as $case ) {
			$this->assertSame( $case['expected'], $normalizer->normalize( $case['input'] ) );
		}
	}
}
