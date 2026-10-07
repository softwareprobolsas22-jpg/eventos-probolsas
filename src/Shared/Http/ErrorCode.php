<?php
/**
 * Códigos de error de la API.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Http;

/**
 * Fuente única de verdad de los códigos de error propios del plugin y su estado HTTP.
 * Están documentados en docs/api/README.md.
 */
enum ErrorCode: string {

	case ValidationFailed = 'eventos_validation_failed';
	case NotFound         = 'eventos_not_found';
	case Conflict         = 'eventos_conflict';
	case ServerError      = 'eventos_server_error';

	/**
	 * Estado HTTP asociado.
	 */
	public function status(): int {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts -- Falso positivo: PHPCompatibility 9 no reconoce los enums de PHP 8.1.
		return match ( $this ) {
			self::ValidationFailed => 422,
			self::NotFound         => 404,
			self::Conflict         => 409,
			self::ServerError      => 500,
		};
	}
}
