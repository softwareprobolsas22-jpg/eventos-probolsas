<?php
/**
 * Migración 1: esquema base.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core\Database\Migrations;

use Probolsas\Eventos\Core\Database\Migration;
use Probolsas\Eventos\Core\Database\Tables;

/**
 * Crea las tablas de tipos de evento y de eventos.
 *
 * Decisiones del esquema (docs/scrum/00-analisis-sm.md §5.5):
 * - Sin llaves foráneas: dbDelta no las soporta. La integridad la garantizan los servicios de cada dominio,
 *   que además impiden eliminar un tipo que tenga eventos.
 * - `name_key` guarda el nombre normalizado (minúsculas, sin tildes) para que la unicidad no dependa del
 *   collation de la instalación.
 * - Las fechas de auditoría se guardan en UTC (`*_gmt`); las de calendario (`start_*`, `end_*`) son la hora
 *   de pared de Colombia y no se convierten.
 * - `end_date` y `end_time` quedan reservados para la hora de fin y los eventos de varios días (D-7, v1.1):
 *   en v1 siempre son NULL. El índice (start_date, end_date) sirve a la consulta por solapamiento de rango.
 */
final class CreateBaseSchema implements Migration {

	/**
	 * Crea la migración.
	 *
	 * @param Tables $tables Catálogo de tablas del plugin.
	 */
	public function __construct( private readonly Tables $tables ) {}

	/**
	 * Versión del esquema.
	 */
	public function version(): int {
		return 1;
	}

	/**
	 * Crea o actualiza las tablas con dbDelta.
	 */
	public function up(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( $this->statements() as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Sentencias CREATE TABLE con el formato estricto que exige dbDelta (un campo por línea, dos espacios
	 * después de PRIMARY KEY y KEY en lugar de INDEX).
	 *
	 * @return list<string>
	 */
	public function statements(): array {
		$charset_collate = $this->tables->charset_collate();

		$event_types = [
			'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
			'name varchar(100) NOT NULL',
			'name_key varchar(100) NOT NULL',
			'slug varchar(120) NOT NULL',
			'color char(7) NOT NULL',
			'icon varchar(60) NOT NULL',
			'requires_attachment tinyint(1) unsigned NOT NULL DEFAULT 0',
			'description varchar(500) NOT NULL DEFAULT \'\'',
			'sort_order int(11) NOT NULL DEFAULT 0',
			'created_at_gmt datetime NOT NULL',
			'updated_at_gmt datetime NOT NULL',
			'PRIMARY KEY  (id)',
			'UNIQUE KEY slug (slug)',
			'UNIQUE KEY name_key (name_key)',
			'KEY sort_order (sort_order)',
		];

		$events = [
			'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
			'type_id bigint(20) unsigned NOT NULL',
			'title varchar(150) NOT NULL',
			'description text NULL',
			'start_date date NOT NULL',
			'start_time time NULL',
			'end_date date NULL',
			'end_time time NULL',
			'attachment_id bigint(20) unsigned NULL',
			'created_by bigint(20) unsigned NOT NULL DEFAULT 0',
			'updated_by bigint(20) unsigned NOT NULL DEFAULT 0',
			'created_at_gmt datetime NOT NULL',
			'updated_at_gmt datetime NOT NULL',
			'PRIMARY KEY  (id)',
			'KEY type_id (type_id)',
			'KEY start_end (start_date,end_date)',
		];

		return [
			$this->create_table( $this->tables->name( Tables::EVENT_TYPES ), $event_types, $charset_collate ),
			$this->create_table( $this->tables->name( Tables::EVENTS ), $events, $charset_collate ),
		];
	}

	/**
	 * Arma una sentencia CREATE TABLE con el formato de dbDelta.
	 *
	 * @param string   $table           Nombre completo de la tabla.
	 * @param string[] $definitions     Definiciones de columnas e índices.
	 * @param string   $charset_collate Juego de caracteres y collation.
	 * @phpstan-param list<string> $definitions
	 */
	private function create_table( string $table, array $definitions, string $charset_collate ): string {
		return "CREATE TABLE {$table} (\n" . implode( ",\n", $definitions ) . "\n) {$charset_collate};";
	}
}
