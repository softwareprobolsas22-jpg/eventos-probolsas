<?php
/**
 * API REST de tipos de evento.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\EventType\Presentation;

use Probolsas\Eventos\Domains\EventType\Application\EventTypeService;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeSummary;
use Probolsas\Eventos\Shared\Http\RestController;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Ui\ColorContrast;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Endpoints `eventos/v1/event-types` (contrato en docs/api/event-types.md).
 */
final class EventTypeRestController extends RestController {

	/**
	 * Crea el controlador.
	 *
	 * @param EventTypeService $service  Servicio de tipos de evento.
	 * @param DateFormatter    $dates    Fechas.
	 * @param ColorContrast    $contrast Contraste de colores (tono de texto legible).
	 */
	public function __construct(
		private readonly EventTypeService $service,
		private readonly DateFormatter $dates,
		private readonly ColorContrast $contrast
	) {}

	/**
	 * Segmento base.
	 */
	protected function rest_base(): string {
		return 'event-types';
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
					'permission_callback' => [ $this, 'can_view' ],
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
			'/(?P<id>\d+)',
			[
				[
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

		$this->add_route(
			'/order',
			[
				'methods'             => 'PUT',
				'permission_callback' => [ $this, 'can_manage' ],
				'callback'            => [ $this, 'reorder' ],
			]
		);
	}

	/**
	 * Listado.
	 */
	public function index(): WP_REST_Response|WP_Error {
		return $this->handle( fn(): WP_REST_Response => $this->ok( array_map( [ $this, 'present' ], $this->service->list() ) ) );
	}

	/**
	 * Detalle.
	 *
	 * @param WP_REST_Request $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->handle( fn(): WP_REST_Response => $this->ok( $this->present( $this->service->get( (int) $request['id'] ) ) ) );
	}

	/**
	 * Creación.
	 *
	 * @param WP_REST_Request $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function store( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->handle( fn(): WP_REST_Response => $this->ok( $this->present( $this->service->create( $this->input( $request ) ) ), 201 ) );
	}

	/**
	 * Actualización.
	 *
	 * @param WP_REST_Request $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->handle( fn(): WP_REST_Response => $this->ok( $this->present( $this->service->update( (int) $request['id'], $this->input( $request ) ) ) ) );
	}

	/**
	 * Eliminación.
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
	 * Nuevo orden: `{ ids: [3, 1, 2, 4] }` con todos los tipos. Responde el listado ya ordenado.
	 *
	 * @param WP_REST_Request $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function reorder( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$ids = $request->get_param( 'ids' );

		return $this->handle( fn(): WP_REST_Response => $this->ok( array_map( [ $this, 'present' ], $this->service->reorder( $ids ) ) ) );
	}

	/**
	 * Representación pública de un tipo (docs/api/event-types.md).
	 *
	 * @param EventTypeSummary $summary Tipo con su conteo de eventos.
	 *
	 * @return array<string, mixed>
	 */
	public function present( EventTypeSummary $summary ): array {
		$type = $summary->type;

		return [
			'id'                  => $type->id,
			'name'                => $type->name,
			'slug'                => $type->slug,
			'color'               => $type->color,
			'text_tone'           => $this->contrast->readable_tone( $type->color ),
			'icon'                => $type->icon,
			'requires_attachment' => $type->requires_attachment,
			'description'         => $type->description,
			'sort_order'          => $type->sort_order,
			'events_count'        => $summary->events_count,
			'created_at'          => $this->dates->to_iso( $type->created_at_gmt ),
			'updated_at'          => $this->dates->to_iso( $type->updated_at_gmt ),
		];
	}

	/**
	 * Datos recibidos (cuerpo JSON incluido), saneados: sin etiquetas HTML ni caracteres de control.
	 *
	 * @param WP_REST_Request $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return array<string, mixed>
	 */
	private function input( WP_REST_Request $request ): array {
		$body = $request->get_params();
		$text = static fn( string $key ): string => is_scalar( $body[ $key ] ?? null ) ? sanitize_text_field( (string) $body[ $key ] ) : '';

		return [
			'name'                => $text( 'name' ),
			'color'               => $text( 'color' ),
			'icon'                => $text( 'icon' ),
			'requires_attachment' => $this->boolean_text( $body['requires_attachment'] ?? null ),
			'description'         => is_scalar( $body['description'] ?? null ) ? sanitize_textarea_field( (string) $body['description'] ) : '',
			'sort_order'          => $text( 'sort_order' ),
		];
	}

	/**
	 * «Requiere adjunto» como texto que entiende el servicio: `1`, `0`, `true`, `false` o vacío (no enviado).
	 * Un JSON con `true`/`false` llega como booleano; un formulario, como texto.
	 *
	 * @param mixed $value Valor recibido.
	 */
	private function boolean_text( mixed $value ): string {
		// No enviado: el servicio aplica el valor por defecto (no requiere adjunto).
		if ( null === $value ) {
			return '';
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		return is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : 'no-válido';
	}
}
