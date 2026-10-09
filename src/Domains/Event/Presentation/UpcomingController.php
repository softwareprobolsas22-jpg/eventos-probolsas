<?php
/**
 * Próximos eventos.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Presentation;

use Probolsas\Eventos\Domains\Event\Application\CalendarService;
use Probolsas\Eventos\Shared\Cache\ResponseCache;
use Probolsas\Eventos\Shared\Http\RestController;
use Probolsas\Eventos\Shared\Time\DateFormatter;
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
	 * @param ResponseCache   $cache     Caché de las respuestas (H-401).
	 * @param DateFormatter   $dates     «Hoy» de Colombia, parte de la clave (`is_past` y el rango cambian a medianoche).
	 */
	public function __construct(
		private readonly CalendarService $calendar,
		private readonly EventPresenter $presenter,
		private readonly ResponseCache $cache,
		private readonly DateFormatter $dates
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
		$params = [
			'limit' => self::text_param( $request->get_param( 'limit' ) ),
			'types' => self::list_param( $request->get_param( 'types' ) ),
		];

		return $this->handle(
			fn(): WP_REST_Response => $this->ok(
				$this->cache->remember(
					'upcoming',
					[
						...$params,
						'today' => $this->dates->today(),
					],
					fn(): array => array_map( [ $this->presenter, 'present' ], $this->calendar->upcoming( $params ) )
				)
			)
		);
	}
}
