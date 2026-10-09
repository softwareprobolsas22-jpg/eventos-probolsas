<?php
/**
 * Repositorio de eventos en memoria para las pruebas unitarias.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Support;

use Probolsas\Eventos\Domains\Event\Domain\Event;
use Probolsas\Eventos\Domains\Event\Domain\EventPage;
use Probolsas\Eventos\Domains\Event\Domain\EventQuery;
use Probolsas\Eventos\Domains\Event\Domain\EventRepository;

/**
 * Se comporta como la tabla real en lo que usa el servicio: IDs incrementales, búsqueda por palabras sin
 * distinguir mayúsculas, filtro por tipo, rango por solapamiento y paginación. El orden es siempre por
 * fecha, hora e ID (el SQL de cada orden se prueba en WpdbEventRepositoryTest).
 */
final class InMemoryEventRepository implements EventRepository {

	/**
	 * Eventos guardados por ID.
	 *
	 * @var array<int, Event>
	 */
	public array $events = [];

	/**
	 * Búsquedas recibidas.
	 *
	 * @var list<EventQuery>
	 */
	public array $queries = [];

	/**
	 * Siguiente ID.
	 *
	 * @var int
	 */
	private int $next_id = 1;

	/**
	 * Evento por ID.
	 *
	 * @param int $id ID.
	 */
	public function find( int $id ): ?Event {
		return $this->events[ $id ] ?? null;
	}

	/**
	 * Búsqueda paginada.
	 *
	 * @param EventQuery $query Búsqueda.
	 */
	public function search( EventQuery $query ): EventPage {
		$this->queries[] = $query;

		$matches = array_values(
			array_filter(
				$this->sorted(),
				static function ( Event $event ) use ( $query ): bool {
					$text = mb_strtolower( $event->title . ' ' . $event->description );
					foreach ( $query->words as $word ) {
						if ( ! str_contains( $text, mb_strtolower( $word ) ) ) {
							return false;
						}
					}

					return ( null === $query->type_id || $event->type_id === $query->type_id )
						&& $event->schedule->overlaps( $query->date_from ?? '0000-01-01', $query->date_to ?? '9999-12-31' );
				}
			)
		);

		return new EventPage( array_slice( $matches, $query->offset(), $query->per_page ), count( $matches ), $query->per_page );
	}

	/**
	 * Eventos de un día.
	 *
	 * @param string $date Día.
	 *
	 * @return list<Event>
	 */
	public function on_date( string $date ): array {
		return array_values( array_filter( $this->sorted(), static fn( Event $event ): bool => $event->schedule->overlaps( $date, $date ) ) );
	}

	/**
	 * Eventos de un rango.
	 *
	 * @param string    $from     Primer día.
	 * @param string    $to       Último día.
	 * @param int[]     $type_ids Tipos (vacío = todos).
	 * @param int|null  $limit    Máximo.
	 * @phpstan-param list<int> $type_ids
	 *
	 * @return list<Event>
	 */
	public function in_range( string $from, string $to, array $type_ids = [], ?int $limit = null ): array {
		$matches = array_values(
			array_filter(
				$this->sorted(),
				static fn( Event $event ): bool => $event->schedule->overlaps( $from, $to ) && ( [] === $type_ids || in_array( $event->type_id, $type_ids, true ) )
			)
		);

		return null === $limit ? $matches : array_slice( $matches, 0, $limit );
	}

	/**
	 * Cantidad de eventos de un rango.
	 *
	 * @param string $from Primer día.
	 * @param string $to   Último día.
	 */
	public function count_in_range( string $from, string $to ): int {
		return count( $this->in_range( $from, $to ) );
	}

	/**
	 * Guarda un evento nuevo.
	 *
	 * @param Event $event Evento.
	 */
	public function insert( Event $event ): Event {
		$saved                            = $event->with_id( $this->next_id++ );
		$this->events[ (int) $saved->id ] = $saved;

		return $saved;
	}

	/**
	 * Guarda cambios.
	 *
	 * @param Event $event Evento.
	 */
	public function update( Event $event ): void {
		$this->events[ (int) $event->id ] = $event;
	}

	/**
	 * Elimina.
	 *
	 * @param int $id ID.
	 */
	public function delete( int $id ): void {
		unset( $this->events[ $id ] );
	}

	/**
	 * Eventos por fecha, hora (todo el día primero) e ID.
	 *
	 * @return list<Event>
	 */
	private function sorted(): array {
		$events = array_values( $this->events );
		usort(
			$events,
			static fn( Event $a, Event $b ): int => [ $a->schedule->start_date, (string) $a->schedule->start_time, $a->id ] <=> [ $b->schedule->start_date, (string) $b->schedule->start_time, $b->id ]
		);

		return $events;
	}
}
