<?php
/**
 * Pantalla «Ajustes».
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Settings\Presentation;

use Probolsas\Eventos\Core\Admin\AdminPage;
use Probolsas\Eventos\Core\Security\Capabilities;
use Probolsas\Eventos\Core\View\View;

/**
 * Submenú «Ajustes» de Eventos (H-402, D-16). La interfaz la monta assets/src/js/screens/settings.js
 * sobre `#ep-settings`; los datos llegan de `GET /settings`.
 */
final class SettingsPage implements AdminPage {

	public const SLUG = 'eventos-probolsas-ajustes';

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
		return __( 'Ajustes', 'eventos-probolsas' );
	}

	/**
	 * Texto del submenú.
	 */
	public function menu_title(): string {
		return __( 'Ajustes', 'eventos-probolsas' );
	}

	/**
	 * Capability requerida.
	 */
	public function capability(): string {
		return Capabilities::MANAGE;
	}

	/**
	 * Posición en el submenú (después de «Tipos de evento»).
	 */
	public function position(): int {
		return 30;
	}

	/**
	 * Imprime la pantalla.
	 */
	public function render(): void {
		$this->view->render(
			'admin/layout',
			[
				'screen'   => $this->slug(),
				'icon'     => 'fa-solid fa-gear',
				'title'    => __( 'Ajustes', 'eventos-probolsas' ),
				'subtitle' => __( 'Qué pasa con los eventos y los tipos si se desinstala el plugin.', 'eventos-probolsas' ),
				'content'  => $this->view->fetch( 'partials/mount', [ 'id' => 'ep-settings' ] ),
			]
		);
	}
}
