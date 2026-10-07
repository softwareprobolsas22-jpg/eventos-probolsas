<?php
/**
 * Repositorio de prueba para las pruebas unitarias de WpdbRepository.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Support;

use Probolsas\Eventos\Shared\Persistence\WpdbRepository;

/**
 * Expone las operaciones protegidas de WpdbRepository.
 */
final class FakeWpdbRepository extends WpdbRepository {

	/**
	 * Fila por ID.
	 *
	 * @param int $id ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		return $this->find_row( $id );
	}

	/**
	 * Existencia de un valor.
	 *
	 * @param string   $column    Columna.
	 * @param string   $value     Valor.
	 * @param int|null $except_id ID excluido.
	 */
	public function exists( string $column, string $value, ?int $except_id = null ): bool {
		return $this->exists_where( $column, $value, $except_id );
	}

	/**
	 * Inserción.
	 *
	 * @param array<string, scalar|null> $data Datos.
	 */
	public function insert( array $data ): int {
		return $this->insert_row( $data );
	}

	/**
	 * Actualización.
	 *
	 * @param int                        $id   ID.
	 * @param array<string, scalar|null> $data Datos.
	 */
	public function update( int $id, array $data ): void {
		$this->update_row( $id, $data );
	}

	/**
	 * Eliminación.
	 *
	 * @param int $id ID.
	 */
	public function delete( int $id ): void {
		$this->delete_row( $id );
	}

	/**
	 * Siguiente posición.
	 */
	public function next_position(): int {
		return $this->next_sort_order_value();
	}

	/**
	 * Guarda posiciones.
	 *
	 * @param array<int, int> $positions Posición por ID.
	 */
	public function reorder( array $positions ): void {
		$this->write_sort_orders( $positions );
	}
}
