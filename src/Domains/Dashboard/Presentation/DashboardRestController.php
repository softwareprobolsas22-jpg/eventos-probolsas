<?php
/**
 * API del dashboard.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Dashboard\Presentation;

use Probolsas\Eventos\Domains\Dashboard\Application\DashboardService;
use Probolsas\Eventos\Shared\Http\RestController;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

/**
 * `GET eventos/v1/dashboard`: cifras de las tarjetas de «Eventos» (docs/api/events.md). Solo gestores.
 */
final class DashboardRestController extends RestController {

	/**
	 * Crea el controlador.
	 *
	 * @param DashboardService $dashboard Resumen.
	 */
	public function __construct( private readonly DashboardService $dashboard ) {}

	/**
	 * Segmento base.
	 */
	protected function rest_base(): string {
		return 'dashboard';
	}

	/**
	 * Rutas del recurso.
	 */
	public function register_routes(): void {
		$this->add_route(
			'',
			[
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => [ $this, 'can_manage' ],
				'callback'            => [ $this, 'show' ],
			]
		);
	}

	/**
	 * Resumen.
	 */
	public function show(): WP_REST_Response|WP_Error {
		return $this->handle( fn(): WP_REST_Response => $this->ok( $this->dashboard->summary() ) );
	}
}
