<?php
/**
 * Pruebas unitarias de la API de tipos de evento.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Domains\EventType;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Domains\EventType\Application\EventTypeService;
use Probolsas\Eventos\Domains\EventType\Presentation\EventTypeRestController;
use Probolsas\Eventos\Shared\Text\Slugger;
use Probolsas\Eventos\Shared\Text\TextNormalizer;
use Probolsas\Eventos\Shared\Time\Clock;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Ui\ColorContrast;
use Probolsas\Eventos\Shared\Ui\IconCatalog;
use Probolsas\Eventos\Tests\Unit\Support\InMemoryEventTypeRepository;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Representación del contrato (docs/api/event-types.md), códigos HTTP y saneamiento de la entrada.
 * Permisos y rutas contra la API REST real de WordPress: tests/php/Integration/EventTypeApiTest.php.
 *
 * @covers \Probolsas\Eventos\Domains\EventType\Presentation\EventTypeRestController
 */
final class EventTypeRestControllerTest extends UnitTestCase {

	/**
	 * Repositorio en memoria.
	 *
	 * @var InMemoryEventTypeRepository
	 */
	private InMemoryEventTypeRepository $repository;

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'sanitize_text_field' )->alias( [ self::class, 'strip' ] );
		Functions\when( 'sanitize_textarea_field' )->alias( [ self::class, 'strip' ] );
		$this->repository = new InMemoryEventTypeRepository();
	}

	public function test_routes_follow_the_contract_permissions(): void {
		$routes = [];
		Functions\when( 'register_rest_route' )->alias(
			static function ( string $route_namespace, string $route, array $endpoints ) use ( &$routes ): void {
				$routes[ $route ] = isset( $endpoints['methods'] ) ? [ $endpoints ] : $endpoints;
			}
		);

		$controller = $this->controller();
		$controller->register_routes();

		$permissions = [];
		foreach ( $routes as $route => $endpoints ) {
			foreach ( $endpoints as $endpoint ) {
				$permissions[] = $endpoint['methods'] . ' ' . $route . ' → ' . $endpoint['permission_callback'][1];
			}
		}

		$this->assertSame(
			[
				'GET /event-types → can_view',
				'POST /event-types → can_manage',
				'GET /event-types/(?P<id>\d+) → can_view',
				'PUT /event-types/(?P<id>\d+) → can_manage',
				'DELETE /event-types/(?P<id>\d+) → can_manage',
				'PUT /event-types/order → can_manage',
			],
			$permissions
		);
	}

	public function test_store_returns_201_with_the_contract_representation(): void {
		$response = $this->controller()->store(
			new WP_REST_Request(
				[
					'name'                => '  <b>Cumpleaños</b> ',
					'color'               => '#9d174d',
					'icon'                => 'cake-candles',
					'requires_attachment' => true,
					'description'         => 'Celebraciones del mes',
				]
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );
		$this->assertSame(
			[
				'id'                  => 1,
				'name'                => 'Cumpleaños',
				'slug'                => 'cumpleanos',
				'color'               => '#9D174D',
				'text_tone'           => 'light',
				'icon'                => 'cake-candles',
				'requires_attachment' => true,
				'description'         => 'Celebraciones del mes',
				'sort_order'          => 1,
				'events_count'        => 0,
				'created_at'          => '2026-10-07T20:30:00-05:00',
				'updated_at'          => '2026-10-07T20:30:00-05:00',
			],
			$response->get_data()['data']
		);
	}

	public function test_light_colors_get_dark_text(): void {
		$data = $this->controller()->store( new WP_REST_Request( $this->body( [ 'color' => '#669F30' ] ) ) )->get_data()['data'];

		$this->assertSame( 'dark', $data['text_tone'] );
	}

	/**
	 * @return array<string, array{mixed, bool}>
	 */
	public static function boolean_forms(): array {
		return [
			'booleano true'  => [ true, true ],
			'booleano false' => [ false, false ],
			'texto 1'        => [ '1', true ],
			'texto TRUE'     => [ 'TRUE', true ],
			'número 0'       => [ 0, false ],
			'no enviado'     => [ null, false ],
		];
	}

	/**
	 * @dataProvider boolean_forms
	 *
	 * @param mixed $value    Valor recibido.
	 * @param bool  $expected Resultado.
	 */
	public function test_requires_attachment_accepts_json_and_form_values( mixed $value, bool $expected ): void {
		$data = $this->controller()->store( new WP_REST_Request( $this->body( [ 'requires_attachment' => $value ] ) ) )->get_data()['data'];

		$this->assertSame( $expected, $data['requires_attachment'] );
	}

	public function test_non_scalar_values_are_rejected_instead_of_ignored(): void {
		$error = $this->controller()->store( new WP_REST_Request( $this->body( [ 'requires_attachment' => [ 'x' ] ] ) ) );

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 422, $error->get_error_data()['status'] );
		$this->assertArrayHasKey( 'requires_attachment', $error->get_error_data()['errors'] );
	}

	public function test_invalid_input_returns_422_with_field_errors(): void {
		$error = $this->controller()->store(
			new WP_REST_Request(
				$this->body(
					[
						'name'  => '',
						'color' => 'verde',
					]
				)
			)
		);

		$this->assertSame( 'eventos_validation_failed', $error->get_error_code() );
		$this->assertSame( [ 'name', 'color' ], array_keys( $error->get_error_data()['errors'] ) );
	}

	public function test_index_show_update_reorder_and_destroy(): void {
		$controller = $this->controller();
		$controller->store( new WP_REST_Request( $this->body( [ 'name' => 'A' ] ) ) );
		$controller->store( new WP_REST_Request( $this->body( [ 'name' => 'B' ] ) ) );

		$this->assertSame( [ 'A', 'B' ], array_column( $controller->index()->get_data()['data'], 'name' ) );
		$this->assertSame( 'B', $controller->show( new WP_REST_Request( [ 'id' => '2' ] ) )->get_data()['data']['name'] );
		$this->assertSame(
			'B2',
			$controller->update(
				new WP_REST_Request(
					$this->body(
						[
							'id'   => '2',
							'name' => 'B2',
						]
					)
				)
			)->get_data()['data']['name']
		);
		$this->assertSame( [ 'B2', 'A' ], array_column( $controller->reorder( new WP_REST_Request( [ 'ids' => [ 2, 1 ] ] ) )->get_data()['data'], 'name' ) );
		$this->assertSame(
			[
				'deleted' => true,
				'id'      => 1,
			],
			$controller->destroy( new WP_REST_Request( [ 'id' => '1' ] ) )->get_data()['data']
		);
		$this->assertSame( [ 'B2' ], array_column( $controller->index()->get_data()['data'], 'name' ) );
	}

	public function test_missing_and_in_use_types_return_404_and_409(): void {
		$controller = $this->controller();
		$controller->store( new WP_REST_Request( $this->body() ) );
		$this->repository->events = [ 1 => 2 ];

		$this->assertSame( 404, $controller->show( new WP_REST_Request( [ 'id' => '9' ] ) )->get_error_data()['status'] );
		$this->assertSame( 409, $controller->destroy( new WP_REST_Request( [ 'id' => '1' ] ) )->get_error_data()['status'] );
	}

	/**
	 * Cuerpo válido con cambios.
	 *
	 * @param array<string, mixed> $changes Cambios.
	 *
	 * @return array<string, mixed>
	 */
	private function body( array $changes = [] ): array {
		return array_merge(
			[
				'name'  => 'Cumpleaños',
				'color' => '#9D174D',
				'icon'  => 'cake-candles',
			],
			$changes
		);
	}

	/**
	 * Controlador con reloj fijo (8:30 p. m. del 7 de octubre en Bogotá).
	 */
	private function controller(): EventTypeRestController {
		$config = new Config(
			[
				'ui'    => [ 'timezone' => 'America/Bogota' ],
				'icons' => [ 'cake-candles' => [ 'Pastel', '' ] ],
			]
		);
		$clock  = new class() implements Clock {
			public function now(): DateTimeImmutable {
				return new DateTimeImmutable( '2026-10-08 01:30:00', new DateTimeZone( 'UTC' ) );
			}
		};
		$dates  = DateFormatter::from_config( $config, $clock );

		return new EventTypeRestController(
			new EventTypeService( $this->repository, new TextNormalizer(), new Slugger( new TextNormalizer() ), $dates, new IconCatalog( $config ) ),
			$dates,
			new ColorContrast()
		);
	}

	/**
	 * Doble de sanitize_text_field / sanitize_textarea_field: quita etiquetas y espacios de los extremos.
	 *
	 * @param string $value Texto.
	 */
	public static function strip( string $value ): string {
		return trim( strip_tags( $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Doble de la función de WordPress.
	}
}
