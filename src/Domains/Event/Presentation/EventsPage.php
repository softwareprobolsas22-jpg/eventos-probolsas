<?php
/**
 * Pantalla de administración «Eventos».
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Presentation;

use Probolsas\Eventos\Core\Admin\AdminMenu;
use Probolsas\Eventos\Core\Admin\AdminPage;
use Probolsas\Eventos\Core\Security\Capabilities;
use Probolsas\Eventos\Core\View\View;

/**
 * Listado y gestión de eventos: es la pantalla principal del menú «Eventos» (QA-022). La interfaz la
 * construye assets/src/js/screens/events.js contra la API `eventos/v1/events` (H-203).
 */
final class EventsPage implements AdminPage {

	public const SLUG = AdminMenu::ROOT_SLUG;

	/**
	 * Crea la pantalla.
	 *
	 * @param View $view Renderizador de plantillas.
	 */
	public function __construct( private readonly View $view ) {}

	/**
	 * Slug de la pantalla.
	 */
	public function slug(): string {
		return self::SLUG;
	}

	/**
	 * Título de la pestaña del navegador.
	 */
	public function page_title(): string {
		return __( 'Eventos', 'eventos-probolsas' );
	}

	/**
	 * Texto del submenú.
	 */
	public function menu_title(): string {
		return __( 'Eventos', 'eventos-probolsas' );
	}

	/**
	 * Capability requerida.
	 */
	public function capability(): string {
		return Capabilities::MANAGE;
	}

	/**
	 * Posición en el submenú (la primera).
	 */
	public function position(): int {
		return 10;
	}

	/**
	 * Imprime la pantalla.
	 */
	public function render(): void {
		$this->view->render(
			'admin/layout',
			[
				'screen'   => $this->slug(),
				'icon'     => 'fa-solid fa-calendar-days',
				'title'    => __( 'Eventos', 'eventos-probolsas' ),
				'subtitle' => __( 'Crea, busca y exporta los eventos del calendario de la intranet.', 'eventos-probolsas' ),
				'content'  => $this->view->fetch( 'partials/mount', [ 'id' => 'ep-events' ] ),
			]
		);
	}
}
