<?php
/**
 * Reglas de validación de un campo.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Validation;

/**
 * Reglas encadenables de un campo. Se detiene en el primer error del campo, para mostrar un solo mensaje claro.
 * Las reglas distintas de `required()` se omiten si el campo está vacío (así un campo opcional vacío es válido).
 */
final class FieldRules {

	/**
	 * Indica si el campo ya tiene un error.
	 *
	 * @var bool
	 */
	private bool $failed = false;

	/**
	 * Crea las reglas.
	 *
	 * @param Validator $validator Validador que acumula los errores.
	 * @param string    $field     Nombre del campo.
	 * @param string    $label     Nombre visible del campo.
	 * @param mixed     $value     Valor recibido.
	 */
	public function __construct(
		private readonly Validator $validator,
		private readonly string $field,
		private readonly string $label,
		private readonly mixed $value
	) {}

	/**
	 * El campo no puede estar vacío ni contener solo espacios.
	 */
	public function required(): self {
		if ( ! $this->failed && $this->is_empty() ) {
			/* translators: %s: nombre del campo. */
			$this->fail( sprintf( __( 'El campo «%s» es obligatorio.', 'eventos-probolsas' ), $this->label ) );
		}

		return $this;
	}

	/**
	 * Longitud máxima en caracteres (no en bytes).
	 *
	 * @param int $max Caracteres permitidos.
	 */
	public function max_length( int $max ): self {
		if ( $this->should_check() && mb_strlen( $this->text() ) > $max ) {
			/* translators: 1: nombre del campo, 2: número máximo de caracteres. */
			$this->fail( sprintf( __( 'El campo «%1$s» admite máximo %2$d caracteres.', 'eventos-probolsas' ), $this->label, $max ) );
		}

		return $this;
	}

	/**
	 * Longitud mínima en caracteres (no en bytes), sin contar los espacios de los extremos.
	 *
	 * @param int $min Caracteres requeridos.
	 */
	public function min_length( int $min ): self {
		if ( $this->should_check() && mb_strlen( $this->text() ) < $min ) {
			/* translators: 1: nombre del campo, 2: número mínimo de caracteres. */
			$this->fail( sprintf( __( 'El campo «%1$s» debe tener al menos %2$d caracteres.', 'eventos-probolsas' ), $this->label, $min ) );
		}

		return $this;
	}

	/**
	 * Fecha de calendario real con el formato `Y-m-d` (rechaza, por ejemplo, el 30 de febrero).
	 */
	public function calendar_date(): self {
		return $this->rule(
			fn(): bool => $this->matches_format( 'Y-m-d' ),
			/* translators: %s: nombre del campo. */
			sprintf( __( 'El campo «%s» debe ser una fecha válida.', 'eventos-probolsas' ), $this->label )
		);
	}

	/**
	 * Hora de calendario con el formato `H:i` o `H:i:s` (24 h, como la envía el campo de hora del navegador).
	 */
	public function calendar_time(): self {
		return $this->rule(
			fn(): bool => $this->matches_format( 'H:i' ) || $this->matches_format( 'H:i:s' ),
			/* translators: %s: nombre del campo. */
			sprintf( __( 'El campo «%s» debe ser una hora válida.', 'eventos-probolsas' ), $this->label )
		);
	}

	/**
	 * Color hexadecimal de 6 dígitos, por ejemplo `#155728`.
	 */
	public function hex_color(): self {
		return $this->rule(
			fn(): bool => 1 === preg_match( '/^#[0-9a-fA-F]{6}$/', $this->text() ),
			/* translators: %s: nombre del campo. */
			sprintf( __( 'El campo «%s» debe ser un color hexadecimal, por ejemplo #155728.', 'eventos-probolsas' ), $this->label )
		);
	}

	/**
	 * URL absoluta con http o https.
	 */
	public function url(): self {
		return $this->rule(
			function (): bool {
				$url = $this->text();
				return false !== filter_var( $url, FILTER_VALIDATE_URL ) && 1 === preg_match( '#^https?://#i', $url );
			},
			/* translators: %s: nombre del campo. */
			sprintf( __( 'El campo «%s» debe ser una dirección web válida (http o https).', 'eventos-probolsas' ), $this->label )
		);
	}

	/**
	 * Número entero dentro de un rango.
	 *
	 * @param int $min Valor mínimo permitido.
	 * @param int $max Valor máximo permitido.
	 */
	public function integer( int $min = PHP_INT_MIN, int $max = PHP_INT_MAX ): self {
		$options = [
			'min_range' => $min,
			'max_range' => $max,
		];
		$message = PHP_INT_MIN === $min || PHP_INT_MAX === $max
			/* translators: %s: nombre del campo. */
			? sprintf( __( 'El campo «%s» debe ser un número válido.', 'eventos-probolsas' ), $this->label )
			/* translators: 1: nombre del campo, 2: valor mínimo, 3: valor máximo. */
			: sprintf( __( 'El campo «%1$s» debe ser un número entre %2$d y %3$d.', 'eventos-probolsas' ), $this->label, $min, $max );

		return $this->rule(
			fn(): bool => false !== filter_var( $this->text(), FILTER_VALIDATE_INT, [ 'options' => $options ] ),
			$message
		);
	}

	/**
	 * El valor debe estar en una lista de opciones.
	 *
	 * @param list<string|int> $allowed Valores permitidos.
	 */
	public function one_of( array $allowed ): self {
		return $this->rule(
			fn(): bool => in_array( $this->text(), array_map( 'strval', $allowed ), true ),
			/* translators: %s: nombre del campo. */
			sprintf( __( 'Selecciona una opción válida en el campo «%s».', 'eventos-probolsas' ), $this->label )
		);
	}

	/**
	 * Regla personalizada.
	 *
	 * @param callable(): bool $passes  Devuelve true si el valor es válido.
	 * @param string           $message Mensaje si no es válido.
	 */
	public function rule( callable $passes, string $message ): self {
		if ( $this->should_check() && ! $passes() ) {
			$this->fail( $message );
		}

		return $this;
	}

	/**
	 * Valor del campo como texto, sin espacios en los extremos.
	 */
	public function text(): string {
		return is_scalar( $this->value ) ? trim( (string) $this->value ) : '';
	}

	/**
	 * Indica si el valor cumple exactamente un formato de fecha u hora y es un valor real.
	 *
	 * @param string $format Formato con la sintaxis de PHP.
	 */
	private function matches_format( string $format ): bool {
		$value = $this->text();
		$date  = \DateTimeImmutable::createFromFormat( '!' . $format, $value, new \DateTimeZone( 'UTC' ) );

		return false !== $date && $date->format( $format ) === $value;
	}

	/**
	 * Indica si se deben evaluar las reglas: el campo no falló antes y tiene un valor.
	 */
	private function should_check(): bool {
		return ! $this->failed && ! $this->is_empty();
	}

	/**
	 * Indica si el campo está vacío.
	 */
	private function is_empty(): bool {
		if ( is_array( $this->value ) ) {
			return [] === $this->value;
		}

		return '' === $this->text();
	}

	/**
	 * Registra el error y detiene las demás reglas del campo.
	 *
	 * @param string $message Mensaje para el usuario.
	 */
	private function fail( string $message ): void {
		$this->failed = true;
		$this->validator->add_error( $this->field, $message );
	}
}
