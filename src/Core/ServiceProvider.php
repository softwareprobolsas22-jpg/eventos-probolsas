<?php
/**
 * Contrato de registro de servicios.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core;

/**
 * Cada módulo (núcleo o dominio) declara sus servicios en el contenedor mediante un proveedor.
 *
 * En `register()` solo se registran fábricas: no se construyen servicios ni se agregan hooks.
 */
interface ServiceProvider {

	/**
	 * Registra las fábricas y etiquetas del módulo en el contenedor.
	 *
	 * @param Container $container Contenedor del plugin.
	 */
	public function register( Container $container ): void;
}
