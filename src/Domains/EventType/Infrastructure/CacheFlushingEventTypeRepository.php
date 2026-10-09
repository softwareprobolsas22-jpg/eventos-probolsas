<?php
/**
 * Repositorio de tipos que invalida la caché al escribir.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\EventType\Infrastructure;

use Probolsas\Eventos\Domains\EventType\Domain\EventType;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeRepository;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeSummary;
use Probolsas\Eventos\Shared\Cache\ResponseCache;

/**
 * Decorador (H-401): el feed del calendario y los próximos llevan el color, el ícono y el nombre del tipo;
 * al crear, editar, eliminar o reordenar tipos se invalidan las respuestas guardadas.
 */
final class CacheFlushingEventTypeRepository implements EventTypeRepository {

	/**
	 * Crea el decorador.
	 *
	 * @param EventTypeRepository $inner Repositorio real.
	 * @param ResponseCache       $cache Caché de respuestas.
	 */
	public function __construct(
		private readonly EventTypeRepository $inner,
		private readonly ResponseCache $cache
	) {}

	/**
	 * Tipo por ID.
	 *
	 * @param int $id ID.
	 */
	public function find( int $id ): ?EventType {
		return $this->inner->find( $id );
	}

	/**
	 * Tipos con su conteo de eventos.
	 *
	 * @return list<EventTypeSummary>
	 */
	public function all_with_event_counts(): array {
		return $this->inner->all_with_event_counts();
	}

	/**
	 * Indica si no hay tipos.
	 */
	public function is_empty(): bool {
		return $this->inner->is_empty();
	}

	/**
	 * Indica si un nombre normalizado ya está en uso.
	 *
	 * @param string   $name_key  Nombre normalizado.
	 * @param int|null $except_id ID a excluir.
	 */
	public function name_key_exists( string $name_key, ?int $except_id = null ): bool {
		return $this->inner->name_key_exists( $name_key, $except_id );
	}

	/**
	 * Indica si un slug ya está en uso.
	 *
	 * @param string $slug Slug.
	 */
	public function slug_exists( string $slug ): bool {
		return $this->inner->slug_exists( $slug );
	}

	/**
	 * Eventos que usan un tipo.
	 *
	 * @param int $id ID.
	 */
	public function count_events( int $id ): int {
		return $this->inner->count_events( $id );
	}

	/**
	 * Guarda un tipo nuevo e invalida la caché.
	 *
	 * @param EventType $type Tipo sin ID.
	 */
	public function insert( EventType $type ): EventType {
		$saved = $this->inner->insert( $type );
		$this->cache->flush();

		return $saved;
	}

	/**
	 * Guarda los cambios e invalida la caché.
	 *
	 * @param EventType $type Tipo con ID.
	 */
	public function update( EventType $type ): void {
		$this->inner->update( $type );
		$this->cache->flush();
	}

	/**
	 * Elimina e invalida la caché.
	 *
	 * @param int $id ID.
	 */
	public function delete( int $id ): void {
		$this->inner->delete( $id );
		$this->cache->flush();
	}

	/**
	 * Posición para un tipo nuevo.
	 */
	public function next_sort_order(): int {
		return $this->inner->next_sort_order();
	}

	/**
	 * Guarda el orden e invalida la caché.
	 *
	 * @param array<int, int> $positions Posición por ID.
	 */
	public function reorder( array $positions ): void {
		$this->inner->reorder( $positions );
		$this->cache->flush();
	}
}
