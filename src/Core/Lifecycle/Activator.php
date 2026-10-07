<?php
/**
 * Activación del plugin.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core\Lifecycle;

use Probolsas\Eventos\Core\Database\Migrator;
use Probolsas\Eventos\Core\Security\Capabilities;

/**
 * Prepara el esquema y los permisos. Es idempotente: puede ejecutarse varias veces sin efectos adicionales.
 *
 * La desactivación no tiene tareas propias: los datos se conservan hasta la desinstalación.
 */
final class Activator {

	/**
	 * Crea el activador.
	 *
	 * @param Migrator     $migrator     Migrador del esquema.
	 * @param Capabilities $capabilities Permisos del plugin.
	 */
	public function __construct(
		private readonly Migrator $migrator,
		private readonly Capabilities $capabilities
	) {}

	/**
	 * Aplica las migraciones pendientes y asigna los permisos.
	 */
	public function activate(): void {
		$this->migrator->migrate();
		$this->capabilities->install();
	}
}
