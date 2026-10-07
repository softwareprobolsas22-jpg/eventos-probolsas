<?php
/**
 * Pruebas de la configuración.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Core;

use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * @covers \Probolsas\Eventos\Core\Config
 */
final class ConfigTest extends UnitTestCase {

	public function test_get_reads_nested_values_with_dot_notation(): void {
		$config = new Config( [ 'ui' => [ 'table' => [ 'size' => 25 ] ] ] );

		$this->assertSame( 25, $config->get( 'ui.table.size' ) );
		$this->assertSame( [ 'size' => 25 ], $config->get( 'ui.table' ) );
	}

	public function test_get_returns_fallback_for_missing_keys(): void {
		$config = new Config( [ 'ui' => [ 'locale' => 'es-CO' ] ] );

		$this->assertNull( $config->get( 'ui.missing' ) );
		$this->assertSame( 'x', $config->get( 'ui.locale.deeper', 'x' ) );
		$this->assertSame( [], $config->get( 'other', [] ) );
	}

	/**
	 * Verifica los valores SSOT acordados con el PO (zona horaria, hora en 12 h, paginación y separador del CSV).
	 */
	public function test_ui_config_file_holds_agreed_values(): void {
		$config = Config::from_directory( $this->plugin_dir() . 'config' );

		$this->assertSame( 'America/Bogota', $config->get( 'ui.timezone' ) );
		$this->assertSame( 'es-CO', $config->get( 'ui.locale' ) );
		$this->assertSame( 'h:i', $config->get( 'ui.time_format' ), 'Hora en 12 h (D-5).' );
		$this->assertSame(
			[
				'am' => 'a. m.',
				'pm' => 'p. m.',
			],
			$config->get( 'ui.meridiem' )
		);
		$this->assertSame( [ 25, 50, 100 ], $config->get( 'ui.page_sizes' ), 'Selector de registros por página (R-23).' );
		$this->assertSame( 25, $config->get( 'ui.default_page_size' ) );
		$this->assertContains( $config->get( 'ui.default_page_size' ), $config->get( 'ui.page_sizes' ) );
		$this->assertSame( ';', $config->get( 'ui.csv_separator' ), 'Excel en es-CO separa las columnas con «;».' );
	}

	/**
	 * Decisión del PO: los eventos solo admiten imágenes o PDF, elegidos en la Biblioteca de Medios (R-09).
	 */
	public function test_media_accepts_only_images_and_pdf(): void {
		$config = Config::from_directory( $this->plugin_dir() . 'config' );

		$this->assertSame( [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf' ], $config->get( 'media.allowed_mimes' ) );
		$this->assertSame( [ 'image', 'application/pdf' ], $config->get( 'media.library_types' ) );
	}

	public function test_from_directory_tolerates_missing_directory(): void {
		$config = Config::from_directory( $this->plugin_dir() . 'no-existe' );

		$this->assertNull( $config->get( 'ui' ) );
	}
}
