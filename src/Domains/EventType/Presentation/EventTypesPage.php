<?php
/**
 * Pantalla de administración «Tipos de evento».
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\EventType\Presentation;

use Probolsas\Eventos\Core\Admin\AdminPage;
use Probolsas\Eventos\Core\Security\Capabilities;
use Probolsas\Eventos\Core\View\View;

/**
 * Listado y gestión de tipos de evento. La interfaz la construye assets/src/js/screens/event-types.js
 * contra la API `eventos/v1/event-types` (H-104).
 */
final class EventTypesPage implements AdminPage {

	public const SLUG = 'eventos-probolsas-tipos';

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
		return __( 'Tipos de evento', 'eventos-probolsas' );
	}

	/**
	 * Texto del submenú.
	 */
	public function menu_title(): string {
		return __( 'Tipos de evento', 'eventos-probolsas' );
	}

	/**
	 * Capability requerida.
	 */
	public function capability(): string {
		return Capabilities::MANAGE;
	}

	/**
	 * Posición en el submenú (después de «Eventos»).
	 */
	public function position(): int {
		return 20;
	}

	/**
	 * Imprime la pantalla.
	 */
	public function render(): void {
		$this->view->render(
			'admin/layout',
			[
				'screen'   => $this->slug(),
				'icon'     => 'fa-solid fa-tags',
				'title'    => __( 'Tipos de evento', 'eventos-probolsas' ),
				'subtitle' => __( 'Clasifica los eventos del calendario con un color, un ícono y si requieren imagen o PDF.', 'eventos-probolsas' ),
				'content'  => $this->view->fetch( 'partials/mount', [ 'id' => 'ep-event-types' ] ),
			]
		);
	}
}
