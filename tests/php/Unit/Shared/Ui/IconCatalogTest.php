<?php
/**
 * Pruebas del catálogo de íconos.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Shared\Ui;

use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Shared\Ui\IconCatalog;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * @covers \Probolsas\Eventos\Shared\Ui\IconCatalog
 */
final class IconCatalogTest extends UnitTestCase {

	public function test_exposes_icons_with_label_and_keywords(): void {
		$catalog = new IconCatalog( new Config( [ 'icons' => [ 'award' => [ 'Distinción', 'calidad certificación' ] ] ] ) );

		$this->assertSame( [ 'award' ], $catalog->keys() );
		$this->assertTrue( $catalog->has( 'award' ) );
		$this->assertFalse( $catalog->has( 'skull' ) );
		$this->assertSame(
			[
				[
					'key'      => 'award',
					'label'    => 'Distinción',
					'keywords' => 'calidad certificación',
				],
			],
			$catalog->all()
		);
	}

	/**
	 * Un ícono con un nombre equivocado se vería en blanco: cada clave debe existir en el CSS compilado
	 * de Font Awesome (assets/dist se versiona, así esta prueba no necesita node_modules). Font Awesome
	 * puede quedar en el CSS de una entrada o en el que comparten varias, por eso se revisa todo el CSS.
	 */
	public function test_every_configured_icon_exists_in_font_awesome(): void {
		$catalog = new IconCatalog( Config::from_directory( $this->plugin_dir() . 'config' ) );
		$files   = (array) glob( $this->plugin_dir() . 'assets/dist/css/*.css' );

		if ( [] === $files ) {
			$this->markTestSkipped( 'El CSS compilado lo genera el Frontend (H-004).' );
		}

		$css = implode( "\n", array_map( static fn( string $file ): string => (string) file_get_contents( $file ), $files ) );

		$this->assertGreaterThan( 50, count( $catalog->keys() ) );

		foreach ( $catalog->keys() as $key ) {
			$this->assertMatchesRegularExpression( '/\.fa-' . preg_quote( $key, '/' ) . '[{,:]/', $css, "Ícono inexistente en Font Awesome: {$key}" );
		}
	}

	public function test_default_event_types_use_configured_icons(): void {
		$catalog = new IconCatalog( Config::from_directory( $this->plugin_dir() . 'config' ) );

		foreach ( [ 'cake-candles', 'graduation-cap', 'star', 'briefcase' ] as $icon ) {
			$this->assertTrue( $catalog->has( $icon ), $icon );
		}
	}
}
