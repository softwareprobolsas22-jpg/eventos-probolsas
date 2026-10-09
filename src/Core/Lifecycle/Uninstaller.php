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
use Probolsas\Eventos\Core\Settings\PluginSettings;

/**
 * Al desinstalar siempre quita las capabilities del plugin. Los datos (tablas de eventos y tipos, la
 * versión del esquema, la limpieza propia de cada dominio y los ajustes) solo se borran si el gestor
 * marcó «Borrar todos los datos al desinstalar» en «Ajustes»; si no, se conservan para una reinstalación
 * (D-16). Los archivos se quedan siempre en la Biblioteca de Medios: los usan otras partes de la empresa
 * (D-4).
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
	 * @param PluginSettings  $settings     Ajustes (si se borran los datos).
	 * @param UninstallTask[] $tasks        Limpieza propia de los dominios.
	 * @phpstan-param list<UninstallTask> $tasks
	 */
	public function __construct(
		private readonly Migrator $migrator,
		private readonly Capabilities $capabilities,
		private readonly PluginSettings $settings,
		private readonly array $tasks = []
	) {}

	/**
	 * Desinstala: quita los permisos y, si así se configuró, los datos.
	 */
	public function uninstall(): void {
		$this->capabilities->uninstall();

		if ( ! $this->settings->delete_data_on_uninstall() ) {
			return;
		}

		foreach ( $this->tasks as $task ) {
			$task->run();
		}

		$this->migrator->reset();
		$this->settings->forget();
	}
}
