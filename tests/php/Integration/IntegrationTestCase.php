<?php
/**
 * Clase base de las pruebas de integración.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Integration;

use Probolsas\Eventos\Core\Database\Migrator;
use Probolsas\Eventos\Core\Database\Tables;
use Probolsas\Eventos\Core\Plugin;
use Probolsas\Eventos\Core\Settings\PluginSettings;
use WP_UnitTestCase;

/**
 * Cada prueba parte de un plugin sin instalar: sin tablas, sin opción de versión y sin capabilities.
 *
 * La suite de WordPress convierte CREATE/DROP TABLE en tablas temporales y deshace cada prueba con un
 * ROLLBACK. Aquí se necesitan tablas reales, y el DDL hace COMMIT implícito, por eso se restaura el
 * estado de forma explícita antes y después de cada prueba.
 */
abstract class IntegrationTestCase extends WP_UnitTestCase {

	/**
	 * Deja el plugin sin instalar.
	 */
	public function set_up(): void {
		parent::set_up();

		remove_filter( 'query', [ $this, '_create_temporary_tables' ] );
		remove_filter( 'query', [ $this, '_drop_temporary_tables' ] );

		$this->reset_plugin();
	}

	/**
	 * Deja la base de datos limpia para las demás pruebas.
	 */
	public function tear_down(): void {
		$this->reset_plugin();

		parent::tear_down();
	}

	/**
	 * Plugin en ejecución.
	 */
	protected function plugin(): Plugin {
		$plugin = Plugin::instance();
		$this->assertNotNull( $plugin, 'El plugin no se cargó en el bootstrap.' );

		return $plugin;
	}

	/**
	 * Ruta del archivo principal del plugin.
	 */
	protected function plugin_file(): string {
		return dirname( __DIR__, 3 ) . '/eventos-probolsas.php';
	}

	/**
	 * Catálogo de tablas del plugin.
	 */
	protected function tables(): Tables {
		return $this->plugin()->container()->get( Tables::class );
	}

	/**
	 * Versión más alta del esquema que aplican las migraciones registradas.
	 */
	protected function latest_schema_version(): int {
		return $this->plugin()->container()->get( Migrator::class )->latest_version();
	}

	/**
	 * Indica si una tabla existe en la base de datos.
	 *
	 * @param string $table Nombre completo de la tabla.
	 */
	protected function table_exists( string $table ): bool {
		global $wpdb;

		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * Desinstala el plugin y confirma la transacción para que el ROLLBACK de la suite no lo revierta.
	 */
	private function reset_plugin(): void {
		// Desinstalar conserva los datos por defecto (D-16): para dejar la base limpia se pide borrarlos.
		( new PluginSettings() )->set_delete_data_on_uninstall( true );
		Plugin::uninstall( $this->plugin_file() );
		self::commit_transaction();
	}
}
