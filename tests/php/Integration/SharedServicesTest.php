<?php
/**
 * Pruebas de los servicios compartidos dentro de WordPress (casos CP-1.1 y CP-1.5).
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Integration;

use Probolsas\Eventos\Core\Plugin;
use Probolsas\Eventos\Shared\Text\Slugger;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use WP_UnitTestCase;

/**
 * Comprueba que el contenedor entrega los servicios con la configuración real de config/ui.php
 * y que el formato de fechas no depende de la configuración regional del sitio.
 *
 * @coversNothing
 */
final class SharedServicesTest extends WP_UnitTestCase {

	public function test_dates_use_colombian_time_regardless_of_site_settings(): void {
		update_option( 'timezone_string', 'Europe/Madrid' );
		switch_to_locale( 'en_US' );

		try {
			$formatter = $this->service( DateFormatter::class );

			$this->assertSame( '01/10/2026 11:30 p. m.', $formatter->format_datetime( '2026-10-02 04:30:00' ) );
			$this->assertSame( '2026-10-01T23:30:00-05:00', $formatter->to_iso( '2026-10-02 04:30:00' ) );
		} finally {
			restore_previous_locale();
		}
	}

	public function test_slugger_is_available(): void {
		$this->assertSame( 'reuniones-especiales', $this->service( Slugger::class )->slugify( 'Reuniones Especiales' ) );
	}

	/**
	 * Servicio del contenedor del plugin.
	 *
	 * @template T of object
	 *
	 * @param class-string<T> $id Clase del servicio.
	 *
	 * @return T
	 */
	private function service( string $id ): object {
		$plugin = Plugin::instance();
		$this->assertNotNull( $plugin );

		return $plugin->container()->get( $id );
	}
}
