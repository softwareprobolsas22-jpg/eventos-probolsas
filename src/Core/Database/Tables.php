<?php
/**
 * Tablas propias del plugin.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core\Database;

/**
 * Fuente única de verdad de los nombres de las tablas del plugin.
 */
final class Tables {

	public const EVENT_TYPES = 'eventos_event_types';
	public const EVENTS      = 'eventos_events';

	/**
	 * Todas las tablas, ordenadas de dependiente a independiente (orden seguro para eliminarlas).
	 */
	private const ALL = [ self::EVENTS, self::EVENT_TYPES ];

	/**
	 * Crea el catálogo de tablas.
	 *
	 * @param \wpdb $wpdb Conexión de WordPress.
	 */
	public function __construct( private readonly \wpdb $wpdb ) {}

	/**
	 * Nombre completo de una tabla, con el prefijo de la instalación.
	 *
	 * @param string $table Una de las constantes de esta clase.
	 */
	public function name( string $table ): string {
		return $this->wpdb->prefix . $table;
	}

	/**
	 * Nombres completos de todas las tablas del plugin.
	 *
	 * @return list<string>
	 */
	public function all_names(): array {
		return array_map( [ $this, 'name' ], self::ALL );
	}

	/**
	 * Juego de caracteres y collation de la instalación, para las sentencias CREATE TABLE.
	 */
	public function charset_collate(): string {
		return $this->wpdb->get_charset_collate();
	}

	/**
	 * Collation binaria del juego de caracteres de la instalación (por ejemplo `utf8mb4_bin`), para las
	 * columnas que guardan un valor ya normalizado y deben compararse letra por letra. Las collation
	 * `*_ci` tratan la ñ como n («cumpleanos» = «cumpleaños»).
	 */
	public function binary_collation(): string {
		$charset = '' !== (string) $this->wpdb->charset ? (string) $this->wpdb->charset : 'utf8mb4';

		return $charset . '_bin';
	}

	/**
	 * Elimina todas las tablas del plugin.
	 */
	public function drop_all(): void {
		$wpdb = $this->wpdb;

		foreach ( $this->all_names() as $table ) {
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		}
	}
}
