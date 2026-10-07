<?php
/**
 * Contrato de un shortcode del plugin.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core\Frontend;

/**
 * Shortcode de la intranet. Cada dominio aporta los suyos (capa Presentation) y los registra con la
 * etiqueta ShortcodeRegistry::SHORTCODES_TAG del contenedor.
 */
interface Shortcode {

	/**
	 * Nombre del shortcode (con el prefijo `eventos_`).
	 */
	public function tag(): string;

	/**
	 * HTML del shortcode.
	 *
	 * @param array<string, string> $atts Atributos escritos en el contenido.
	 */
	public function render( array $atts ): string;

	/**
	 * Cómo se usa, para la pantalla «Shortcodes». Debe coincidir con los atributos que lee render().
	 */
	public function guide(): ShortcodeGuide;
}
