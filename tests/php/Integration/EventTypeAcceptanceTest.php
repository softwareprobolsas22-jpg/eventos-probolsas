<?php
/**
 * Pruebas de aceptación de los tipos de evento (H-105, QA).
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Integration;

use Probolsas\Eventos\Core\Database\Tables;
use Probolsas\Eventos\Core\Security\Capabilities;
use Probolsas\Eventos\Shared\Ui\ColorContrast;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Criterios 2, 3, 4 y 8 del Sprint 1 (docs/scrum/sprint-1.md) contra WordPress y la base de datos reales:
 * la unicidad del nombre depende también del índice único de `name_key` y de su collation, que las
 * pruebas unitarias no ejercitan.
 *
 * @coversNothing
 */
final class EventTypeAcceptanceTest extends IntegrationTestCase {

	public function set_up(): void {
		parent::set_up();
		$this->plugin()->activate();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Servidor REST de WordPress para la prueba.
		do_action( 'rest_api_init', $wp_rest_server ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook de WordPress.

		$this->act_as_manager();
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Restablece el servidor REST.

		parent::tear_down();
	}

	/**
	 * Variantes de los nombres iniciales que deben contar como repetidas (criterio 3).
	 *
	 * @return array<string, array{string}>
	 */
	public static function duplicated_names(): array {
		return [
			'mayúsculas'            => [ 'CAPACITACIONES' ],
			'minúsculas'            => [ 'capacitaciones' ],
			'tilde agregada'        => [ 'Capacitaciónes' ],
			'eñe en mayúscula'      => [ 'CUMPLEAÑOS' ],
			'espacios sobrantes'    => [ '  Reuniones   especiales  ' ],
			'mayúsculas con tildes' => [ 'REUNIONES LÁBORALES' ],
		];
	}

	/**
	 * @dataProvider duplicated_names
	 *
	 * @param string $name Nombre que repite uno existente.
	 */
	public function test_a_repeated_name_is_rejected_ignoring_case_accents_and_spaces( string $name ): void {
		$response = $this->create( $name );

		$this->assertSame( 422, $response->get_status() );
		$this->assertArrayHasKey( 'name', $response->get_data()['data']['errors'] );
		$this->assertCount( 4, $this->list_types(), 'No se creó ningún tipo.' );
	}

	public function test_a_name_without_its_accents_is_the_same_name(): void {
		$this->assertSame( 201, $this->create( 'Integración' )->get_status() );

		$this->assertSame( 422, $this->create( 'INTEGRACION' )->get_status(), '«Capacitación» = «CAPACITACION» (criterio 3).' );
		$this->assertSame( 422, $this->create( 'integracion' )->get_status() );
	}

	public function test_the_enie_is_its_own_letter(): void {
		$response = $this->create( 'Cumpleanos' );

		$this->assertSame( 201, $response->get_status(), '«Cumpleanos» es otro nombre: la ñ es una letra propia (criterio 3).' );
		$this->assertSame( 'Cumpleanos', $response->get_data()['data']['name'] );
		$this->assertNotSame( 'cumpleanos', $response->get_data()['data']['slug'], 'El slug no choca con el de «Cumpleaños».' );

		$this->assertSame( 201, $this->create( 'Año nuevo' )->get_status() );
		$this->assertSame( 201, $this->create( 'Ano nuevo' )->get_status() );
		$this->assertSame( 422, $this->create( 'AÑO NUEVO' )->get_status() );
	}

	public function test_renaming_checks_uniqueness_against_the_other_types_only(): void {
		$birthday = $this->type_named( 'Cumpleaños' );

		$this->assertSame( 422, $this->update( $birthday['id'], 'REUNIONES LABORALES' )->get_status(), 'No se renombra igual que otro tipo.' );

		$own = $this->update( $birthday['id'], 'CUMPLEAÑOS' );
		$this->assertSame( 200, $own->get_status(), 'Cambiar solo las mayúsculas del propio nombre está permitido.' );
		$this->assertSame( 'CUMPLEAÑOS', $own->get_data()['data']['name'] );
		$this->assertSame( 'cumpleanos', $own->get_data()['data']['slug'] );
	}

	public function test_a_type_with_events_cannot_be_deleted_and_the_message_says_how_many(): void {
		$training = $this->type_named( 'Capacitaciones' );
		$this->insert_events( (int) $training['id'], 2 );

		$response = $this->request( 'DELETE', '/' . $training['id'] );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'eventos_conflict', $response->get_data()['code'] );
		$this->assertSame( 'No se puede eliminar «Capacitaciones» porque tiene 2 eventos asociados.', $response->get_data()['message'] );
		$this->assertSame( 2, $this->type_named( 'Capacitaciones' )['events_count'] );

		$this->delete_events( (int) $training['id'] );
		$this->assertSame( 200, $this->request( 'DELETE', '/' . $training['id'] )->get_status(), 'Sin eventos sí se elimina.' );
	}

