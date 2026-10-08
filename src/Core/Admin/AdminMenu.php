<?php
/**
 * Menú de administración del plugin.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core\Admin;

use Probolsas\Eventos\Core\Assets\Assets;

/**
 * Registra el menú y las pantallas que aportan los dominios, y carga los assets solo en esas pantallas.
 */
final class AdminMenu {

	public const PAGES_TAG = 'eventos.admin_pages';
	public const ROOT_SLUG = 'eventos-probolsas';

	/**
	 * Clase agregada al <body> en las pantallas del plugin, para acotar los estilos.
	 */
	public const BODY_CLASS = 'ep-admin';

	private const MENU_ICON     = 'dashicons-calendar-alt';
	private const MENU_POSITION = 26;

	/**
	 * Indica si la petición actual corresponde a una pantalla del plugin.
	 *
	 * @var bool
	 */
	private bool $is_plugin_screen = false;

	/**
	 * Crea el menú.
	 *
	 * @param AdminPage[] $pages  Pantallas aportadas por los dominios.
	 * @param Assets      $assets Gestor de assets.
	 * @phpstan-param list<AdminPage> $pages
	 */
	public function __construct(
		private readonly array $pages,
		private readonly Assets $assets
	) {}

	/**
	 * Conecta el menú con WordPress.
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_pages' ] );
		add_filter( 'admin_body_class', [ $this, 'add_body_class' ] );
	}

	/**
	 * Registra el menú principal y un submenú por pantalla. La pantalla principal es la de `ROOT_SLUG`; mientras
	 * esa pantalla no exista (por ejemplo, antes de construir la de eventos), lo es la primera por posición.
	 */
	public function add_pages(): void {
		$root = $this->find_root_page();

		if ( null === $root ) {
			return;
		}

		add_menu_page(
			$root->page_title(),
			__( 'Eventos', 'eventos-probolsas' ),
			$root->capability(),
			$root->slug(),
			[ $root, 'render' ],
			self::MENU_ICON,
			self::MENU_POSITION
		);

		foreach ( $this->sorted_pages() as $page ) {
			$hook_suffix = add_submenu_page(
				$root->slug(),
				$page->page_title(),
				$page->menu_title(),
				$page->capability(),
				$page->slug(),
				[ $page, 'render' ]
			);

			if ( false !== $hook_suffix ) {
				add_action( "load-{$hook_suffix}", [ $this, 'prepare_plugin_screen' ] );
			}
		}
	}

	/**
	 * Se ejecuta antes de renderizar una pantalla del plugin: marca la petición y encola los assets.
	 */
	public function prepare_plugin_screen(): void {
		$this->is_plugin_screen = true;

		add_action( 'admin_enqueue_scripts', [ $this->assets, 'enqueue_admin' ] );
	}

	/**
	 * Agrega la clase de acotamiento al <body> en las pantallas del plugin.
	 *
	 * @param string $classes Clases actuales del <body>, separadas por espacios.
	 */
	public function add_body_class( string $classes ): string {
		return $this->is_plugin_screen ? $classes . ' ' . self::BODY_CLASS : $classes;
	}

	/**
	 * Pantalla principal del menú: la de `ROOT_SLUG` o, si no existe, la primera por posición.
	 */
	private function find_root_page(): ?AdminPage {
		foreach ( $this->pages as $page ) {
			if ( self::ROOT_SLUG === $page->slug() ) {
				return $page;
			}
		}

		return $this->sorted_pages()[0] ?? null;
	}

	/**
	 * Pantallas ordenadas por posición.
	 *
	 * @return list<AdminPage>
	 */
	private function sorted_pages(): array {
		$pages = $this->pages;

		usort(
			$pages,
			static fn( AdminPage $a, AdminPage $b ): int => $a->position() <=> $b->position()
		);

		return $pages;
	}
}
