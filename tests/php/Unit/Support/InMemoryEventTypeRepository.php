<?php
/**
 * Repositorio de tipos de evento en memoria para las pruebas unitarias.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Support;

use Probolsas\Eventos\Domains\EventType\Domain\EventType;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeRepository;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeSummary;
use Probolsas\Eventos\Shared\Persistence\DuplicateEntryException;

/**
 * Se comporta como la tabla real: IDs incrementales, índices únicos de nombre normalizado y slug, y orden
 * por `sort_order` y nombre. Permite simular eventos asociados y escrituras simultáneas.
 */
final class InMemoryEventTypeRepository implements EventTypeRepository {

	/**
	 * Tipos guardados por ID.
	 *
	 * @var array<int, EventType>
	 */
	public array $types = [];

	/**
	 * Eventos asociados por ID de tipo.
	 *
	 * @var array<int, int>
	 */
	public array $events = [];

	/**
	 * Si es true, la próxima escritura falla como si otro usuario hubiera guardado el mismo nombre a la vez.
	 *
	 * @var bool
	 */
	public bool $fail_next_write_as_duplicate = false;

	/**
	 * Siguiente ID.
	 *
	 * @var int
	 */
	private int $next_id = 1;

	/**
	 * Tipo por ID.
	 *
	 * @param int $id ID.
	 */
	public function find( int $id ): ?EventType {
		return $this->types[ $id ] ?? null;
	}

	/**
	 * Todos los tipos con su conteo, en orden.
	 *
	 * @return list<EventTypeSummary>
	 */
	public function all_with_event_counts(): array {
		$types = array_values( $this->types );
		usort( $types, static fn( EventType $a, EventType $b ): int => [ $a->sort_order, $a->name ] <=> [ $b->sort_order, $b->name ] );

		return array_map( fn( EventType $type ): EventTypeSummary => new EventTypeSummary( $type, $this->count_events( (int) $type->id ) ), $types );
	}

	/**
	 * Indica si no hay tipos.
	 */
	public function is_empty(): bool {
		return [] === $this->types;
	}

	/**
	 * Indica si un nombre normalizado ya existe.
	 *
	 * @param string   $name_key  Nombre normalizado.
	 * @param int|null $except_id ID a excluir.
	 */
	public function name_key_exists( string $name_key, ?int $except_id = null ): bool {
		foreach ( $this->types as $id => $type ) {
			if ( $id !== $except_id && $type->name_key === $name_key ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Indica si un slug ya existe.
	 *
	 * @param string $slug Slug.
	 */
	public function slug_exists( string $slug ): bool {
		foreach ( $this->types as $type ) {
			if ( $type->slug === $slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Eventos asociados.
	 *
	 * @param int $id ID del tipo.
	 */
	public function count_events( int $id ): int {
		return $this->events[ $id ] ?? 0;
	}

	/**
	 * Guarda un tipo nuevo.
	 *
	 * @param EventType $type Tipo sin ID.
	 *
	 * @throws DuplicateEntryException Si se simula una escritura simultánea.
	 */
	public function insert( EventType $type ): EventType {
		$this->fail_if_requested();
		$saved                     = $type->with_id( $this->next_id++ );
		$this->types[ $saved->id ] = $saved;

		return $saved;
	}

	/**
	 * Guarda cambios.
	 *
	 * @param EventType $type Tipo con ID.
	 *
	 * @throws DuplicateEntryException Si se simula una escritura simultánea.
	 */
	public function update( EventType $type ): void {
		$this->fail_if_requested();
		$this->types[ (int) $type->id ] = $type;
	}

	/**
	 * Elimina un tipo.
	 *
	 * @param int $id ID.
	 */
	public function delete( int $id ): void {
		unset( $this->types[ $id ] );
	}

	/**
	 * Posición después del último.
	 */
	public function next_sort_order(): int {
		return 1 + max( [ 0, ...array_map( static fn( EventType $type ): int => $type->sort_order, array_values( $this->types ) ) ] );
	}

	/**
	 * Guarda el orden.
	 *
	 * @param array<int, int> $positions Posición por ID.
	 */
	public function reorder( array $positions ): void {
		foreach ( $positions as $id => $position ) {
			$this->types[ $id ] = $this->types[ $id ]->with_sort_order( $position );
		}
	}

	/**
	 * Lanza la excepción de duplicado si se pidió simularla.
	 *
	 * @throws DuplicateEntryException Si se pidió.
	 */
	private function fail_if_requested(): void {
		if ( $this->fail_next_write_as_duplicate ) {
			$this->fail_next_write_as_duplicate = false;
			throw new DuplicateEntryException( 'Duplicado', 'Duplicate entry' );
		}
	}
}
