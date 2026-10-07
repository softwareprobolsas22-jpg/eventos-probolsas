<?php
/**
 * Pruebas de consistencia de la versión del plugin.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Core;

use Probolsas\Eventos\Core\Plugin;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * La versión aparece en tres lugares por exigencia de WordPress y npm; esta prueba evita que diverjan.
 *
 * @coversNothing
 */
final class PluginVersionTest extends UnitTestCase {

	public function test_plugin_header_matches_version_constant(): void {
		$header = (string) file_get_contents( $this->plugin_dir() . 'eventos-probolsas.php' );

		$this->assertMatchesRegularExpression( '/^\s*\*\s*Version:\s*' . preg_quote( Plugin::VERSION, '/' ) . '\s*$/m', $header );
	}

	public function test_plugin_header_requires_the_production_php_version(): void {
		$header   = (string) file_get_contents( $this->plugin_dir() . 'eventos-probolsas.php' );
		$composer = json_decode( (string) file_get_contents( $this->plugin_dir() . 'composer.json' ), true );

		$this->assertMatchesRegularExpression( '/^\s*\*\s*Requires PHP:\s*8\.3\s*$/m', $header );
		$this->assertIsArray( $composer );
		$this->assertSame( '>=8.3', $composer['require']['php'] );
	}

	public function test_package_json_matches_version_constant(): void {
		$file = $this->plugin_dir() . 'package.json';

		if ( ! file_exists( $file ) ) {
			$this->markTestSkipped( 'package.json lo crea el Frontend (H-004).' );
		}

		$package = json_decode( (string) file_get_contents( $file ), true );

		$this->assertIsArray( $package );
		$this->assertSame( Plugin::VERSION, $package['version'] );
	}
}
