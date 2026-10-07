<?php
/**
 * Ejecución de migraciones de esquema.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core\Database;

/**
 * Aplica en orden las migraciones pendientes y registra la versión del esquema en una opción.
 *
 * Las migraciones se registran en el contenedor con la etiqueta `MIGRATIONS_TAG`.
 */
final class Migrator {

	public const OPTION         = 'eventos_db_version';
	public const MIGRATIONS_TAG = 'eventos.migrations';

	/**
	 * Migraciones ordenadas por versión ascendente.
	 *
	 * @var list<Migration>
	 */
	private array $migrations;

	/**
	 * Crea el migrador.
	 *
	 * @param Migration[] $migrations Migraciones disponibles, en cualquier orden.
	 * @param Tables      $tables     Catálogo de tablas del plugin.
	 * @phpstan-param list<Migration> $migrations
	 *
	 * @throws \LogicException Si dos migraciones declaran la misma versión.
	 */
	public function __construct( array $migrations, private readonly Tables $tables ) {
		usort(
			$migrations,
			static fn( Migration $a, Migration $b ): int => $a->version() <=> $b->version()
		);

		$versions = array_map( static fn( Migration $migration ): int => $migration->version(), $migrations );

		if ( count( $versions ) !== count( array_unique( $versions ) ) ) {
			throw new \LogicException( 'Hay migraciones con la misma versión.' );
		}

		$this->migrations = $migrations;
	}

	/**
	 * Versión del esquema aplicada en la base de datos (0 si no hay ninguna).
	 */
	public function current_version(): int {
		return (int) get_option( self::OPTION, 0 );
	}

	/**
	 * Versión más alta disponible en el código.
	 */
	public function latest_version(): int {
		$last = end( $this->migrations );

		return false === $last ? 0 : $last->version();
	}

	/**
	 * Indica si hay migraciones pendientes.
	 */
	public function needs_migration(): bool {
		return $this->current_version() < $this->latest_version();
	}

	/**
	 * Aplica las migraciones pendientes en orden. La versión se registra tras cada migración exitosa.
	 */
	public function migrate(): void {
		$current = $this->current_version();

		foreach ( $this->migrations as $migration ) {
			if ( $migration->version() <= $current ) {
				continue;
			}

			$migration->up();
			update_option( self::OPTION, $migration->version(), true );
			$current = $migration->version();
		}
	}

	/**
	 * Elimina todas las tablas del plugin y el registro de versión.
	 */
	public function reset(): void {
		$this->tables->drop_all();
		delete_option( self::OPTION );
	}
}
