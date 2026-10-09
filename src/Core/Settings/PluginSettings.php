<?php
/**
 * Ajustes del plugin.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core\Settings;

/**
 * Ajustes que se guardan en opciones de WordPress (D-16):
 * - `delete_data_on_uninstall`: si desinstalar borra las tablas de eventos y tipos. Falso por defecto:
 *   desinstalar conserva los datos salvo que el gestor marque la casilla en «Ajustes».
 */
final class PluginSettings {

	public const DELETE_DATA_OPTION = 'eventos_delete_data_on_uninstall';

	/**
	 * Indica si desinstalar debe borrar todos los datos.
	 */
	public function delete_data_on_uninstall(): bool {
		return '1' === (string) get_option( self::DELETE_DATA_OPTION, '0' );
	}

	/**
	 * Guarda si desinstalar debe borrar todos los datos.
	 *
	 * @param bool $delete Borrar al desinstalar.
	 */
	public function set_delete_data_on_uninstall( bool $delete ): void {
		update_option( self::DELETE_DATA_OPTION, $delete ? '1' : '0', false );
	}

	/**
	 * Ajustes para la API (`GET /settings`).
	 *
	 * @return array{delete_data_on_uninstall: bool}
	 */
	public function to_array(): array {
		return [ 'delete_data_on_uninstall' => $this->delete_data_on_uninstall() ];
	}

	/**
	 * Borra los ajustes (al desinstalar con los datos).
	 */
	public function forget(): void {
		delete_option( self::DELETE_DATA_OPTION );
	}
}
