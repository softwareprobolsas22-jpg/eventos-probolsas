<?php
/**
 * Contrato de una tarea de limpieza al desinstalar.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core\Lifecycle;

/**
 * Limpieza propia de un dominio al desinstalar el plugin (por ejemplo, metadatos que el dominio dejó en
 * entradas de WordPress). Los dominios la registran con la etiqueta Uninstaller::TASKS_TAG; así el
 * núcleo no necesita conocer sus detalles.
 */
interface UninstallTask {

	/**
	 * Ejecuta la limpieza.
	 */
	public function run(): void;
}