	/**
	 * Colores extremos y medios: con el text_tone de la API, el badge siempre es legible (criterio 8, R-02).
	 *
	 * @return array<string, array{string}>
	 */
	public static function colors(): array {
		return [
			'blanco'             => [ '#FFFFFF' ],
			'negro'              => [ '#000000' ],
			'gris medio'         => [ '#777777' ],
			'gris del peor caso' => [ '#757575' ],
			'verde secundario'   => [ '#669F30' ],
			'amarillo claro'     => [ '#FDE68A' ],
			'azul saturado'      => [ '#0000FF' ],
		];
	}

	/**
	 * @dataProvider colors
	 *
	 * @param string $color Color del tipo.
	 */
	public function test_the_text_tone_is_always_readable( string $color ): void {
		$response = $this->create( 'Prueba de contraste', $color );
		$this->assertSame( 201, $response->get_status() );

		$data     = $response->get_data()['data'];
		$text     = 'light' === $data['text_tone'] ? '#FFFFFF' : '#000000';
		$contrast = new ColorContrast();

		$this->assertGreaterThanOrEqual( 4.5, $contrast->ratio( $data['color'], $text ) );
		$this->assertSame( $contrast->readable_tone( $color ), $data['text_tone'] );
	}

	public function test_the_initial_colors_use_white_text_with_aa_contrast(): void {
		$contrast = new ColorContrast();

		foreach ( $this->list_types() as $type ) {
			$this->assertSame( 'light', $type['text_tone'], $type['name'] );
			$this->assertGreaterThanOrEqual( 4.5, $contrast->ratio( $type['color'], '#FFFFFF' ), $type['name'] );
		}
	}

	/**
	 * Matriz de permisos de todas las rutas (criterio 2, R-22).
	 */
	public function test_permissions_on_every_route(): void {
		$id    = (int) $this->type_named( 'Cumpleaños' )['id'];
		$ids   = array_column( $this->list_types(), 'id' );
		$write = [
			[ 'POST', '', $this->body( 'Otro tipo' ) ],
			[ 'PUT', '/' . $id, $this->body( 'Otro nombre' ) ],
			[ 'PUT', '/order', [ 'ids' => array_reverse( $ids ) ] ],
			[ 'DELETE', '/' . $id, [] ],
		];

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request( 'GET', '' )->get_status(), 'Visitante: lista.' );
		$this->assertSame( 401, $this->request( 'GET', '/' . $id )->get_status(), 'Visitante: detalle.' );
		foreach ( $write as [ $method, $path, $body ] ) {
			$this->assertSame( 401, $this->request( $method, $path, $body )->get_status(), "Visitante: {$method} {$path}." );
		}

