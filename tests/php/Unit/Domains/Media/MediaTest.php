<?php
/**
 * Pruebas del dominio Medios.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Domains\Media;

use Brain\Monkey\Functions;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Core\Container;
use Probolsas\Eventos\Domains\Media\Domain\Attachment;
use Probolsas\Eventos\Domains\Media\Domain\AttachmentGateway;
use Probolsas\Eventos\Domains\Media\Domain\MediaPolicy;
use Probolsas\Eventos\Domains\Media\Infrastructure\WpAttachmentGateway;
use Probolsas\Eventos\Domains\Media\MediaServiceProvider;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;
use WP_Post;

/**
 * Política de archivos (R-09), adjunto y lectura de la Biblioteca de Medios (H-202).
 *
 * @covers \Probolsas\Eventos\Domains\Media\Domain\Attachment
 * @covers \Probolsas\Eventos\Domains\Media\Domain\MediaPolicy
 * @covers \Probolsas\Eventos\Domains\Media\Infrastructure\WpAttachmentGateway
 * @covers \Probolsas\Eventos\Domains\Media\MediaServiceProvider
 */
final class MediaTest extends UnitTestCase {

	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

	/**
	 * Archivos temporales creados por la prueba.
	 *
	 * @var list<string>
	 */
	private array $files = [];

	protected function set_up(): void {
		parent::set_up();
		Functions\when( 'wp_basename' )->alias( 'basename' );
	}

	protected function tear_down(): void {
		array_map( 'unlink', array_filter( $this->files, 'is_file' ) );
		parent::tear_down();
	}

	public function test_the_policy_allows_only_images_and_pdf_from_the_configuration(): void {
		$policy = MediaPolicy::from_config( new Config( [ 'media' => [ 'allowed_mimes' => [ 'image/jpeg', 'image/PNG', 'application/pdf' ] ] ] ) );

		$this->assertTrue( $policy->allows( $this->attachment( 'image/jpeg' ) ) );
		$this->assertTrue( $policy->allows( $this->attachment( 'IMAGE/png' ) ), 'Sin distinguir mayúsculas.' );
		$this->assertTrue( $policy->allows( $this->attachment( 'application/pdf' ) ) );
		$this->assertFalse( $policy->allows( $this->attachment( 'application/x-dosexec' ) ) );
		$this->assertFalse( $policy->allows( $this->attachment( 'image/svg+xml' ) ), 'Solo los formatos de la lista.' );
		$this->assertSame( [ 'image/jpeg', 'image/png', 'application/pdf' ], $policy->allowed_mimes() );
		$this->assertSame( [], MediaPolicy::from_config( new Config( [] ) )->allowed_mimes() );
	}

	public function test_the_attachment_knows_its_kind_and_api_representation(): void {
		$this->assertSame( Attachment::KIND_IMAGE, $this->attachment( 'image/webp' )->kind() );
		$this->assertSame( Attachment::KIND_PDF, $this->attachment( 'application/pdf' )->kind() );
		$this->assertSame( Attachment::KIND_OTHER, $this->attachment( 'text/plain' )->kind() );

		$this->assertSame(
			[
				'id'            => 315,
				'kind'          => 'pdf',
				'mime'          => 'application/pdf',
				'url'           => 'https://intranet.test/uploads/acta.pdf',
				'thumbnail_url' => null,
				'title'         => 'acta',
				'filename'      => 'acta.pdf',
				'filesize'      => 2048,
			],
			$this->attachment( 'application/pdf' )->to_array()
		);
	}

	public function test_only_attachments_are_found(): void {
		// Con el ID 0 ni siquiera consulta WordPress (get_post( 0 ) devolvería la entrada actual del loop).
		Functions\expect( 'get_post' )->twice()->andReturnUsing( static fn( int $id ): ?WP_Post => 7 === $id ? new WP_Post( 7, 'page' ) : null );

		$gateway = new WpAttachmentGateway();

		$this->assertNull( $gateway->find( 0 ) );
		$this->assertNull( $gateway->find( 7 ), 'Una página no es un adjunto.' );
		$this->assertNull( $gateway->find( 99 ), 'No existe.' );
	}

	public function test_an_attachment_without_url_is_not_found(): void {
		Functions\when( 'get_post' )->justReturn( new WP_Post( 5, 'attachment', 'image/png', 'Logo' ) );
		Functions\when( 'wp_get_attachment_url' )->justReturn( false );

		$this->assertNull( ( new WpAttachmentGateway() )->find( 5 ) );
	}

