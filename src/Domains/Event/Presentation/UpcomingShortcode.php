<?php
/**
 * Shortcode [eventos_proximos].
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Presentation;

use Probolsas\Eventos\Core\Frontend\Shortcode;
use Probolsas\Eventos\Core\Frontend\ShortcodeAttribute;
use Probolsas\Eventos\Core\Frontend\ShortcodeGuide;
use Probolsas\Eventos\Core\Frontend\WidgetRenderer;
use Probolsas\Eventos\Domains\Event\Application\CalendarService;

/**
 * Lista de los próximos eventos (widget `upcoming`, H-304). Solo para usuarios con sesión (D-1).
 */
final class UpcomingShortcode implements Shortcode {

	public const TAG = 'eventos_proximos';

	/**
	 * Crea el shortcode.
	 *
	 * @param WidgetRenderer $renderer Contenedor del widget.
	 * @param ShortcodeTypes $types    Atributo `tipos`.
	 */
	public function __construct(
		private readonly WidgetRenderer $renderer,
		private readonly ShortcodeTypes $types
	) {}

	/**
	 * Nombre del shortcode.
	 */
	public function tag(): string {
		return self::TAG;
	}

	/**
	 * HTML del shortcode. Un `limite` fuera de 1–20 se ajusta al extremo más cercano.
	 *
	 * @param array<string, string> $atts Atributos.
	 */
	public function render( array $atts ): string {
		$limit = filter_var( $atts['limite'] ?? '', FILTER_VALIDATE_INT );

		return $this->renderer->render(
			'upcoming',
			[
				'limit' => false === $limit ? CalendarService::UPCOMING_DEFAULT_LIMIT : max( 1, min( CalendarService::UPCOMING_MAX_LIMIT, $limit ) ),
				'types' => $this->types->ids( $atts['tipos'] ?? '' ),
				'title' => sanitize_text_field( $atts['titulo'] ?? __( 'Próximos eventos', 'eventos-probolsas' ) ),
			]
		);
	}

	/**
	 * Guía de uso.
	 */
	public function guide(): ShortcodeGuide {
		return new ShortcodeGuide(
			__( 'Próximos eventos', 'eventos-probolsas' ),
			__( 'Lista de los eventos que vienen, desde hoy. Solo la ven los usuarios con sesión iniciada.', 'eventos-probolsas' ),
			'[eventos_proximos limite="5"]',
			[
				new ShortcodeAttribute(
					'limite',
					/* translators: %d: máximo de eventos. */
					sprintf( __( 'Cuántos eventos mostrar, de 1 a %d.', 'eventos-probolsas' ), CalendarService::UPCOMING_MAX_LIMIT ),
					false,
					(string) CalendarService::UPCOMING_DEFAULT_LIMIT
				),
				new ShortcodeAttribute(
					'tipos',
					__( 'Muestra solo estos tipos de evento, separados por comas.', 'eventos-probolsas' ),
					false,
					__( 'Todos', 'eventos-probolsas' ),
					ShortcodeAttribute::EVENT_TYPES
				),
				new ShortcodeAttribute(
					'titulo',
					__( 'Título sobre la lista. Vacío para no mostrarlo.', 'eventos-probolsas' ),
					false,
					__( 'Próximos eventos', 'eventos-probolsas' )
				),
			]
		);
	}
}
