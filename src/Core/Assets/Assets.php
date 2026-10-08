<?php
/**
 * Registro y encolado de los assets compilados.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core\Assets;

use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Core\PluginContext;
use Probolsas\Eventos\Core\Security\Capabilities;
use Probolsas\Eventos\Shared\Http\RestApi;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Ui\IconCatalog;

/**
 * Encola los bundles de `assets/dist` (generados por Vite) y expone la configuración SSOT a JS.
 *
 * Cada entrada de Vite produce `dist/js/<entrada>.js` (módulo ES) y, si importa estilos, `dist/css/<entrada>.css`.
 * Los estilos que comparten varias entradas (librerías como Font Awesome o Tom Select) quedan en otro
 * archivo, que se descubre con el manifest de Vite (`dist/.vite/manifest.json`).
 */
final class Assets {

	public const ADMIN_ENTRY = 'admin';

	public const PUBLIC_ENTRY = 'public';

	private const HANDLE_PREFIX = 'ep-';

	/**
	 * Handles de los scripts encolados por el plugin, que deben imprimirse como módulos ES.
	 *
	 * @var array<string, true>
	 */
	private array $module_handles = [];

	/**
	 * Crea el gestor de assets.
	 *
	 * @param PluginContext $context Ubicación y versión del plugin.
	 * @param Config        $config  Configuración del plugin.
	 * @param IconCatalog   $icons   Íconos permitidos (para los selectores de la interfaz).
	 * @param DateFormatter $dates   Fechas (el «hoy» de Colombia lo decide el servidor).
	 */
	public function __construct(
		private readonly PluginContext $context,
		private readonly Config $config,
		private readonly IconCatalog $icons,
		private readonly DateFormatter $dates
	) {}

	/**
	 * Conecta los filtros de WordPress.
	 */
	public function register(): void {
		add_filter( 'wp_script_attributes', [ $this, 'add_module_type' ] );
	}

	/**
	 * Encola los assets de las pantallas de administración del plugin.
	 */
	public function enqueue_admin(): void {
		$this->enqueue_entry( self::ADMIN_ENTRY );
	}

	/**
	 * Encola los assets de los shortcodes de la intranet. Se puede llamar varias veces (una por
	 * shortcode de la página): solo encola la primera.
	 */
	public function enqueue_public(): void {
		$this->enqueue_entry( self::PUBLIC_ENTRY );
	}

	/**
	 * Marca como módulo ES los scripts del plugin.
	 *
	 * @param array<string, string|bool> $attributes Atributos de la etiqueta <script>.
	 *
	 * @return array<string, string|bool>
	 */
	public function add_module_type( array $attributes ): array {
		$id = $attributes['id'] ?? '';

		if ( is_string( $id ) && str_ends_with( $id, '-js' ) && isset( $this->module_handles[ substr( $id, 0, -3 ) ] ) ) {
			$attributes['type'] = 'module';
		}

		return $attributes;
	}

	/**
	 * Configuración que recibe el navegador en `window.epConfig`.
	 *
	 * @return array<string, mixed>
	 */
	public function client_config(): array {
		$config = [
			'version'   => $this->context->version,
			'restUrl'   => esc_url_raw( rest_url( RestApi::NAMESPACE_V1 . '/' ) ),
			'restNonce' => wp_create_nonce( RestApi::NONCE_ACTION ),
			'ui'        => $this->config->get( 'ui', [] ),
			'icons'     => $this->icons->all(),
			'media'     => $this->config->get( 'media', [] ),
			// El navegador nunca calcula «hoy»: en Colombia, después de las 7:00 p. m. la fecha UTC ya es mañana.
			'today'     => $this->dates->today(),
			'firstDay'  => (int) get_option( 'start_of_week', 1 ),
			'can'       => [ 'manage' => current_user_can( Capabilities::MANAGE ) ],
			'loginUrl'  => esc_url_raw( wp_login_url() ),
		];

		/**
		 * Permite que cada dominio agregue su configuración para el navegador (por ejemplo, las reglas de
		 * validación de sus formularios en `rules`) sin que el núcleo dependa de los dominios.
		 *
		 * @param array<string, mixed> $config Configuración del cliente.
		 */
		return (array) apply_filters( 'eventos_client_config', $config );
	}

