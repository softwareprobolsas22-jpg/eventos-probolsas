<?php
/**
 * Validación de datos de entrada.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Validation;

/**
 * Valida un conjunto de datos y acumula **todos** los errores, uno por campo.
 *
 * Uso:
 *     $validator = new Validator( $input );
 *     $validator->field( 'name', __( 'Nombre', 'eventos-probolsas' ) )->required()->max_length( 150 );
 *     $validator->field( 'badge_color', __( 'Color', 'eventos-probolsas' ) )->required()->hex_color();
 *     $validator->validate(); // Lanza ValidationException si hay errores.
 */
final class Validator {

	/**
	 * Mensajes de error por campo.
	 *
	 * @var array<string, list<string>>
	 */
	private array $errors = [];

	/**
	 * Crea el validador.
	 *
	 * @param array<string, mixed> $input Datos a validar.
	 */
	public function __construct( private readonly array $input ) {}

	/**
	 * Inicia las reglas de un campo.
	 *
	 * @param string $name  Nombre del campo en los datos.
	 * @param string $label Nombre visible del campo, usado en los mensajes.
	 */
	public function field( string $name, string $label ): FieldRules {
		return new FieldRules( $this, $name, $label, $this->input[ $name ] ?? null );
	}

	/**
	 * Registra un error en un campo.
	 *
	 * @param string $field   Nombre del campo.
	 * @param string $message Mensaje para el usuario.
	 */
	public function add_error( string $field, string $message ): void {
		$this->errors[ $field ][] = $message;
	}

	/**
	 * Indica si un campo tiene errores.
	 *
	 * @param string $field Nombre del campo.
	 */
	public function has_error( string $field ): bool {
		return isset( $this->errors[ $field ] );
	}

	/**
	 * Mensajes de error por campo.
	 *
	 * @return array<string, list<string>>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Lanza la excepción de validación si hay errores.
	 *
	 * @throws ValidationException Si algún campo no es válido.
	 */
	public function validate(): void {
		if ( [] !== $this->errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Los errores se entregan como JSON por la API, no se imprimen en HTML.
			throw new ValidationException( $this->errors );
		}
	}
}
