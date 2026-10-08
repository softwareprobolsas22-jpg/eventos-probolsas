<?php
/**
 * API REST de eventos.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Presentation;

use Probolsas\Eventos\Domains\Event\Application\EventService;
use Probolsas\Eventos\Domains\Event\Domain\Event;
use Probolsas\Eventos\Domains\EventType\Domain\EventType;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeRepository;
use Probolsas\Eventos\Domains\Media\Domain\AttachmentGateway;
use Probolsas\Eventos\Shared\Http\RestApi;
use Probolsas\Eventos\Shared\Http\RestController;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Ui\ColorContrast;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Endpoints `eventos/v1/events` de gestión y detalle (contrato en docs/api/events.md). El feed del
 * calendario, el .ics y los próximos eventos llegan en H-301.
 */
final class EventRestController extends RestController {

	/**
	 * Ruta de la exportación, para servirla como archivo y no como JSON.
	 */
	public const EXPORT_ROUTE = '/' . RestApi::NAMESPACE_V1 . '/events/export.csv';

	/**
	 * Parámetros de búsqueda que acepta el listado y la exportación.
	 */
	private const QUERY_PARAMS = [ 'page', 'per_page', 'search', 'type', 'date_from', 'date_to', 'orderby', 'order' ];

	/**
	 * Campos de texto de una línea del cuerpo de POST y PUT.
	 */
	private const TEXT_FIELDS = [ 'title', 'type_id', 'start_date', 'start_time', 'attachment_id', 'end_date', 'end_time' ];

	/**
	 * Tipos ya leídos en esta petición.
	 *
	 * @var array<int, EventType|null>
	 */
	private array $types_cache = [];

	/**
	 * Nombres de usuario ya leídos en esta petición.
	 *
	 * @var array<int, array{id: int, name: string}|null>
	 */
	private array $users_cache = [];

	/**
	 * Crea el controlador.
	 *
	 * @param EventService        $service     Servicio de eventos.
	 * @param EventTypeRepository $types       Tipos de evento.
	 * @param AttachmentGateway   $attachments Biblioteca de Medios.
	 * @param EventCsvExport      $csv         Exportación CSV.
	 * @param DateFormatter       $dates       Fechas.
	 * @param ColorContrast       $contrast    Tono de texto legible del tipo.
	 */
	public function __construct(
		private readonly EventService $service,
		private readonly EventTypeRepository $types,
		private readonly AttachmentGateway $attachments,
		private readonly EventCsvExport $csv,
		private readonly DateFormatter $dates,
		private readonly ColorContrast $contrast
	) {}