		foreach ( [ 'subscriber', 'editor' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			$this->assertSame( 200, $this->request( 'GET', '' )->get_status(), "{$role}: lista." );
			$this->assertSame( 200, $this->request( 'GET', '/' . $id )->get_status(), "{$role}: detalle." );
			foreach ( $write as [ $method, $path, $body ] ) {
				$this->assertSame( 403, $this->request( $method, $path, $body )->get_status(), "{$role} sin eventos_manage: {$method} {$path}." );
			}
		}

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertTrue( current_user_can( Capabilities::MANAGE ), 'El administrador recibe eventos_manage al activar.' );
		$this->assertSame( 201, $this->request( 'POST', '', $this->body( 'Otro tipo' ) )->get_status() );
		$this->assertSame( 200, $this->request( 'PUT', '/order', [ 'ids' => array_column( $this->list_types(), 'id' ) ] )->get_status() );
		$this->assertSame( 200, $this->request( 'DELETE', '/' . $id )->get_status() );
	}

	/**
	 * Usuario con eventos_manage (no administrador): así se prueba la capability y no el rol.
	 */
	private function act_as_manager(): void {
		$manager = self::factory()->user->create_and_get( [ 'role' => 'editor' ] );
		$manager->add_cap( Capabilities::MANAGE );
		wp_set_current_user( $manager->ID );
	}

	/**
	 * Cuerpo válido de un tipo.
	 *
	 * @param string $name  Nombre.
	 * @param string $color Color.
	 * @return array<string, mixed>
	 */
	private function body( string $name, string $color = '#155728' ): array {
		return [
			'name'  => $name,
			'color' => $color,
			'icon'  => 'star',
		];
	}

	/**
	 * Crea un tipo.
	 *
	 * @param string $name  Nombre.
	 * @param string $color Color.
	 */
	private function create( string $name, string $color = '#155728' ): WP_REST_Response {
		return $this->request( 'POST', '', $this->body( $name, $color ) );
	}

	/**
	 * Renombra un tipo conservando sus demás datos.
	 *
	 * @param int|string $id   Identificador.
	 * @param string     $name Nombre nuevo.
	 */
	private function update( int|string $id, string $name ): WP_REST_Response {
		return $this->request( 'PUT', '/' . $id, $this->body( $name ) );
	}

	/**
	 * Lista de tipos.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function list_types(): array {
		return $this->request( 'GET', '' )->get_data()['data'];
	}

	/**
	 * Tipo por nombre exacto.
	 *
	 * @param string $name Nombre.
	 * @return array<string, mixed>
	 */
	private function type_named( string $name ): array {
		$matches = array_values( array_filter( $this->list_types(), static fn ( array $type ): bool => $name === $type['name'] ) );
		$this->assertCount( 1, $matches, "Tipo «{$name}»." );

		return $matches[0];
	}

	/**
	 * Inserta eventos de un tipo directamente en la tabla (la API de eventos llega en el Sprint 2).
	 *
	 * @param int $type_id Tipo.
	 * @param int $count   Cantidad.
	 */
	private function insert_events( int $type_id, int $count ): void {
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );

		for ( $index = 1; $index <= $count; $index++ ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Datos de prueba.
				$this->tables()->name( Tables::EVENTS ),
				[
					'type_id'        => $type_id,
					'title'          => "Evento {$index}",
					'start_date'     => '2026-10-07',
					'created_at_gmt' => $now,
					'updated_at_gmt' => $now,
				]
			);
		}
	}

	/**
	 * Elimina los eventos de un tipo.
	 *
	 * @param int $type_id Tipo.
	 */
	private function delete_events( int $type_id ): void {
		global $wpdb;
		$wpdb->delete( $this->tables()->name( Tables::EVENTS ), [ 'type_id' => $type_id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Datos de prueba.
	}

	/**
	 * Ejecuta una petición a la API de tipos de evento.
	 *
	 * @param string               $method Método HTTP.
	 * @param string               $path   Ruta relativa al recurso.
	 * @param array<string, mixed> $body   Cuerpo JSON.
	 */
	private function request( string $method, string $path, array $body = [] ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/eventos/v1/event-types' . $path );

		if ( [] !== $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return rest_do_request( $request );
	}
}
