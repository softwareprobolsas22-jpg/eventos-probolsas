<?php
/**
 * Repositorio de tipos de evento sobre $wpdb.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\EventType\Infrastructure;

use Probolsas\Eventos\Core\Database\Tables;
use Probolsas\Eventos\Domains\EventType\Domain\EventType;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeRepository;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeSummary;
use Probolsas\Eventos\Shared\Persistence\WpdbRepository;
use wpdb;

/**
 * Implementación de producción del repositorio de tipos de evento (tabla `eventos_event_types`).
 */
final class WpdbEventTypeRepository extends WpdbRepository implements EventTypeRepository {

	/**
	 * Tabla de eventos, para los conteos.
	 *
	 * @var string
	 */
	private string $events_table;

	/**
	 * Crea el repositorio.
	 *
	 * @param wpdb   $wpdb   Conexión de WordPress.
	 * @param Tables $tables Catálogo de tablas.
	 */
	public function __construct( wpdb $wpdb, Tables $tables ) {
		parent::__construct( $wpdb, $tables->name( Tables::EVENT_TYPES ) );
		$this->events_table = $tables->name( Tables::EVENTS );
	}

	/**
	 * Tipo por ID.
	 *
	 * @param int $id ID.
	 */
	public function find( int $id ): ?EventType {
		$row = $this->find_row( $id );

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Todos los tipos con su conteo de eventos.
	 *
	 * @return list<EventTypeSummary>
	 */
	public function all_with_event_counts(): array {
		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT t.*, (SELECT COUNT(*) FROM %i e WHERE e.type_id = t.id) AS events_count
				FROM %i t
				ORDER BY t.sort_order ASC, t.name ASC',
				$this->events_table,
				$this->table
			),
			ARRAY_A
		);

		return array_map(
			fn( array $row ): EventTypeSummary => new EventTypeSummary( $this->hydrate( $row ), (int) $row['events_count'] ),
			is_array( $rows ) ? $rows : []
		);
	}

	/**
	 * Indica si hay tipos guardados.
	 */
	public function is_empty(): bool {
		$wpdb = $this->wpdb;

		return null === $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i LIMIT 1', $this->table ) );
	}

	/**
	 * Indica si un nombre normalizado ya está en uso.
	 *
	 * @param string   $name_key  Nombre normalizado.
	 * @param int|null $except_id ID a excluir.
	 */
	public function name_key_exists( string $name_key, ?int $except_id = null ): bool {
		return $this->exists_where( 'name_key', $name_key, $except_id );
	}

	/**
	 * Indica si un slug ya está en uso.
	 *
	 * @param string $slug Slug.
	 */
	public function slug_exists( string $slug ): bool {
		return $this->exists_where( 'slug', $slug );
	}

	/**
	 * Eventos que usan un tipo.
	 *
	 * @param int $id ID del tipo.
	 */
	public function count_events( int $id ): int {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE type_id = %d', $this->events_table, $id ) );
	}

	/**
	 * Guarda un tipo nuevo.
	 *
	 * @param EventType $type Tipo sin ID.
	 */
	public function insert( EventType $type ): EventType {
		return $type->with_id( $this->insert_row( $this->dehydrate( $type ) ) );
	}

	/**
	 * Guarda los cambios de un tipo.
	 *
	 * @param EventType $type Tipo con ID.
	 */
	public function update( EventType $type ): void {
		$this->update_row( (int) $type->id, $this->dehydrate( $type ) );
	}

	/**
	 * Elimina un tipo.
	 *
	 * @param int $id ID.
	 */
	public function delete( int $id ): void {
		$this->delete_row( $id );
	}

	/**
	 * Posición para un tipo nuevo: después del último.
	 */
	public function next_sort_order(): int {
		return $this->next_sort_order_value();
	}

	/**
	 * Guarda el orden de todos los tipos.
	 *
	 * @param array<int, int> $positions Posición por ID.
	 */
	public function reorder( array $positions ): void {
		$this->write_sort_orders( $positions );
	}

	/**
	 * Fila → entidad.
	 *
	 * @param array<string, mixed> $row Fila de la base de datos.
	 */
	private function hydrate( array $row ): EventType {
		return new EventType(
			(int) $row['id'],
			(string) $row['name'],
			(string) $row['name_key'],
			(string) $row['slug'],
			(string) $row['color'],
			(string) $row['icon'],
			1 === (int) $row['requires_attachment'],
			(string) ( $row['description'] ?? '' ),
			(int) $row['sort_order'],
			(string) $row['created_at_gmt'],
			(string) $row['updated_at_gmt']
		);
	}

	/**
	 * Entidad → columnas.
	 *
	 * @param EventType $type Tipo.
	 *
	 * @return array<string, string|int>
	 */
	private function dehydrate( EventType $type ): array {
		return [
			'name'                => $type->name,
			'name_key'            => $type->name_key,
			'slug'                => $type->slug,
			'color'               => $type->color,
			'icon'                => $type->icon,
			'requires_attachment' => $type->requires_attachment ? 1 : 0,
			'description'         => $type->description,
			'sort_order'          => $type->sort_order,
			'created_at_gmt'      => $type->created_at_gmt,
			'updated_at_gmt'      => $type->updated_at_gmt,
		];
	}
}