	/**
	 * Encola el CSS y el JS de una entrada de Vite, precedidos por la configuración del cliente.
	 *
	 * @param string $entry Nombre de la entrada definida en vite.config.js.
	 */
	private function enqueue_entry( string $entry ): void {
		$handle = self::HANDLE_PREFIX . $entry;
		$script = "assets/dist/js/{$entry}.js";

		// Ya encolado en esta petición (por ejemplo, una página con varios shortcodes).
		if ( isset( $this->module_handles[ $handle ] ) && wp_script_is( $handle, 'enqueued' ) ) {
			return;
		}

		// Primero todo el CSS compartido y al final el de la entrada, que depende de todo él: así gana en la
		// cascada aunque el manifest liste otra hoja después de la suya (por ejemplo, Bootstrap compartido
		// por las dos entradas). El compartido lleva hash en el nombre y se encola sin ?ver=: su dirección
		// coincide con la que usa el cargador de Vite al importar una pantalla, que entonces no lo vuelve a
		// insertar.
		$dependencies = [];
		$own_style    = null;
		foreach ( $this->styles_of( $entry ) as $file ) {
			$name = basename( $file, '.css' );

			if ( $name === $entry ) {
				$own_style = "assets/dist/{$file}";
				continue;
			}

			$dependencies[] = self::HANDLE_PREFIX . 'shared-' . sanitize_key( $name );
			wp_enqueue_style( end( $dependencies ), $this->context->url( "assets/dist/{$file}" ), [], null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- El nombre lleva hash.
		}

		if ( null !== $own_style ) {
			wp_enqueue_style( $handle, $this->context->url( $own_style ), $dependencies, $this->version_of( $own_style ) );
		}

		// wp-i18n expone window.wp.i18n, que usa assets/src/js/core/i18n.js para traducir los textos de la interfaz.
		wp_enqueue_script( $handle, $this->context->url( $script ), [ 'wp-i18n' ], $this->version_of( $script ), [ 'in_footer' => true ] );
		wp_set_script_translations( $handle, 'eventos-probolsas', $this->context->path( 'languages' ) );
		wp_add_inline_script( $handle, 'window.epConfig = ' . wp_json_encode( $this->client_config() ) . ';', 'before' );

		$this->module_handles[ $handle ] = true;
	}

	/**
	 * Hojas de estilo de una entrada, relativas a `assets/dist`: según el manifest de Vite, las de los
	 * chunks que importa (en orden) y al final la suya. Sin manifest, `css/<entrada>.css` si existe.
	 *
	 * @param string $entry Nombre de la entrada.
	 *
	 * @return list<string>
	 */
	private function styles_of( string $entry ): array {
		$manifest = $this->manifest();
		$key      = "assets/src/js/pages/{$entry}.js";

		if ( ! isset( $manifest[ $key ] ) ) {
			return file_exists( $this->context->path( "assets/dist/css/{$entry}.css" ) ) ? [ "css/{$entry}.css" ] : [];
		}

		return array_values( array_unique( $this->chunk_styles( $manifest, $key ) ) );
	}

	/**
	 * CSS de un chunk del manifest y de los que importa de forma estática (los dinámicos cargan el suyo).
	 *
	 * @param array<string, mixed> $manifest Manifest de Vite.
	 * @param string               $key      Chunk.
	 * @param array<string, true>  $visited  Chunks ya recorridos (evita ciclos).
	 *
	 * @return list<string>
	 */
	private function chunk_styles( array $manifest, string $key, array $visited = [] ): array {
		$chunk = $manifest[ $key ] ?? null;

		if ( isset( $visited[ $key ] ) || ! is_array( $chunk ) ) {
			return [];
		}

		$visited[ $key ] = true;
		$styles          = [];

		foreach ( (array) ( $chunk['imports'] ?? [] ) as $import ) {
			$styles = array_merge( $styles, $this->chunk_styles( $manifest, (string) $import, $visited ) );
		}

		return array_merge( $styles, array_map( 'strval', array_values( (array) ( $chunk['css'] ?? [] ) ) ) );
	}

	/**
	 * Manifest de Vite (`assets/dist/.vite/manifest.json`), o vacío si no existe.
	 *
	 * @return array<string, mixed>
	 */
	private function manifest(): array {
		$path = $this->context->path( 'assets/dist/.vite/manifest.json' );

		if ( ! file_exists( $path ) ) {
			return [];
		}

		$manifest = wp_json_file_decode( $path, [ 'associative' => true ] );

		return is_array( $manifest ) ? $manifest : [];
	}

	/**
	 * Versión para invalidar la caché del navegador: la fecha de modificación del archivo compilado.
	 *
	 * @param string $relative Ruta relativa a la raíz del plugin.
	 */
	private function version_of( string $relative ): string {
		$modified = @filemtime( $this->context->path( $relative ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Si el archivo no existe se usa la versión del plugin.

		return false === $modified ? $this->context->version : (string) $modified;
	}
}