	/**
	 * Registra las rutas y la entrega del CSV como archivo.
	 */
	public function register(): void {
		parent::register();
		add_filter( 'rest_pre_serve_request', [ $this, 'serve_csv' ], 10, 3 );
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
			'',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => [ $this, 'can_manage' ],
					'callback'            => [ $this, 'index' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => [ $this, 'can_manage' ],
					'callback'            => [ $this, 'store' ],
				],
			]
		);

		$this->add_route(
			'/export\.csv',
			[
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => [ $this, 'can_manage' ],
				'callback'            => [ $this, 'export' ],
			]
		);

		$this->add_route(
			'/(?P<id>\d+)',
			[
				[
					// Única definición de la ruta (QA-014): la usan el modal del calendario y el detalle de wp-admin.
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => [ $this, 'can_view' ],
					'callback'            => [ $this, 'show' ],
				],
				[
					'methods'             => 'PUT',
					'permission_callback' => [ $this, 'can_manage' ],
					'callback'            => [ $this, 'update' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'permission_callback' => [ $this, 'can_manage' ],
					'callback'            => [ $this, 'destroy' ],
				],
			]
		);
	}

	/**
	 * Listado paginado con filtros. Cabeceras `X-WP-Total` y `X-WP-TotalPages`.
	 *
	 * @param WP_REST_Request $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->handle(
			function () use ( $request ): WP_REST_Response {
				$page     = $this->service->search( $this->service->query( $this->query_params( $request ) ) );
				$response = $this->ok( array_map( [ $this, 'present' ], $page->events ) );
				$response->header( 'X-WP-Total', (string) $page->total );
				$response->header( 'X-WP-TotalPages', (string) $page->total_pages() );

				return $response;
			}
		);
	}

	/**
	 * Detalle con los eventos del mismo día (navegación del modal y del detalle).
	 *
	 * @param WP_REST_Request $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->handle(
			function () use ( $request ): WP_REST_Response {
				$event    = $this->service->get( (int) $request['id'] );
				$same_day = array_map(
					static fn( Event $other ): array => [
						'id'         => $other->id,
						'title'      => $other->title,
						'start_time' => $other->schedule->start_time,
					],
					$this->service->same_day( $event )
				);

				return $this->ok(
					[
						...$this->present( $event ),
						'same_day' => $same_day,
					]
				);
			}
		);
	}

	/**
	 * Creación.
	 *
	 * @param WP_REST_Request $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function store( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->handle( fn(): WP_REST_Response => $this->ok( $this->present( $this->service->create( $this->input( $request ), get_current_user_id() ) ), 201 ) );
	}

	/**
	 * Actualización.
	 *
	 * @param WP_REST_Request $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->handle( fn(): WP_REST_Response => $this->ok( $this->present( $this->service->update( (int) $request['id'], $this->input( $request ), get_current_user_id() ) ) ) );
	}

	/**
	 * Eliminación. El adjunto se queda en la Biblioteca de Medios (D-4).
	 *
	 * @param WP_REST_Request $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function destroy( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->handle(
			function () use ( $request ): WP_REST_Response {
				$id = (int) $request['id'];
				$this->service->delete( $id );

				return $this->ok(
					[
						'deleted' => true,
						'id'      => $id,
					]
				);
			}
		);
	}

	/**
	 * Exportación CSV con los mismos filtros del listado, sin paginar.
	 *
	 * @param WP_REST_Request $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function export( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->handle(
			function () use ( $request ): WP_REST_Response {
				$query = $this->service->query( $this->query_params( $request ) );
				$body  = $this->csv->build(
					$this->service->all_matching( $query ),
					fn( int $id ): string => $this->type( $id )->name ?? '',
					fn( int $id ): string => $this->user( $id )['name'] ?? ''
				);

				$response = new WP_REST_Response( $body, 200 );
				$response->header( 'Content-Type', 'text/csv; charset=utf-8' );
				$response->header( 'Content-Disposition', 'attachment; filename="eventos-' . $this->dates->today() . '.csv"' );
				$response->header( 'Cache-Control', 'no-store, private' );

				return $response;
			}
		);
	}

	/**
	 * Entrega la exportación como archivo: WordPress codificaría el texto como JSON. Las cabeceras de la
	 * respuesta (tipo y nombre del archivo) ya las envió el servidor REST antes de este filtro.
	 *
	 * @param bool                   $served  Si otra extensión ya entregó la respuesta.
	 * @param WP_HTTP_Response|mixed $result  Respuesta.
	 * @param WP_REST_Request        $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function serve_csv( bool $served, mixed $result, WP_REST_Request $request ): bool {
		if ( $served || self::EXPORT_ROUTE !== $request->get_route() || ! $result instanceof WP_HTTP_Response || 200 !== $result->get_status() || ! is_string( $result->get_data() ) ) {
			return $served;
		}

		echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Archivo CSV (text/csv), no HTML; las celdas se protegen contra fórmulas en EventCsvExport.

		return true;
	}

	/**
	 * Representación pública de un evento (docs/api/events.md).
	 *
	 * @param Event $event Evento.
	 *
	 * @return array<string, mixed>
	 */
	public function present( Event $event ): array {
		$schedule   = $event->schedule;
		$type       = $this->type( $event->type_id );
		$attachment = null === $event->attachment_id ? null : $this->attachments->find( $event->attachment_id );

		return [
			'id'          => $event->id,
			'title'       => $event->title,
			'description' => $event->description,
			'type'        => null === $type ? null : [
				'id'        => $type->id,
				'name'      => $type->name,
				'slug'      => $type->slug,
				'color'     => $type->color,
				'text_tone' => $this->contrast->readable_tone( $type->color ),
				'icon'      => $type->icon,
			],
			'start_date'  => $schedule->start_date,
			'start_time'  => $schedule->start_time,
			'end_date'    => $schedule->end_date,
			'end_time'    => $schedule->end_time,
			'all_day'     => $schedule->is_all_day(),
			'is_past'     => $this->service->is_past( $event ),
			'attachment'  => $attachment?->to_array(),
			'created_by'  => $this->user( $event->created_by ),
			'updated_by'  => $this->user( $event->updated_by ),
			'created_at'  => $this->dates->to_iso( $event->created_at_gmt ),
			'updated_at'  => $this->dates->to_iso( $event->updated_at_gmt ),
		];
	}

	/**
	 * Parámetros de búsqueda recibidos, como texto saneado.
	 *
	 * @param WP_REST_Request $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return array<string, string>
	 */
	private function query_params( WP_REST_Request $request ): array {
		$params = [];

		foreach ( self::QUERY_PARAMS as $key ) {
			$value          = $request->get_param( $key );
			$params[ $key ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		}

		return $params;
	}

	/**
	 * Cuerpo de POST y PUT, saneado: sin etiquetas HTML ni caracteres de control.
	 *
	 * @param WP_REST_Request $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return array<string, string>
	 */
	private function input( WP_REST_Request $request ): array {
		$body  = $request->get_params();
		$input = [];

		foreach ( self::TEXT_FIELDS as $key ) {
			$input[ $key ] = is_scalar( $body[ $key ] ?? null ) ? sanitize_text_field( (string) $body[ $key ] ) : '';
		}

		$input['description'] = is_scalar( $body['description'] ?? null ) ? sanitize_textarea_field( (string) $body['description'] ) : '';

		return $input;
	}

	/**
	 * Tipo por ID, leído una sola vez por petición.
	 *
	 * @param int $id ID del tipo.
	 */
	private function type( int $id ): ?EventType {
		if ( ! array_key_exists( $id, $this->types_cache ) ) {
			$this->types_cache[ $id ] = $this->types->find( $id );
		}

		return $this->types_cache[ $id ];
	}

	/**
	 * Usuario de WordPress por ID (`null` si fue eliminado), leído una sola vez por petición.
	 *
	 * @param int $id ID del usuario.
	 *
	 * @return array{id: int, name: string}|null
	 */
	private function user( int $id ): ?array {
		if ( ! array_key_exists( $id, $this->users_cache ) ) {
			$user                     = $id > 0 ? get_userdata( $id ) : false;
			$this->users_cache[ $id ] = false === $user ? null : [
				'id'   => $id,
				'name' => (string) $user->display_name,
			];
		}

		return $this->users_cache[ $id ];
	}
}
