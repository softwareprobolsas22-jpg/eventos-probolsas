<?php
/**
 * Pruebas de integración de la API de eventos.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Integration;

use Probolsas\Eventos\Core\Security\Capabilities;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Contrato de docs/api/events.md contra WordPress y MySQL reales (H-201): CRUD, permisos, filtros con
 * solapamiento de rango, paginación, adjuntos de la Biblioteca de Medios (R-09, D-4) y exportación CSV.
 *
 * @coversNothing
 */
final class EventApiTest extends IntegrationTestCase {

	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

	/**
	 * Tipos iniciales por nombre.
	 *
	 * @var array<string, int>
	 */
	private array $types = [];

	public function set_up(): void {
		parent::set_up();
		$this->plugin()->activate();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Servidor REST de WordPress para la prueba.
		do_action( 'rest_api_init', $wp_rest_server ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook de WordPress.

		$manager = self::factory()->user->create_and_get(
			[
				'role'         => 'editor',
				'display_name' => 'Talento Humano',
			]
		);
		$manager->add_cap( Capabilities::MANAGE );
		wp_set_current_user( $manager->ID );

		$types = $this->request( 'GET', '/event-types' )->get_data()['data'];
		foreach ( $types as $type ) {
			$this->types[ $type['name'] ] = (int) $type['id'];
		}
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Restablece el servidor REST.

		parent::tear_down();
	}

	public function test_a_fresh_install_accepts_an_event_of_each_type(): void {
		$image = $this->upload( 'ana.png', (string) base64_decode( self::PNG ), 'image/png' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Imagen de prueba.

		foreach ( $this->types as $name => $id ) {
			$response = $this->create(
				[
					'title'         => "Evento de {$name}",
					'type_id'       => $id,
					'attachment_id' => $image,
				]
			);
			$this->assertSame( 201, $response->get_status(), "RL-05: {$name}" );
		}
	}

	public function test_create_show_update_and_delete(): void {
		$image   = $this->upload( 'ana.png', (string) base64_decode( self::PNG ), 'image/png' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Imagen de prueba.
		$created = $this->create( [ 'attachment_id' => $image ] );

		$this->assertSame( 201, $created->get_status() );
		$data = $created->get_data()['data'];
		$this->assertSame( '2026-10-07', $data['start_date'], 'R-08: la fecha no se corre.' );
		$this->assertSame( '15:00', $data['start_time'] );
		$this->assertSame( 'Cumpleaños', $data['type']['name'] );
		$this->assertSame( 'image', $data['attachment']['kind'] );
		$this->assertSame( 'Talento Humano', $data['created_by']['name'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}-05:00$/', $data['created_at'] );

		$id   = (int) $data['id'];
		$show = $this->request( 'GET', "/events/{$id}" )->get_data()['data'];
		$this->assertSame(
			[
				[
					'id'         => $id,
					'title'      => 'Cumpleaños de Ana María',
					'start_time' => '15:00',
				],
			],
			$show['same_day']
		);

		$updated = $this->request(
			'PUT',
			"/events/{$id}",
			[
				'title'      => 'Reunión de planeación',
				'type_id'    => $this->types['Reuniones laborales'],
				'start_date' => '2026-10-08',
				'start_time' => '',
			]
		);
		$this->assertSame( 200, $updated->get_status() );
		$this->assertTrue( $updated->get_data()['data']['all_day'] );
		$this->assertNull( $updated->get_data()['data']['attachment'] );

		$this->assertSame( 200, $this->request( 'DELETE', "/events/{$id}" )->get_status() );
		$this->assertSame( 404, $this->request( 'GET', "/events/{$id}" )->get_status() );
	}

	public function test_deleting_an_event_keeps_its_file_in_the_library(): void {
		$image = $this->upload( 'ana.png', (string) base64_decode( self::PNG ), 'image/png' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Imagen de prueba.
		$file  = (string) get_attached_file( $image );
		$id    = (int) $this->create( [ 'attachment_id' => $image ] )->get_data()['data']['id'];

		$this->request( 'DELETE', "/events/{$id}" );

		$this->assertNotNull( get_post( $image ), 'D-4 / RL-07: el adjunto sigue en la biblioteca.' );
		$this->assertFileExists( $file );
	}

	public function test_attachments_must_be_real_images_or_pdfs_and_required_when_the_type_says_so(): void {
		$fake_pdf = $this->upload( 'manual.pdf', 'MZ' . str_repeat( "\0", 62 ) . 'This program cannot be run in DOS mode.', 'application/pdf' );

		$required = $this->create( [ 'attachment_id' => '' ] );
		$this->assertSame( 422, $required->get_status() );
		$this->assertSame( [ 'Este tipo de evento requiere una imagen o un PDF.' ], $required->get_data()['data']['errors']['attachment_id'] );

		$executable = $this->create( [ 'attachment_id' => $fake_pdf ] );
		$this->assertSame( [ 'El archivo debe ser una imagen o un PDF.' ], $executable->get_data()['data']['errors']['attachment_id'], 'R-09: .exe renombrado a .pdf.' );

		$post = $this->create( [ 'attachment_id' => self::factory()->post->create() ] );
		$this->assertSame( [ 'El archivo elegido ya no existe en la Biblioteca de Medios.' ], $post->get_data()['data']['errors']['attachment_id'] );

		$optional = $this->create(
			[
				'type_id'       => $this->types['Reuniones laborales'],
				'attachment_id' => '',
			]
		);
		$this->assertSame( 201, $optional->get_status() );
	}

	public function test_validation_follows_the_contract(): void {
		$response = $this->create(
			[
				'title'      => 'Ab',
				'type_id'    => 999,
				'start_date' => '2026-02-30',
				'start_time' => '25:00',
				'end_date'   => '2026-10-08',
			]
		);

		$this->assertSame( 422, $response->get_status() );
		$errors = $response->get_data()['data']['errors'];
		$this->assertSame( [ 'title', 'type_id', 'start_date', 'start_time', 'end_date' ], array_keys( $errors ) );
	}

	public function test_filters_search_by_words_type_and_overlapping_range_with_pagination(): void {
		$meeting = $this->types['Reuniones laborales'];
		$this->create( $this->meeting( 'Reunión de planeación', '2026-10-01' ) );
		$this->create( $this->meeting( 'Planeación anual', '2026-10-15' ) );
		$this->create( $this->meeting( 'Comité de calidad', '2026-11-03' ) );

		$search = $this->request( 'GET', '/events', [], [ 'search' => 'REUNION planeacion' ] );
		$this->assertSame( [ 'Reunión de planeación' ], array_column( $search->get_data()['data'], 'title' ), 'Sin distinguir mayúsculas ni tildes, todas las palabras.' );

		$october = $this->request(
			'GET',
			'/events',
			[],
			[
				'type'      => $meeting,
				'date_from' => '2026-10-01',
				'date_to'   => '2026-10-31',
				'orderby'   => 'title',
			]
		);
		$this->assertSame( [ 'Planeación anual', 'Reunión de planeación' ], array_column( $october->get_data()['data'], 'title' ) );
		$this->assertSame( '2', $october->get_headers()['X-WP-Total'] );

		$this->assertSame( 422, $this->request( 'GET', '/events', [], [ 'per_page' => 10 ] )->get_status(), 'R-23: solo 25, 50 o 100.' );
		$this->assertSame( [], $this->request( 'GET', '/events', [], [ 'page' => 9 ] )->get_data()['data'] );
	}

	public function test_export_csv_uses_the_active_filters(): void {
		$this->create( $this->meeting( 'Reunión de planeación', '2026-10-01' ) );
		$this->create( $this->meeting( 'Comité de calidad', '2026-11-03' ) );

		$response = $this->request(
			'GET',
			'/events/export.csv',
			[],
			[
				'date_from' => '2026-10-01',
				'date_to'   => '2026-10-31',
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'text/csv; charset=utf-8', $response->get_headers()['Content-Type'] );
		$csv = (string) $response->get_data();
		$this->assertStringStartsWith( "\xEF\xBB\xBFEvento;Tipo;Fecha;Hora;", $csv );
		$this->assertStringContainsString( '01/10/2026', $csv );
		$this->assertStringNotContainsString( 'Comité', $csv );
	}

	public function test_permissions(): void {
		$id = (int) $this->create( $this->meeting( 'Reunión de planeación', '2026-10-01' ) )->get_data()['data']['id'];

		wp_set_current_user( 0 );
		foreach ( [ [ 'GET', '/events' ], [ 'GET', "/events/{$id}" ], [ 'GET', '/events/export.csv' ], [ 'POST', '/events' ], [ 'DELETE', "/events/{$id}" ] ] as [ $method, $path ] ) {
			$this->assertSame( 401, $this->request( $method, $path, 'POST' === $method ? $this->meeting( 'X', '2026-10-01' ) : [] )->get_status(), "Visitante: {$method} {$path} (D-1)." );
		}

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertSame( 200, $this->request( 'GET', "/events/{$id}" )->get_status(), 'El detalle lo ve cualquier usuario con sesión (modal del calendario).' );
		foreach ( [ [ 'GET', '/events' ], [ 'GET', '/events/export.csv' ], [ 'PUT', "/events/{$id}" ], [ 'DELETE', "/events/{$id}" ] ] as [ $method, $path ] ) {
			$this->assertSame( 403, $this->request( $method, $path, 'PUT' === $method ? $this->meeting( 'X', '2026-10-01' ) : [] )->get_status(), "Sin eventos_manage: {$method} {$path}." );
		}
	}

	public function test_the_browser_receives_the_event_rules(): void {
		$config = $this->plugin()->container()->get( \Probolsas\Eventos\Core\Assets\Assets::class )->client_config();

		$this->assertSame( 150, $config['rules']['event']['title']['maxLength'] );
		$this->assertSame( 100, $config['rules']['event_type']['name']['maxLength'], 'Las reglas de cada dominio conviven.' );
	}

	/**
	 * Reunión laboral (no exige adjunto).
	 *
	 * @param string $title Título.
	 * @param string $date  Fecha.
	 *
	 * @return array<string, mixed>
	 */
	private function meeting( string $title, string $date ): array {
		return [
			'title'      => $title,
			'type_id'    => $this->types['Reuniones laborales'],
			'start_date' => $date,
			'start_time' => '09:00',
		];
	}

	/**
	 * Crea un evento (por defecto, un cumpleaños sin adjunto).
	 *
	 * @param array<string, mixed> $changes Cambios.
	 */
	private function create( array $changes = [] ): WP_REST_Response {
		return $this->request(
			'POST',
			'/events',
			[
				'title'       => 'Cumpleaños de Ana María',
				'type_id'     => $this->types['Cumpleaños'],
				'start_date'  => '2026-10-07',
				'start_time'  => '15:00',
				'description' => 'Celebración en la sala de juntas.',
				...$changes,
			]
		);
	}

	/**
	 * Sube un archivo a la Biblioteca de Medios.
	 *
	 * @param string $name     Nombre.
	 * @param string $contents Contenido.
	 * @param string $mime     Tipo MIME que registra WordPress.
	 */
	private function upload( string $name, string $contents, string $mime ): int {
		$upload = wp_upload_bits( $name, null, $contents );
		$id     = wp_insert_attachment(
			[
				'post_mime_type' => $mime,
				'post_title'     => pathinfo( $name, PATHINFO_FILENAME ),
				'post_status'    => 'inherit',
			],
			$upload['file']
		);
		$this->assertIsInt( $id );

		return $id;
	}

	/**
	 * Ejecuta una petición a la API del plugin.
	 *
	 * @param string               $method Método HTTP.
	 * @param string               $path   Ruta relativa al namespace.
	 * @param array<string, mixed> $body   Cuerpo JSON.
	 * @param array<string, mixed> $query  Parámetros de consulta.
	 */
	private function request( string $method, string $path, array $body = [], array $query = [] ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/eventos/v1' . $path );
		$request->set_query_params( $query );

		if ( [] !== $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return rest_do_request( $request );
	}
}
