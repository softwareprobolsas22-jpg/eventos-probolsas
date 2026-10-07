<?php
/**
 * Contenedor de una interfaz pública (widget).
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core\Frontend;

use Probolsas\Eventos\Core\Assets\Assets;
use Probolsas\Eventos\Core\Security\Capabilities;
use Probolsas\Eventos\Core\View\View;

/**
 * Imprime el contenedor donde assets/src/js/pages/public.js monta cada widget, solo para quien puede
 * consultar los eventos (`eventos_view`). A los visitantes sin sesión les muestra un aviso con el enlace
 * para iniciar sesión. Los datos no viajan en el HTML: el widget los pide a la API.
 */
final class WidgetRenderer {

	/**
	 * Crea el renderizador.
	 *
	 * @param View   $view   Plantillas.
	 * @param Assets $assets Gestor de assets.
	 */
	public function __construct(
		private readonly View $view,
		private readonly Assets $assets
	) {}

	/**
	 * HTML del widget, o el aviso que corresponda si el usuario no puede verlo.
	 *
	 * @param string               $widget Nombre del widget (`calendar`, `upcoming`).
	 * @param array<string, mixed> $props  Opciones del widget, ya saneadas.
	 */
	public function render( string $widget, array $props = [] ): string {
		if ( ! is_user_logged_in() ) {
			return $this->view->fetch(
				'public/notice',
				[
					'icon'    => 'fa-solid fa-right-to-bracket',
					'message' => __( 'Inicia sesión para ver el calendario de eventos.', 'eventos-probolsas' ),
					'link'    => [
						'url'   => wp_login_url( (string) get_permalink() ),
						'label' => __( 'Iniciar sesión', 'eventos-probolsas' ),
					],
				]
			);
		}

		if ( ! current_user_can( Capabilities::VIEW ) ) {
			return $this->view->fetch(
				'public/notice',
				[
					'icon'    => 'fa-solid fa-lock',
					'message' => __( 'No tienes permiso para ver el calendario de eventos.', 'eventos-probolsas' ),
				]
			);
		}

		$this->assets->enqueue_public();

		return $this->view->fetch(
			'public/widget',
			[
				'widget' => $widget,
				'props'  => $props,
			]
		);
	}
}
