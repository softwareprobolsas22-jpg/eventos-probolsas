<?php
/**
 * Búsqueda de eventos.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Domain;

/**
 * Filtros, orden y página de la tabla de eventos. La usan el listado y la exportación CSV, así ambos
 * devuelven exactamente los mismos eventos (SSOT). La construye y valida EventService.
 */
final class EventQuery {

	public const ORDER_BY = [ 'start_date', 'title', 'type', 'created_at' ];

	/**
	 * Crea la consulta.
	 *
	 * @param string[]    $words     Palabras buscadas (en título o descripción, todas deben aparecer).
	 * @param int|null    $type_id   Tipo de evento.
	 * @param string|null $date_from Primer día del rango `Y-m-d` (por solapamiento).
	 * @param string|null $date_to   Último día del rango `Y-m-d`.
	 * @param string      $order_by  Columna de orden (una de ORDER_BY).
	 * @param bool        $ascending Orden ascendente.
	 * @param int         $page      Página (desde 1).
	 * @param int         $per_page  Registros por página.
	 *
	 * @phpstan-param list<string> $words
	 */
	public function __construct(
		public readonly array $words = [],
		public readonly ?int $type_id = null,
		public readonly ?string $date_from = null,
		public readonly ?string $date_to = null,
		public readonly string $order_by = 'start_date',
		public readonly bool $ascending = true,
		public readonly int $page = 1,
		public readonly int $per_page = 25
	) {}

	/**
	 * Registros que se saltan antes de la página.
	 */
	public function offset(): int {
		return ( $this->page - 1 ) * $this->per_page;
	}

	/**
	 * La misma consulta en otra página y con otro tamaño (la exportación recorre todas las páginas).
	 *
	 * @param int $page     Página.
	 * @param int $per_page Registros por página.
	 */
	public function with_page( int $page, int $per_page ): self {
		return new self( $this->words, $this->type_id, $this->date_from, $this->date_to, $this->order_by, $this->ascending, $page, $per_page );
	}
}
