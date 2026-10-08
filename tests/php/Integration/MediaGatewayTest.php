<?php
/**
 * Pruebas de integración de la Biblioteca de Medios.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Integration;

use Probolsas\Eventos\Domains\Media\Domain\AttachmentGateway;
use Probolsas\Eventos\Domains\Media\Domain\MediaPolicy;

/**
 * H-202 contra WordPress real: adjuntos subidos a la biblioteca, tipo MIME real (R-09) y configuración
 * del selector del navegador.
 *
 * @coversNothing
 */
final class MediaGatewayTest extends IntegrationTestCase {

	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

	public function set_up(): void {
		parent::set_up();
		$this->plugin()->activate();
	}

	public function test_an_image_from_the_library_is_allowed(): void {
		$id         = $this->upload( 'ana.png', (string) base64_decode( self::PNG ), 'image/png' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Imagen de prueba.
		$attachment = $this->gateway()->find( $id );

		$this->assertNotNull( $attachment );
		$this->assertSame( 'image', $attachment->kind() );
		$this->assertSame( 'image/png', $attachment->mime );
		$this->assertStringEndsWith( '.png', $attachment->url );
		$this->assertGreaterThan( 0, $attachment->filesize );
		$this->assertTrue( $this->policy()->allows( $attachment ) );
	}

	public function test_a_pdf_from_the_library_is_allowed(): void {
		$id         = $this->upload( 'acta.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n", 'application/pdf' );
		$attachment = $this->gateway()->find( $id );

		$this->assertNotNull( $attachment );
		$this->assertSame( 'pdf', $attachment->kind() );
		$this->assertNull( $attachment->thumbnail_url );
		$this->assertTrue( $this->policy()->allows( $attachment ) );
	}

	public function test_an_executable_renamed_to_pdf_is_rejected(): void {
		$id         = $this->upload( 'manual.pdf', 'MZ' . str_repeat( "\0", 62 ) . 'This program cannot be run in DOS mode.', 'application/pdf' );
		$attachment = $this->gateway()->find( $id );

		$this->assertNotNull( $attachment );
		$this->assertSame( 'application/pdf', get_post_mime_type( $id ), 'WordPress lo registró como PDF por su extensión.' );
		$this->assertFalse( $this->policy()->allows( $attachment ), 'R-09: el contenido real no es un PDF.' );
	}

	public function test_posts_that_are_not_attachments_are_not_found(): void {
		$this->assertNull( $this->gateway()->find( self::factory()->post->create() ) );
		$this->assertNull( $this->gateway()->find( 999999 ) );
	}

	public function test_the_browser_receives_the_same_allowed_types(): void {
		$config = $this->plugin()->container()->get( \Probolsas\Eventos\Core\Assets\Assets::class )->client_config();

		$this->assertSame( $this->policy()->allowed_mimes(), $config['media']['allowed_mimes'] );
		$this->assertSame( [ 'image', 'application/pdf' ], $config['media']['library_types'] );
	}

	/**
	 * Sube un archivo a la Biblioteca de Medios como lo haría WordPress (solo mira la extensión).
	 *
	 * @param string $name     Nombre del archivo.
	 * @param string $contents Contenido.
	 * @param string $mime     Tipo MIME que registra WordPress.
	 */
	private function upload( string $name, string $contents, string $mime ): int {
		$upload = wp_upload_bits( $name, null, $contents );
		$this->assertEmpty( $upload['error'], (string) $upload['error'] );

		$id = wp_insert_attachment(
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
	 * Gateway registrado en el contenedor.
	 */
	private function gateway(): AttachmentGateway {
		return $this->plugin()->container()->get( AttachmentGateway::class );
	}

	/**
	 * Política registrada en el contenedor.
	 */
	private function policy(): MediaPolicy {
		return $this->plugin()->container()->get( MediaPolicy::class );
	}
}
