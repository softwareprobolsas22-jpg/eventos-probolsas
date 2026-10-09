<?php
/**
 * Registro de los shortcodes del plugin.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core\Frontend;

use Probolsas\Eventos\Core\Assets\Assets;
use Probolsas\Eventos\Core\Security\Capabilities;
use WP_Post;

/**
 * Registra los shortcodes aportados por los dominios y encola los assets públicos solo en las páginas
 * que los usan: en `wp_enqueue_scripts` si el contenido los contiene (estilos en el <head>, sin
 * parpadeo) y, como respaldo, al imprimir el shortcode (por ejemplo, dentro de un widget o un bloque).
 */
final class ShortcodeRegistry {

	/**
	 * Etiqueta del contenedor con la que los dominios aportan sus shortcodes.
	 */
	public const SHORTCODES_TAG = 'eventos.shortcodes';

	/**
	 * Crea el registro.
	 *
	 * @param Shortcode[] $shortcodes Shortcodes aportados por los dominios.
	 * @param Assets      $assets     Gestor de assets.
	 * @phpstan-param list<Shortcode> $shortcodes
	 */
	public function __construct(
		private readonly array $shortcodes,
		private readonly Assets $assets
	) {}

	/**
	 * Conecta los shortcodes con WordPress.
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'add_shortcodes' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_when_used' ] );
	}

	/**
	 * Registra cada shortcode.
	 */
	public function add_shortcodes(): void {
		foreach ( $this->shortcodes as $shortcode ) {
			add_shortcode( $shortcode->tag(), [ $this, 'dispatch' ] );
		}
	}

	/**
	 * Imprime un shortcode. WordPress entrega los atributos como arreglo, o como texto vacío si el
	 * shortcode no tiene ninguno.
	 *
	 * @param mixed  $atts    Atributos.
	 * @param mixed  $content Contenido encerrado (no se usa).
	 * @param string $tag     Nombre del shortcode.
	 */
	public function dispatch( mixed $atts, mixed $content = '', string $tag = '' ): string {
		foreach ( $this->shortcodes as $shortcode ) {
			if ( $shortcode->tag() === $tag ) {
				return $shortcode->render( is_array( $atts ) ? array_map( 'strval', $atts ) : [] );
			}
		}

		return '';
	}

	/**
	 * Encola los assets públicos si la página actual usa algún shortcode del plugin y el usuario puede ver
	 * los eventos: a los demás se les muestra un aviso que no necesita el JS ni los estilos (R-17).
	 */
	public function enqueue_when_used(): void {
		$post = get_post();

		if ( current_user_can( Capabilities::VIEW ) && is_singular() && $post instanceof WP_Post && $this->uses_shortcodes( $post->post_content ) ) {
			$this->assets->enqueue_public();
		}
	}

	/**
	 * Indica si un contenido usa algún shortcode del plugin.
	 *
	 * @param string $content Contenido.
	 */
	public function uses_shortcodes( string $content ): bool {
		foreach ( $this->shortcodes as $shortcode ) {
			if ( has_shortcode( $content, $shortcode->tag() ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Nombres de los shortcodes registrados.
	 *
	 * @return list<string>
	 */
	public function tags(): array {
		return array_map( static fn( Shortcode $shortcode ): string => $shortcode->tag(), $this->shortcodes );
	}

	/**
	 * Guía de cada shortcode, para la pantalla «Shortcodes».
	 *
	 * @return list<array<string, mixed>>
	 */
	public function guides(): array {
		return array_map( static fn( Shortcode $shortcode ): array => $shortcode->guide()->to_array( $shortcode->tag() ), $this->shortcodes );
	}
}
