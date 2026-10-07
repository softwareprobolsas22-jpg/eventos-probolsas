<?php
/**
 * Error de validación.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Validation;

use DomainException;

/**
 * Datos de entrada no válidos. Transporta los errores agrupados por campo para que la interfaz marque cada uno.
 */
final class ValidationException extends DomainException {

	/**
	 * Crea la excepción.
	 *
	 * @param array<string, list<string>> $errors  Mensajes por campo.
	 * @param string                      $message Mensaje general (se muestra en un toast).
	 */
	public function __construct( private readonly array $errors, string $message = '' ) {
		parent::__construct( '' === $message ? __( 'Revisa los campos marcados.', 'eventos-probolsas' ) : $message );
	}

	/**
	 * Mensajes por campo.
	 *
	 * @return array<string, list<string>>
	 */
	public function errors(): array {
		return $this->errors;
	}
}
