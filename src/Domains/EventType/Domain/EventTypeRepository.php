<?php
/**
 * Contrato de persistencia de los tipos de evento.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\EventType\Domain;

use Probolsas\Eventos\Shared\Persistence\DuplicateEntryException;

/**
 * Persistencia de tipos de evento. La implementación de producción usa $wpdb; las pruebas usan una en memoria.
 */
interface EventTypeRepository {

	/**
	 * Tipo por ID.
	 *
	 * @param int $id ID.
	 */
	public function find( int $id ): ?EventType;

	/**
	 * Todos los tipos con su conteo de eventos, ordenados por `sort_order` y luego por nombre.
	 *
	 * @return list<EventTypeSummary>
	 */
	public function all_with_event_counts(): array;

	/**
	 * Indica si hay tipos guardados.
	 */
	public function is_empty(): bool;

	/**
	 * Indica si un nombre normalizado ya está en uso.
	 *
	 * @param string   $name_key  Nombre normalizado.
	 * @param int|null $except_id ID a excluir.
	 */
	public function name_key_exists( string $name_key, ?int $except_id = null ): bool;

	/**
	 * Indica si un slug ya está en uso.
	 *
	 * @param string $slug Slug.
	 */
	public function slug_exists( string $slug ): bool;

	/**
	 * Eventos que usan un tipo.
	 *
	 * @param int $id ID del tipo.
	 */
	public function count_events( int $id ): int;

	/**
	 * Guarda un tipo nuevo y lo devuelve con su ID.
	 *
	 * @param EventType $type Tipo sin ID.
	 *
	 * @throws DuplicateEntryException Si el nombre o el slug ya existen.
	 */
	public function insert( EventType $type ): EventType;

	/**
	 * Guarda los cambios de un tipo existente.
	 *
	 * @param EventType $type Tipo con ID.
	 *
	 * @throws DuplicateEntryException Si el nombre ya existe.
	 */
	public function update( EventType $type ): void;

	/**
	 * Elimina un tipo.
	 *
	 * @param int $id ID.
	 */
	public function delete( int $id ): void;

	/**
	 * Posición para un tipo nuevo: después del último.
	 */
	public function next_sort_order(): int;

	/**
	 * Guarda el orden de todos los tipos.
	 *
	 * @param array<int, int> $positions Posición (desde 1) por ID.
	 */
	public function reorder( array $positions ): void;
}