	public function test_the_mime_comes_from_the_file_content_not_from_its_name(): void {
		$image = $this->file( 'ana.pdf', (string) base64_decode( self::PNG ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Imagen de prueba.
		$this->attachment_post( 21, 'application/pdf', 'Ana', $image );
		Functions\expect( 'wp_get_attachment_image_src' )->once()->with( 21, 'thumbnail' )->andReturn( [ 'https://intranet.test/uploads/ana-150x150.png', 150, 150 ] );

		$attachment = ( new WpAttachmentGateway() )->find( 21 );

		$this->assertNotNull( $attachment );
		$this->assertSame( 'image/png', $attachment->mime, 'Registrado como PDF, pero el contenido es una imagen.' );
		$this->assertSame( 'image', $attachment->kind() );
		$this->assertSame( 'https://intranet.test/uploads/ana-150x150.png', $attachment->thumbnail_url );
		$this->assertSame( 'Ana', $attachment->title );
		$this->assertSame( basename( $image ), $attachment->filename );
		$this->assertSame( filesize( $image ), $attachment->filesize );
	}

	public function test_an_executable_renamed_to_pdf_is_not_a_pdf(): void {
		$executable = $this->file( 'manual.pdf', 'MZ' . str_repeat( "\0", 62 ) . 'This program cannot be run in DOS mode.' );
		$this->attachment_post( 22, 'application/pdf', 'Manual', $executable );

		$attachment = ( new WpAttachmentGateway() )->find( 22 );
		$policy     = new MediaPolicy( [ 'image/png', 'application/pdf' ] );

		$this->assertNotNull( $attachment );
		$this->assertNotSame( 'application/pdf', $attachment->mime );
		$this->assertFalse( $policy->allows( $attachment ), 'R-09: el contenido manda sobre la extensión.' );
		$this->assertNull( $attachment->thumbnail_url );
	}

	public function test_a_real_pdf_is_detected_as_pdf(): void {
		$pdf = $this->file( 'acta.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n" );
		$this->attachment_post( 23, 'application/pdf', 'Acta', $pdf );

		$attachment = ( new WpAttachmentGateway() )->find( 23 );

		$this->assertNotNull( $attachment );
		$this->assertSame( 'application/pdf', $attachment->mime );
		$this->assertTrue( ( new MediaPolicy( [ 'application/pdf' ] ) )->allows( $attachment ) );
	}

	public function test_without_the_file_on_disk_it_trusts_the_registered_mime(): void {
		$this->attachment_post( 24, 'IMAGE/JPEG', 'Foto', '' );
		Functions\when( 'wp_get_attachment_image_src' )->justReturn( false );

		$attachment = ( new WpAttachmentGateway() )->find( 24 );

		$this->assertNotNull( $attachment );
		$this->assertSame( 'image/jpeg', $attachment->mime );
		$this->assertSame( 'foto.jpg', $attachment->filename, 'Sin archivo local, el nombre sale de la URL.' );
		$this->assertSame( 0, $attachment->filesize );
		$this->assertNull( $attachment->thumbnail_url );
	}

	public function test_the_gateway_cannot_delete_files(): void {
		$methods = get_class_methods( WpAttachmentGateway::class );

		$this->assertSame( [ 'find' ], $methods, 'D-4: el plugin nunca borra archivos de la Biblioteca de Medios.' );
	}

	public function test_the_provider_registers_the_policy_and_the_gateway(): void {
		$container = new Container();
		$container->set( Config::class, static fn(): Config => new Config( [ 'media' => [ 'allowed_mimes' => [ 'application/pdf' ] ] ] ) );
		( new MediaServiceProvider() )->register( $container );

		$this->assertSame( [ 'application/pdf' ], $container->get( MediaPolicy::class )->allowed_mimes() );
		$this->assertInstanceOf( WpAttachmentGateway::class, $container->get( AttachmentGateway::class ) );
	}

	/**
	 * Adjunto de prueba.
	 *
	 * @param string $mime Tipo MIME.
	 */
	private function attachment( string $mime ): Attachment {
		return new Attachment( 315, $mime, 'https://intranet.test/uploads/acta.pdf', null, 'acta', 'acta.pdf', 2048 );
	}

	/**
	 * Simula un adjunto de WordPress.
	 *
	 * @param int    $id    ID.
	 * @param string $mime  Tipo MIME registrado.
	 * @param string $title Título.
	 * @param string $path  Archivo en el disco ('' si no existe).
	 */
	private function attachment_post( int $id, string $mime, string $title, string $path ): void {
		Functions\when( 'get_post' )->justReturn( new WP_Post( $id, 'attachment', $mime, $title ) );
		Functions\when( 'wp_get_attachment_url' )->justReturn( 'https://intranet.test/uploads/' . ( '' === $path ? 'foto.jpg' : basename( $path ) ) );
		Functions\when( 'get_attached_file' )->justReturn( '' === $path ? '/no/existe/foto.jpg' : $path );
	}

	/**
	 * Crea un archivo temporal.
	 *
	 * @param string $name     Nombre.
	 * @param string $contents Contenido.
	 */
	private function file( string $name, string $contents ): string {
		$path = sys_get_temp_dir() . '/ep-media-' . uniqid() . '-' . $name;
		file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Archivo de prueba.
		$this->files[] = $path;

		return $path;
	}
}
