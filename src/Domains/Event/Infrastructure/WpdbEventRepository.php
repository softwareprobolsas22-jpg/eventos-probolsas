<?php
/**
 * Repositorio de eventos sobre $wpdb.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Infrastructure;

use Probolsas\Eventos\Core\Database\Tables;
use Probolsas\Eventos\Domains\Event\Domain\Event;
use Probolsas\Eventos\Domains\Event\Domain\EventPage;
use Probolsas\Eventos\Domains\Event\Domain\EventQuery;
use Probolsas\Eventos\Domains\Event\Domain\EventRepository;
use Probolsas\Eventos\Domains\Event\Domain\EventSchedule;
use Probolsas\Eventos\Shared\Persistence\WpdbRepository;
use wpdb;

/**
 * Implementación de producción del repositorio de eventos (tabla `eventos_events`).
 *
 * - Búsqueda en título y descripción con LIKE: el collation `*_ci` de la tabla ya ignora mayúsculas y
 *   tildes. Cada palabra debe aparecer (en cualquier orden), igual que la búsqueda de la interfaz.
 * - Rango por **solapamiento** (§5.5): `start_date <= hasta AND COALESCE(end_date, start_date) >= desde`.
 *   Así, cuando lleguen los eventos de varios días (D-7), aparecerán en cada día que ocupan.
 */
final class WpdbEventRepository extends WpdbRepository implements EventRepository {

	/**
	 * Orden SQL de cada columna de EventQuery::ORDER_BY; `{dir}` se reemplaza por ASC o DESC. Las
	 * columnas vienen de esta lista cerrada, nunca de la petición.
	 */
	private const ORDER_SQL = [
		'start_date' => 'e.start_date {dir}, e.start_time {dir}, e.id {dir}',
		'title'      => 'e.title {dir}, e.start_date ASC, e.id ASC',
		'type'       => 't.sort_order {dir}, t.name {dir}, e.start_date ASC, e.id ASC',
		'created_at' => 'e.created_at_gmt {dir}, e.id {dir}',
	];

	/**
	 * Tabla de tipos, para ordenar por tipo.
	 *
	 * @var string
	 */
	private string $types_table;

	/**
	 * Crea el repositorio.
	 *
	 * @param wpdb   $wpdb   Conexión de WordPress.
	 * @param Tables $tables Catálogo de tablas.
	 */
	public function __construct( wpdb $wpdb, Tables $tables ) {
		parent::__construct( $wpdb, $tables->name( Tables::EVENTS ) );
		$this->types_table = $tables->name( Tables::EVENT_TYPES );
	}

	/**
	 * Evento por ID.
	 *
	 * @param int $id ID.
	 */
	public function find( int $id ): ?Event {
		$row = $this->find_row( $id );

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Eventos que cumplen una búsqueda, en la página pedida.
	 *
	 * @param EventQuery $query Búsqueda.
	 */
	public function search( EventQuery $query ): EventPage {
		$wpdb               = $this->wpdb;
		[ $where, $values ] = $this->where( $query );
		$order              = str_replace( '{dir}', $query->ascending ? 'ASC' : 'DESC', self::ORDER_SQL[ $query->order_by ] ?? self::ORDER_SQL['start_date'] );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $where solo tiene marcadores (tantos como $values) y $order sale de ORDER_SQL; los valores van en prepare().
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i e WHERE {$where}", $this->table, ...$values ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.* FROM %i e LEFT JOIN %i t ON t.id = e.type_id WHERE {$where} ORDER BY {$order} LIMIT %d OFFSET %d",
				$this->table,
				$this->types_table,
				...[ ...$values, $query->per_page, $query->offset() ]
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		return new EventPage( $this->hydrate_all( $rows ), $total, $query->per_page );
	}

	/**
	 * Eventos que ocupan un día.
	 *
	 * @param string $date Día `Y-m-d`.
	 *
	 * @return list<Event>
	 */
	public function on_date( string $date ): array {
		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE start_date <= %s AND COALESCE(end_date, start_date) >= %s ORDER BY start_time ASC, id ASC',
				$this->table,
				$date,
				$date
			),
			ARRAY_A
		);

