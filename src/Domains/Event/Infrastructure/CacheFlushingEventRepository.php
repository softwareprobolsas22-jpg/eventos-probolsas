<?php
/**
 * Repositorio de eventos que invalida la caché al escribir.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Infrastructure;

use Probolsas\Eventos\Domains\Event\Domain\Event;
use Probolsas\Eventos\Domains\Event\Domain\EventPage;
use Probolsas\Eventos\Domains\Event\Domain\EventQuery;
use Probolsas\Eventos\Domains\Event\Domain\EventRepository;
use Probolsas\Eventos\Shared\Cache\ResponseCache;

/**
 * Decorador (H-401): las lecturas pasan directo al repositorio real; cada escritura invalida el feed del
 * calendario y los próximos guardados en caché. Ninguna capa de negocio sabe que hay caché.
 */
final class CacheFlushingEventRepository implements EventRepository {

	/**
	 * Crea el decorador.
	 *
	 * @param EventRepository $inner Repositorio real.
	 * @param ResponseCache   $cache Caché de respuestas.
	 */
	public function __construct(
		private readonly EventRepository $inner,
		private readonly ResponseCache $cache
	) {}

	/**
	 * Evento por ID.
	 *
	 * @param int $id ID.
	 */
	public function find( int $id ): ?Event {
		return $this->inner->find( $id );
	}

	/**
	 * Búsqueda paginada.
	 *
	 * @param EventQuery $query Búsqueda.
	 */
	public function search( EventQuery $query ): EventPage {
		return $this->inner->search( $query );
	}

	/**
	 * Eventos de un día.
	 *
	 * @param string $date Día `Y-m-d`.
	 *
	 * @return list<Event>
	 */
	public function on_date( string $date ): array {
		return $this->inner->on_date( $date );
	}

	/**
	 * Eventos de un rango.
	 *
	 * @param string   $from     Primer día.
	 * @param string   $to       Último día.
	 * @param int[]    $type_ids Tipos (vacío = todos).
	 * @param int|null $limit    Máximo.
	 * @phpstan-param list<int> $type_ids
	 *
	 * @return list<Event>
	 */
	public function in_range( string $from, string $to, array $type_ids = [], ?int $limit = null ): array {
		return $this->inner->in_range( $from, $to, $type_ids, $limit );
	}

	/**
	 * Cantidad de eventos de un rango.
	 *
	 * @param string $from Primer día.
	 * @param string $to   Último día.
	 */
	public function count_in_range( string $from, string $to ): int {
		return $this->inner->count_in_range( $from, $to );
	}

	/**
	 * Guarda un evento nuevo e invalida la caché.
	 *
	 * @param Event $event Evento sin ID.
	 */
	public function insert( Event $event ): Event {
		$saved = $this->inner->insert( $event );
		$this->cache->flush();

		return $saved;
	}

	/**
	 * Guarda los cambios e invalida la caché.
	 *
	 * @param Event $event Evento con ID.
	 */
	public function update( Event $event ): void {
		$this->inner->update( $event );
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
}
