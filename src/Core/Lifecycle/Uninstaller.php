<?php
/**
 * Desinstalación del plugin.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core\Lifecycle;

use Probolsas\Eventos\Core\Database\Migrator;
use Probolsas\Eventos\Core\Security\Capabilities;

/**
 * Elimina todo lo que el plugin creó: la limpieza propia de cada dominio, las tablas, las opciones y
 * las capabilities. Los archivos se conservan en la Biblioteca de medios: los usan otras partes de la
 * empresa, no datos internos del plugin.
 */
final class Uninstaller {

	/**
	 * Etiqueta del contenedor con la que los dominios aportan sus tareas de limpieza.
	 */
	public const TASKS_TAG = 'eventos.uninstall_tasks';

	/**
	 * Crea el desinstalador.
	 *
	 * @param Migrator        $migrator     Migrador del esquema.
	 * @param Capabilities    $capabilities Permisos del plugin.
	 * @param UninstallTask[] $tasks        Limpieza propia de los dominios.
	 * @phpstan-param list<UninstallTask> $tasks
	 */
	public function __construct(
		private readonly Migrator $migrator,
		private readonly Capabilities $capabilities,
		private readonly array $tasks = []
	) {}

	/**
	 * Elimina los datos del plugin.
	 */
	public function uninstall(): void {
		foreach ( $this->tasks as $task ) {
			$task->run();
		}

		$this->migrator->reset();
		$this->capabilities->uninstall();
	}
}
