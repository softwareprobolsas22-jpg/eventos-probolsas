<?php
/**
 * Contrato de una migración de esquema.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core\Database;

/**
 * Cambio versionado del esquema de base de datos. Una vez publicada, una migración no se modifica:
 * los cambios posteriores se hacen con una migración nueva de versión mayor.
 */
interface Migration {

	/**
	 * Versión del esquema que deja aplicada esta migración (entero creciente y único).
	 */
	public function version(): int;

	/**
	 * Aplica el cambio. Debe ser idempotente.
	 */
	public function up(): void;
}
