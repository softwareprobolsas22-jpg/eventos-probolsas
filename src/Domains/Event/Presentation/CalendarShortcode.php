<?php
/**
 * Shortcode [eventos_calendario].
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Presentation;

use Probolsas\Eventos\Core\Frontend\Shortcode;
use Probolsas\Eventos\Core\Frontend\ShortcodeAttribute;
use Probolsas\Eventos\Core\Frontend\ShortcodeGuide;
use Probolsas\Eventos\Core\Frontend\WidgetRenderer;

/**
 * Calendario de eventos de la intranet (widget `calendar`, H-302). Solo para usuarios con sesión (D-1):
 * WidgetRenderer muestra a los demás el aviso para iniciar sesión. Los eventos no viajan en el HTML: el
 * widget los pide al feed por rango.
 */
final class CalendarShortcode implements Shortcode {

	public const TAG = 'eventos_calendario';

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
	 * HTML del shortcode.
	 *
	 * @param array<string, string> $atts Atributos.
	 */
	public function render( array $atts ): string {
		return $this->renderer->render( 'calendar', [ 'types' => $this->types->ids( $atts['tipos'] ?? '' ) ] );
	}

	/**
	 * Guía de uso.
	 */
	public function guide(): ShortcodeGuide {
		return new ShortcodeGuide(
			__( 'Calendario de eventos', 'eventos-probolsas' ),
			__( 'Calendario mensual y lista de eventos, con filtro por tipo y detalle de cada evento. Solo lo ven los usuarios con sesión iniciada.', 'eventos-probolsas' ),
			'[eventos_calendario]',
			[
				new ShortcodeAttribute(
					'tipos',
					__( 'Muestra solo estos tipos de evento, separados por comas.', 'eventos-probolsas' ),
					false,
					__( 'Todos', 'eventos-probolsas' ),
					ShortcodeAttribute::EVENT_TYPES
				),
			]
		);
	}
}
