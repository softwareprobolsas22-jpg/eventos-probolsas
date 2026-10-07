<?php
/**
 * Base de los repositorios sobre $wpdb.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Persistence;

use wpdb;

/**
 * Operaciones comunes sobre una tabla propia del plugin. Todas las consultas usan prepare() con el
 * marcador %i para los identificadores (WordPress 6.2+): ningún valor se interpola en el SQL.
 *
 * Cada dominio extiende esta clase en su capa Infrastructure e implementa la interfaz de su repositorio.
 * Las consultas copian `$this->wpdb` a una variable local `$wpdb` porque así lo exige el sniff de WPCS
 * que verifica el uso de prepare().
 */
abstract class WpdbRepository {

	/**
	 * Crea el repositorio.
	 *
	 * @param wpdb   $wpdb  Conexión de WordPress.
	 * @param string $table Nombre completo de la tabla.
	 */
	public function __construct(
		protected readonly wpdb $wpdb,
		protected readonly string $table
	) {}

	/**
	 * Fila por ID.
	 *
	 * @param int $id ID.
	 *
	 * @return array<string, mixed>|null
	 */
	protected function find_row( int $id ): ?array {
		$wpdb = $this->wpdb;
		$row  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Indica si existe una fila con un valor en una columna, opcionalmente excluyendo un ID.
	 *
	 * @param string   $column    Columna.
	 * @param string   $value     Valor buscado.
	 * @param int|null $except_id ID a excluir (la propia fila al editar).
	 */
	protected function exists_where( string $column, string $value, ?int $except_id = null ): bool {
		$wpdb  = $this->wpdb;
		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM %i WHERE %i = %s AND id <> %d LIMIT 1',
				$this->table,
				$column,
				$value,
				$except_id ?? 0
			)
		);

		return null !== $found;
	}

	/**
	 * Inserta una fila y devuelve su ID.
	 *
	 * @param array<string, scalar|null> $data Columnas y valores.
	 *
	 * @throws PersistenceException Si la base de datos rechaza la operación.
	 */
	protected function insert_row( array $data ): int {
		if ( false === $this->wpdb->insert( $this->table, $data ) ) {
			$this->fail();
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Actualiza una fila.
	 *
	 * @param int                        $id   ID.
	 * @param array<string, scalar|null> $data Columnas y valores.
	 *
	 * @throws PersistenceException Si la base de datos rechaza la operación.
	 */
	protected function update_row( int $id, array $data ): void {
		if ( false === $this->wpdb->update( $this->table, $data, [ 'id' => $id ] ) ) {
			$this->fail();
		}
	}

	/**
	 * Elimina una fila.
	 *
	 * @param int $id ID.
	 *
	 * @throws PersistenceException Si la base de datos rechaza la operación.
	 */
	protected function delete_row( int $id ): void {
		if ( false === $this->wpdb->delete( $this->table, [ 'id' => $id ] ) ) {
			$this->fail();
		}
	}

	/**
	 * Posición para un registro nuevo: después del último (columna `sort_order`).
	 */
	protected function next_sort_order_value(): int {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM %i', $this->table ) );
	}

	/**
	 * Guarda las posiciones de varios registros (columna `sort_order`) con una sola sentencia
	 * `UPDATE … CASE`: se guardan todas o ninguna, sin abrir una transacción propia.
	 *
	 * @param array<int, int> $positions Posición por ID.
	 *
	 * @throws PersistenceException Si la base de datos rechaza la operación.
	 */
	protected function write_sort_orders( array $positions ): void {
		if ( [] === $positions ) {
			return;
		}

		$wpdb  = $this->wpdb;
		$cases = implode(
			' ',
			array_map(
				static fn( int $id, int $position ): string => $wpdb->prepare( 'WHEN %d THEN %d', $id, $position ),
				array_keys( $positions ),
				array_values( $positions )
			)
		);
		$ids   = implode( ',', array_map( 'intval', array_keys( $positions ) ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $cases se arma con prepare() y $ids son enteros.
		$result = $wpdb->query( $wpdb->prepare( "UPDATE %i SET sort_order = CASE id {$cases} END WHERE id IN ({$ids})", $this->table ) );

		if ( false === $result ) {
			$this->fail();
		}
	}

	/**
	 * Convierte el último error de la base de datos en una excepción.
	 *
	 * @throws DuplicateEntryException Si se violó un índice único.
	 * @throws PersistenceException    En cualquier otro caso.
	 */
	private function fail(): never {
		$detail = (string) $this->wpdb->last_error;

		if ( str_contains( $detail, 'Duplicate entry' ) ) {
			throw new DuplicateEntryException( esc_html__( 'Ya existe un registro con esos datos.', 'eventos-probolsas' ), esc_html( $detail ) );
		}

		throw new PersistenceException( esc_html__( 'No se pudo guardar la información. Inténtalo de nuevo.', 'eventos-probolsas' ), esc_html( $detail ) );
	}
}