		return $this->hydrate_all( $rows );
	}

	/**
	 * Eventos que ocupan algún día del rango.
	 *
	 * @param string   $from     Primer día `Y-m-d`.
	 * @param string   $to       Último día `Y-m-d` (incluido).
	 * @param int[]    $type_ids Tipos de evento (vacío = todos).
	 * @param int|null $limit    Máximo de eventos (null = sin límite).
	 * @phpstan-param list<int> $type_ids
	 *
	 * @return list<Event>
	 */
	public function in_range( string $from, string $to, array $type_ids = [], ?int $limit = null ): array {
		$wpdb   = $this->wpdb;
		$sql    = 'SELECT * FROM %i WHERE start_date <= %s AND COALESCE(end_date, start_date) >= %s';
		$values = [ $this->table, $to, $from ];

		if ( [] !== $type_ids ) {
			$sql   .= ' AND type_id IN (' . implode( ', ', array_fill( 0, count( $type_ids ), '%d' ) ) . ')';
			$values = [ ...$values, ...$type_ids ];
		}

		$sql .= ' ORDER BY start_date ASC, start_time ASC, id ASC';

		if ( null !== $limit ) {
			$sql     .= ' LIMIT %d';
			$values[] = $limit;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql solo tiene marcadores; los valores van en prepare().
		return $this->hydrate_all( $wpdb->get_results( $wpdb->prepare( $sql, ...$values ), ARRAY_A ) );
	}

	/**
	 * Cantidad de eventos que ocupan algún día del rango.
	 *
	 * @param string $from Primer día `Y-m-d`.
	 * @param string $to   Último día `Y-m-d` (incluido).
	 */
	public function count_in_range( string $from, string $to ): int {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE start_date <= %s AND COALESCE(end_date, start_date) >= %s', $this->table, $to, $from )
		);
	}

	/**
	 * Guarda un evento nuevo.
	 *
	 * @param Event $event Evento sin ID.
	 */
	public function insert( Event $event ): Event {
		return $event->with_id( $this->insert_row( $this->dehydrate( $event ) ) );
	}

	/**
	 * Guarda los cambios de un evento.
	 *
	 * @param Event $event Evento con ID.
	 */
	public function update( Event $event ): void {
		$this->update_row( (int) $event->id, $this->dehydrate( $event ) );
	}

	/**
	 * Elimina un evento (solo la fila: el adjunto sigue en la Biblioteca de Medios, D-4).
	 *
	 * @param int $id ID.
	 */
	public function delete( int $id ): void {
		$this->delete_row( $id );
	}

	/**
	 * Condiciones de una búsqueda: SQL con marcadores y sus valores.
	 *
	 * @param EventQuery $query Búsqueda.
	 *
	 * @return array{0: string, 1: list<string|int>}
	 */
	private function where( EventQuery $query ): array {
		$conditions = [ '1 = 1' ];
		$values     = [];

		foreach ( $query->words as $word ) {
			$like         = '%' . $this->wpdb->esc_like( $word ) . '%';
			$conditions[] = '(e.title LIKE %s OR e.description LIKE %s)';
			array_push( $values, $like, $like );
		}

		if ( null !== $query->type_id ) {
			$conditions[] = 'e.type_id = %d';
			$values[]     = $query->type_id;
		}

		if ( null !== $query->date_to ) {
			$conditions[] = 'e.start_date <= %s';
			$values[]     = $query->date_to;
		}

		if ( null !== $query->date_from ) {
			$conditions[] = 'COALESCE(e.end_date, e.start_date) >= %s';
			$values[]     = $query->date_from;
		}

		return [ implode( ' AND ', $conditions ), $values ];
	}

	/**
	 * Filas → entidades.
	 *
	 * @param mixed $rows Resultado de get_results().
	 *
	 * @return list<Event>
	 */
	private function hydrate_all( mixed $rows ): array {
		return array_values( array_map( [ $this, 'hydrate' ], is_array( $rows ) ? $rows : [] ) );
	}

	/**
	 * Fila → entidad.
	 *
	 * @param array<string, mixed> $row Fila de la base de datos.
	 */
	private function hydrate( array $row ): Event {
		$optional = static fn( mixed $value ): ?string => null === $value || '' === $value ? null : (string) $value;

		return new Event(
			(int) $row['id'],
			(int) $row['type_id'],
			(string) $row['title'],
			(string) ( $row['description'] ?? '' ),
			EventSchedule::from_storage( (string) $row['start_date'], $optional( $row['start_time'] ?? null ), $optional( $row['end_date'] ?? null ), $optional( $row['end_time'] ?? null ) ),
			null === ( $row['attachment_id'] ?? null ) ? null : (int) $row['attachment_id'],
			(int) ( $row['created_by'] ?? 0 ),
			(int) ( $row['updated_by'] ?? 0 ),
			(string) $row['created_at_gmt'],
			(string) $row['updated_at_gmt']
		);
	}

	/**
	 * Entidad → columnas. Las horas se guardan en columnas TIME (`H:i:s`).
	 *
	 * @param Event $event Evento.
	 *
	 * @return array<string, string|int|null>
	 */
	private function dehydrate( Event $event ): array {
		$time     = static fn( ?string $value ): ?string => null === $value ? null : $value . ':00';
		$schedule = $event->schedule;

		return [
			'type_id'        => $event->type_id,
			'title'          => $event->title,
			'description'    => $event->description,
			'start_date'     => $schedule->start_date,
			'start_time'     => $time( $schedule->start_time ),
			'end_date'       => $schedule->end_date,
			'end_time'       => $time( $schedule->end_time ),
			'attachment_id'  => $event->attachment_id,
			'created_by'     => $event->created_by,
			'updated_by'     => $event->updated_by,
			'created_at_gmt' => $event->created_at_gmt,
			'updated_at_gmt' => $event->updated_at_gmt,
		];
	}
}
