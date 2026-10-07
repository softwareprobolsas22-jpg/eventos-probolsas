<?php
/**
 * Recurso inexistente.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Errors;

use RuntimeException;

/**
 * El recurso solicitado no existe. La API responde 404 con el código `eventos_not_found`.
 * El mensaje se muestra al usuario, por eso debe estar en español y ser claro.
 */
final class NotFoundException extends RuntimeException {}
