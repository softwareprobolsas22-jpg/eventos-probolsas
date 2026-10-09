<?php
/**
 * Descarga del .ics de un evento.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Presentation;

use Probolsas\Eventos\Domains\Event\Application\EventService;
use Probolsas\Eventos\Shared\Http\RestApi;
use Probolsas\Eventos\Shared\Http\RestController;
use Probolsas\Eventos\Shared\Time\Clock;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * `GET eventos/v1/events/{id}/ics`: «Añadir a mi calendario» (docs/api/events.md). El navegador lo descarga
 * con un enlace y el nonce en la dirección (`?_wpnonce=…`), igual que el CSV, porque un enlace no envía la
 * cabecera `X-WP-Nonce`.
 */
final class IcsController extends RestController {

	/**
	 * Rutas que se entregan como archivo y no como JSON.
	 */
	private const ROUTE_PATTERN = '#^/' . RestApi::NAMESPACE_V1 . '/events/\d+/ics$#';

	/**
	 * Crea el controlador.
	 *
	 * @param EventService   $service   Servicio de eventos.
	 * @param EventPresenter $presenter Tipo de cada evento.
	 * @param IcsCalendar    $ics       Generador del archivo.
	 * @param Clock          $clock     Reloj (`DTSTAMP`).
	 */
	public function __construct(
		private readonly EventService $service,
		private readonly EventPresenter $presenter,
		private readonly IcsCalendar $ics,
		private readonly Clock $clock
	) {}

	/**
	 * Registra la ruta y la entrega del archivo.
	 */
	public function register(): void {
		parent::register();
		add_filter( 'rest_pre_serve_request', [ $this, 'serve' ], 10, 3 );
	}

	/**
	 * Segmento base.
	 */
	protected function rest_base(): string {
		return 'events';
	}

	/**
	 * Rutas del recurso.
	 */
	public function register_routes(): void {
		$this->add_route(
			'/(?P<id>\d+)/ics',
			[
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => [ $this, 'can_view' ],
				'callback'            => [ $this, 'show' ],
			]
		);
	}

	/**
	 * Archivo `.ics` del evento.
	 *
	 * @param WP_REST_Request $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->handle(
			function () use ( $request ): WP_REST_Response {
				$event    = $this->service->get( (int) $request['id'] );
				$response = new WP_REST_Response( $this->ics->build( $event, $this->presenter->type( $event->type_id )->name ?? '', $this->clock->now() ), 200 );
				$response->header( 'Content-Type', 'text/calendar; charset=utf-8' );
				$response->header( 'Content-Disposition', 'attachment; filename="' . $this->ics->filename( $event ) . '"' );
				$response->header( 'Cache-Control', 'no-store, private' );
				$response->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );

				return $response;
			}
		);
	}

	/**
	 * Entrega el archivo tal cual: WordPress codificaría el texto como JSON. Las cabeceras ya las envió el
	 * servidor REST antes de este filtro.
	 *
	 * @param bool                   $served  Si otra extensión ya entregó la respuesta.
	 * @param WP_HTTP_Response|mixed $result  Respuesta.
	 * @param WP_REST_Request        $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function serve( bool $served, mixed $result, WP_REST_Request $request ): bool {
		if ( $served || 1 !== preg_match( self::ROUTE_PATTERN, $request->get_route() ) || ! $result instanceof WP_HTTP_Response || 200 !== $result->get_status() || ! is_string( $result->get_data() ) ) {
			return $served;
		}

		echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Archivo iCalendar (text/calendar), no HTML; el texto se escapa según RFC 5545 en IcsCalendar.

		return true;
	}
}
