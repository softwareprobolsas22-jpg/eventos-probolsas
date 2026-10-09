<?php
/**
 * Atributo `tipos` de los shortcodes.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Presentation;

use Probolsas\Eventos\Domains\EventType\Domain\EventTypeRepository;

/**
 * Convierte el atributo `tipos="cumpleanos, capacitaciones"` en los IDs que reciben los widgets. Usa los
 * *slugs* porque no cambian al renombrar un tipo; los desconocidos se ignoran (un tipo eliminado no rompe
 * la página que lo nombraba).
 */
final class ShortcodeTypes {

	/**
	 * Crea el conversor.
	 *
	 * @param EventTypeRepository $types Tipos de evento.
	 */
	public function __construct( private readonly EventTypeRepository $types ) {}

	/**
	 * IDs de los tipos nombrados, en el orden de los tipos. Vacío = todos.
	 *
	 * @param string $slugs Slugs separados por comas.
	 *
	 * @return list<int>
	 */
	public function ids( string $slugs ): array {
		$wanted = array_filter( array_map( static fn( string $slug ): string => strtolower( trim( $slug ) ), explode( ',', $slugs ) ) );

		if ( [] === $wanted ) {
			return [];
		}

		$ids = [];
		foreach ( $this->types->all_with_event_counts() as $summary ) {
			if ( in_array( $summary->type->slug, $wanted, true ) ) {
				$ids[] = (int) $summary->type->id;
			}
		}

		return $ids;
	}
}
