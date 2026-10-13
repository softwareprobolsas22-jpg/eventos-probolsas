<?php
/**
 * Pruebas de integración de la subida del adjunto al soltar un archivo.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Integration;

use Probolsas\Eventos\Core\Assets\Assets;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * H-411 contra WordPress real: `POST /wp/v2/media` marcada desde el formulario rechaza lo que no es imagen ni
 * PDF antes de guardarlo (D-4: no queda nada en la Biblioteca de Medios).
 *
 * @coversNothing
 */
final class AttachmentUploadTest extends IntegrationTestCase {

	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

	/**
	 * Archivos temporales creados por la prueba.
	 *
	 * @var list<string>
	 */
	private array $files = [];

	public function set_up(): void {
		parent::set_up();
		$this->plugin()->activate();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Servidor REST de WordPress para la prueba.
		do_action( 'rest_api_init', $wp_rest_server ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook de WordPress.

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Restablece el servidor REST.
		array_map( 'unlink', array_filter( $this->files, 'is_file' ) );

		parent::tear_down();
	}

	public function test_a_file_that_is_not_an_image_or_a_pdf_never_reaches_the_library(): void {
		$before   = $this->attachments();
		$response = $this->upload( 'manual.pdf', 'MZ' . str_repeat( "\0", 62 ) . 'This program cannot be run in DOS mode.' );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'eventos_validation_failed', $response->get_data()['code'] );
		$this->assertSame( [ 'El archivo debe ser una imagen o un PDF.' ], $response->get_data()['data']['errors']['file'] );
		$this->assertSame( $before, $this->attachments(), 'D-4: el archivo rechazado no se guarda.' );
	}

	public function test_an_image_passes_the_check_and_wordpress_handles_it(): void {
		$response = $this->upload( 'ana.png', (string) base64_decode( self::PNG ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Imagen de prueba.

		$this->assertNotSame( 'eventos_validation_failed', $response->get_data()['code'] ?? null );
	}

	public function test_uploads_without_the_mark_are_not_changed(): void {
		$response = $this->upload( 'manual.pdf', 'MZ', false );

		$this->assertNotSame( 'eventos_validation_failed', $response->get_data()['code'] ?? null, 'La Biblioteca de Medios sigue igual para los demás.' );
	}

	public function test_the_browser_receives_the_upload_configuration(): void {
		$config = $this->plugin()->container()->get( Assets::class )->client_config();

		$this->assertStringEndsWith( 'wp/v2/media', $config['media']['upload_url'] );
		$this->assertSame( 'X-Eventos-Upload', $config['media']['upload_header'] );
		$this->assertSame( wp_max_upload_size(), $config['media']['max_upload_bytes'] );
		$this->assertTrue( $config['can']['upload'], 'Un editor puede subir archivos.' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertFalse( $this->plugin()->container()->get( Assets::class )->client_config()['can']['upload'] );
	}

	/**
	 * Sube un archivo a `POST /wp/v2/media` como el formulario de evento.
	 *
	 * @param string $name     Nombre del archivo.
	 * @param string $contents Contenido.
	 * @param bool   $marked   Si lleva la marca del formulario.
	 */
	private function upload( string $name, string $contents, bool $marked = true ): WP_REST_Response {
		$path = (string) wp_tempnam( $name );
		file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Archivo de prueba.
		$this->files[] = $path;

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		if ( $marked ) {
			$request->set_header( 'X-Eventos-Upload', 'event-attachment' );
		}
		$request->set_file_params(
			[
				'file' => [
					'name'     => $name,
					'type'     => 'application/octet-stream',
					'tmp_name' => $path,
					'error'    => UPLOAD_ERR_OK,
					'size'     => strlen( $contents ),
				],
			]
		);

		return rest_do_request( $request );
	}

	/**
	 * Cantidad de adjuntos en la Biblioteca de Medios.
	 */
	private function attachments(): int {
		return count(
			get_posts(
				[
					'post_type'   => 'attachment',
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
				]
			)
		);
	}
}
