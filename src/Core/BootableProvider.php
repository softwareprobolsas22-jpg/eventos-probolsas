<?php
/**
 * Contrato de arranque de un módulo.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core;

/**
 * Proveedor que necesita conectarse con WordPress (hooks) cuando el plugin arranca en `plugins_loaded`.
 */
interface BootableProvider extends ServiceProvider {

	/**
	 * Conecta los servicios del módulo con WordPress.
	 *
	 * @param Container $container Contenedor del plugin, con todos los proveedores ya registrados.
	 */
	public function boot( Container $container ): void;
}
