<?php
/**
 * Orden manual de una lista (por ejemplo, los tipos de evento).
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Ordering;

use Probolsas\Eventos\Shared\Validation\ValidationException;

/**
 * Convierte el orden que el usuario eligió (los IDs en el orden deseado) en posiciones 1, 2, 3…, sin
 * repetidos. Así nunca hay dos registros con la misma posición.
 */
final class Reordering {

	/**
	 * Posición de cada registro.
	 *
	 * @param mixed  $ids      IDs recibidos, en el orden deseado.
	 * @param int[]  $existing IDs que existen hoy.
	 * @param string $message  Mensaje si la lista no corresponde exactamente a los registros existentes.
	 * @phpstan-param list<int> $existing
	 *
	 * @return array<int, int> Posición (desde 1) por ID.
	 *
	 * @throws ValidationException Si falta algún registro, sobra uno, se repite o no es un ID válido.
	 */
	public static function positions( mixed $ids, array $existing, string $message ): array {
		$list = is_array( $ids ) ? array_values( $ids ) : [];
		$ints = array_map( static fn( mixed $id ): int => is_numeric( $id ) ? (int) $id : 0, $list );

		$sorted_received = $ints;
		$sorted_existing = $existing;
		sort( $sorted_received );
		sort( $sorted_existing );

		if ( [] === $ints || $sorted_received !== $sorted_existing ) {
			throw new ValidationException( [ 'ids' => [ $message ] ], $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Se entrega como JSON; la interfaz lo muestra como texto.
		}

		$positions = [];
		foreach ( $ints as $index => $id ) {
			$positions[ $id ] = $index + 1;
		}

		return $positions;
	}
}
