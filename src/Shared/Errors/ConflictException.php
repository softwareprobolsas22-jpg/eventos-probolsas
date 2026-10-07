<?php
/**
 * Conflicto con una regla de negocio.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Errors;

use RuntimeException;

/**
 * La operación viola una regla de negocio, por ejemplo eliminar un tipo de evento que tiene eventos.
 * La API responde 409 con el código `eventos_conflict`. El mensaje se muestra al usuario.
 */
final class ConflictException extends RuntimeException {}
