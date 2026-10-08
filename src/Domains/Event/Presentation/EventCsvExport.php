<?php
/**
 * Exportación de eventos a CSV.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Presentation;

use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Domains\Event\Domain\Event;
use Probolsas\Eventos\Shared\Time\DateFormatter;

/**
 * CSV que abre bien en Excel en español: separador `;` (`config/ui.php`), BOM UTF-8, fechas `dd/mm/aaaa`
 * y horas en 12 h (R-07). Las celdas que empiezan por `=`, `+`, `-` o `@` se anteponen con un apóstrofo
 * para que Excel no las ejecute como fórmulas (inyección de CSV).
 */
final class EventCsvExport {

	private const BOM = "\xEF\xBB\xBF";

	/**
	 * Crea el exportador.
	 *
	 * @param DateFormatter $dates  Fechas.
	 * @param Config        $config Configuración (separador).
	 */
	public function __construct(
		private readonly DateFormatter $dates,
		private readonly Config $config
	) {}

	/**
	 * Arma el archivo.
	 *
	 * @param iterable $events    Eventos en el orden de la tabla.
	 * @param callable $type_name Nombre del tipo por ID.
	 * @param callable $user_name Nombre del usuario por ID ('' si fue eliminado).
	 *
	 * @phpstan-param iterable<Event> $events
	 * @phpstan-param callable(int): string $type_name
	 * @phpstan-param callable(int): string $user_name
	 */
	public function build( iterable $events, callable $type_name, callable $user_name ): string {
		$separator = (string) $this->config->get( 'ui.csv_separator', ';' );
		$handle    = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Archivo en memoria.

		if ( false === $handle ) {
			return self::BOM;
		}

		$this->write(
			$handle,
			$separator,
			[
				__( 'Evento', 'eventos-probolsas' ),
				__( 'Tipo', 'eventos-probolsas' ),
				__( 'Fecha', 'eventos-probolsas' ),
				__( 'Hora', 'eventos-probolsas' ),
				__( 'Descripción', 'eventos-probolsas' ),
				__( 'Adjunto', 'eventos-probolsas' ),
				__( 'Creado por', 'eventos-probolsas' ),
				__( 'Actualizado', 'eventos-probolsas' ),
			]
		);

		foreach ( $events as $event ) {
			$schedule = $event->schedule;
			$this->write(
				$handle,
				$separator,
				[
					$event->title,
					$type_name( $event->type_id ),
					$this->dates->format_calendar_date( $schedule->start_date ),
					null === $schedule->start_time ? __( 'Todo el día', 'eventos-probolsas' ) : $this->dates->format_calendar_time( $schedule->start_time ),
					$event->description,
					null === $event->attachment_id ? __( 'No', 'eventos-probolsas' ) : __( 'Sí', 'eventos-probolsas' ),
					$user_name( $event->created_by ),
					$this->dates->format_datetime( $event->updated_at_gmt ),
				]
			);
		}

		rewind( $handle );
		$csv = (string) stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Archivo en memoria.

		return self::BOM . $csv;
	}

	/**
	 * Escribe una fila protegida contra fórmulas.
	 *
	 * @param resource $handle    Archivo.
	 * @param string   $separator Separador.
	 * @param string[] $cells     Celdas.
	 *
	 * @phpstan-param list<string> $cells
	 */
	private function write( $handle, string $separator, array $cells ): void {
		$safe = array_map( static fn( string $cell ): string => 1 === preg_match( '/^[=+\-@\t\r]/', $cell ) ? "'" . $cell : $cell, $cells );
		fputcsv( $handle, $safe, $separator, '"', '' );
	}
}
