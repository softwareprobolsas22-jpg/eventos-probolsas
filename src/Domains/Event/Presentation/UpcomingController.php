<?php
/**
 * Próximos eventos.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Presentation;

use Probolsas\Eventos\Domains\Event\Application\CalendarService;
use Probolsas\Eventos\Shared\Http\RestController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * `GET eventos/v1/upcoming?limit=&types[]=`: próximos eventos desde hoy en Colombia, con la misma
 * representación que `GET /events/{id}` sin `same_day` (lo usa `[eventos_proximos]`).
 */
final class UpcomingController extends RestController {

	/**
	 * Crea el controlador.
	 *
	 * @param CalendarService $calendar  Consultas del calendario.
	 * @param EventPresenter  $presenter Representación de los eventos.
	 */
	public function __construct(
		private readonly CalendarService $calendar,
		private readonly EventPresenter $presenter
	) {}

	/**
	 * Segmento base.
	 */
	protected function rest_base(): string {
		return 'upcoming';
	}

	/**
	 * Rutas del recurso.
	 */
	public function register_routes(): void {
		$this->add_route(
			'',
			[
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => [ $this, 'can_view' ],
				'callback'            => [ $this, 'index' ],
			]
		);
	}

	/**
	 * Próximos eventos.
	 *
	 * @param WP_REST_Request $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->handle(
			fn(): WP_REST_Response => $this->ok(
				array_map(
					[ $this->presenter, 'present' ],
					$this->calendar->upcoming(
						[
							'limit' => self::text_param( $request->get_param( 'limit' ) ),
							'types' => self::list_param( $request->get_param( 'types' ) ),
						]
					)
				)
			)
		);
	}
}
