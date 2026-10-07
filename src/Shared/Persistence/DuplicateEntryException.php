<?php
/**
 * Violación de un índice único.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Persistence;

/**
 * La base de datos rechazó un valor repetido en un índice UNIQUE. Ocurre cuando dos usuarios guardan
 * a la vez el mismo valor; los servicios lo convierten en un error de validación del campo afectado.
 */
final class DuplicateEntryException extends PersistenceException {}
