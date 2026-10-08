<?php
/**
 * Persistencia de eventos.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Domain;

/**
 * Contrato del repositorio de eventos. La implementación de producción usa $wpdb; las pruebas, memoria.
 */
interface EventRepository {

	/**
	 * Evento por ID.
	 *
	 * @param int $id ID.
	 */
	public function find( int $id ): ?Event;

	/**
	 * Eventos que cumplen una búsqueda, en la página pedida.
	 *
	 * @param EventQuery $query Búsqueda.
	 */
	public function search( EventQuery $query ): EventPage;

	/**
	 * Eventos que ocupan un día, ordenados por hora (los de todo el día primero) y luego por ID.
	 *
	 * @param string $date Día `Y-m-d`.
	 *
	 * @return list<Event>
	 */
	public function on_date( string $date ): array;

	/**
	 * Guarda un evento nuevo y lo devuelve con su ID.
	 *
	 * @param Event $event Evento sin ID.
	 */
	public function insert( Event $event ): Event;

	/**
	 * Guarda los cambios de un evento.
	 *
	 * @param Event $event Evento con ID.
	 */
	public function update( Event $event ): void;

	/**
	 * Elimina un evento. Nunca toca su adjunto de la Biblioteca de Medios (D-4).
	 *
	 * @param int $id ID.
	 */
	public function delete( int $id ): void;
}
