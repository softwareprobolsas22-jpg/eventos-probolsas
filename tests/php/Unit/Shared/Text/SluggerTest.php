<?php
/**
 * Pruebas del generador de slugs (casos CP-1.10 a CP-1.12).
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Shared\Text;

use LengthException;
use Probolsas\Eventos\Shared\Text\Slugger;
use Probolsas\Eventos\Shared\Text\TextNormalizer;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * @covers \Probolsas\Eventos\Shared\Text\Slugger
 */
final class SluggerTest extends UnitTestCase {

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function names(): array {
		return [
			'tildes'                => [ 'Gestión de Calidad', 'gestion-de-calidad' ],
			'ñ y símbolos'          => [ 'Año 2026 ¿Plan?', 'ano-2026-plan' ],
			'mayúsculas y espacios' => [ '  GESTIÓN   HUMANA  ', 'gestion-humana' ],
			'conector &'            => [ 'Gestión Financiera & Contable', 'gestion-financiera-contable' ],
			'guiones repetidos'     => [ 'Mantenimiento -- Infraestructura', 'mantenimiento-infraestructura' ],
			'solo símbolos'         => [ '¿¡!?', Slugger::FALLBACK ],
			'vacío'                 => [ '', Slugger::FALLBACK ],
		];
	}

	/**
	 * @dataProvider names
	 *
	 * @param string $name     Nombre escrito por el usuario.
	 * @param string $expected Slug esperado.
	 */
	public function test_slugify( string $name, string $expected ): void {
		$this->assertSame( $expected, $this->slugger()->slugify( $name ) );
	}

	public function test_slug_respects_max_length_without_trailing_hyphen(): void {
		$slug = ( new Slugger( new TextNormalizer(), 10 ) )->slugify( 'Gestión de Calidad' );

		$this->assertSame( 'gestion-de', $slug );
	}

	public function test_unique_returns_base_slug_when_free(): void {
		$this->assertSame( 'formatos', $this->slugger()->unique( 'Formatos', static fn(): bool => false ) );
	}

	public function test_unique_appends_consecutive_number_on_collision(): void {
		$taken = [ 'gestion-de-calidad', 'gestion-de-calidad-2' ];

		$slug = $this->slugger()->unique( 'Gestión de Calidad', static fn( string $slug ): bool => in_array( $slug, $taken, true ) );

		$this->assertSame( 'gestion-de-calidad-3', $slug );
	}

	public function test_unique_keeps_suffix_within_max_length(): void {
		$slugger = new Slugger( new TextNormalizer(), 12 );

		$slug = $slugger->unique( 'Gestión de Calidad', static fn( string $slug ): bool => 'gestion-de-c' === $slug );

		$this->assertSame( 'gestion-de-2', $slug );
		$this->assertLessThanOrEqual( 12, strlen( $slug ) );
	}

	public function test_unique_gives_up_after_too_many_attempts(): void {
		$this->expectException( LengthException::class );

		$this->slugger()->unique( 'Formatos', static fn(): bool => true );
	}

	/**
	 * Generador con la configuración por defecto.
	 */
	private function slugger(): Slugger {
		return new Slugger( new TextNormalizer() );
	}
}
