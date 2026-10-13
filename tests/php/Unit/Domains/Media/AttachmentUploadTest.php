<?php
/**
 * Pruebas de la subida del adjunto al soltar un archivo (H-411).
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Domains\Media;

use Brain\Monkey\Functions;
use Mockery;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Core\Container;
use Probolsas\Eventos\Domains\Media\Domain\MediaPolicy;
use Probolsas\Eventos\Domains\Media\MediaServiceProvider;
use Probolsas\Eventos\Domains\Media\Presentation\AttachmentUpload;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;
use WP_Error;
use WP_REST_Request;

/**
 * La subida marcada desde el formulario solo guarda imágenes o PDF (R-09) y rechaza lo demás antes de que
 * WordPress lo guarde (D-4).
 *
 * @covers \Probolsas\Eventos\Domains\Media\Presentation\AttachmentUpload
 * @covers \Probolsas\Eventos\Domains\Media\Infrastructure\RealMime
 * @covers \Probolsas\Eventos\Domains\Media\MediaServiceProvider
 */
final class AttachmentUploadTest extends UnitTestCase {

	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

	private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

	/**
	 * Archivos temporales creados por la prueba.
	 *
	 * @var list<string>
	 */
	private array $files = [];

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
		Functions\when( 'untrailingslashit' )->alias( static fn( string $text ): string => rtrim( $text, '/\\' ) );
		Functions\when( 'wp_max_upload_size' )->justReturn( 2 * 1024 * 1024 );
		Functions\when( 'size_format' )->justReturn( '2 MB' );
		Functions\when( 'wp_check_filetype' )->alias(
			static function ( string $name ): array {
				$types = [
					'png' => 'image/png',
					'jpg' => 'image/jpeg',
					'pdf' => 'application/pdf',
					'exe' => 'application/x-msdownload',
				];
				$ext   = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

				return [
					'ext'  => isset( $types[ $ext ] ) ? $ext : false,
					'type' => $types[ $ext ] ?? false,
				];
			}
		);
	}

	protected function tear_down(): void {
		array_map( 'unlink', array_filter( $this->files, 'is_file' ) );
		parent::tear_down();
	}

	public function test_an_image_or_a_pdf_continues_to_wordpress(): void {
		$upload = $this->upload();

		$this->assertNull( $upload->check( null, [], $this->request( $this->file( 'ana.png', (string) base64_decode( self::PNG ) ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Imagen de prueba.
		$this->assertNull( $upload->check( null, [], $this->request( $this->file( 'acta.pdf', self::PDF ) ) ) );
	}

	public function test_a_file_that_is_not_an_image_or_a_pdf_is_rejected_before_saving(): void {
		$error = $this->upload()->check( null, [], $this->request( $this->file( 'manual.pdf', 'MZ' . str_repeat( "\0", 62 ) . 'This program cannot be run in DOS mode.' ) ) );

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'eventos_validation_failed', $error->get_error_code() );
		$this->assertSame( 422, $error->get_error_data()['status'] );
		$this->assertSame( [ 'El archivo debe ser una imagen o un PDF.' ], $error->get_error_data()['errors']['file'] );

		$text = $this->upload()->check( null, [], $this->request( $this->file( 'notas.pdf', 'Solo texto.' ) ) );
		$this->assertSame( 'El archivo debe ser una imagen o un PDF.', $text->get_error_message() );
	}

	public function test_the_extension_must_match_the_content(): void {
		$error = $this->upload()->check( null, [], $this->request( $this->file( 'acta.png', self::PDF ) ) );

		$this->assertSame( 'La extensión del archivo no corresponde a su contenido.', $error->get_error_message() );

		$executable = $this->upload()->check( null, [], $this->request( $this->file( 'ana.exe', (string) base64_decode( self::PNG ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Imagen de prueba.
		$this->assertInstanceOf( WP_Error::class, $executable );
	}

	public function test_size_and_missing_file(): void {
		$path  = $this->file( 'acta.pdf', self::PDF );
		$error = $this->upload()->check( null, [], $this->request( $path, [ 'size' => 3 * 1024 * 1024 ] ) );
		$this->assertSame( 'El archivo supera el tamaño máximo permitido (2 MB).', $error->get_error_message() );

		$request = $this->request( $path );
		$request->set_file_params( [] );
		$this->assertSame( 'No se recibió ningún archivo.', $this->upload()->check( null, [], $request )->get_error_message() );

		// Un error de PHP al recibirlo lo informa WordPress con su propio mensaje.
		$this->assertNull( $this->upload()->check( null, [], $this->request( $path, [ 'error' => UPLOAD_ERR_INI_SIZE ] ) ) );
	}

	public function test_only_marked_uploads_to_wp_v2_media_are_checked(): void {
		$path     = $this->file( 'manual.pdf', 'MZ' );
		$upload   = $this->upload();
		$unmarked = $this->request( $path );
		$unmarked->set_header( AttachmentUpload::HEADER, 'otra-cosa' );
		$other = $this->request( $path, [], '/wp/v2/posts' );
		$get   = $this->request( $path );
		$get->set_method( 'GET' );

		$this->assertNull( $upload->check( null, [], $unmarked ), 'La Biblioteca de Medios y otros plugins no cambian.' );
		$this->assertNull( $upload->check( null, [], $other ) );
		$this->assertNull( $upload->check( null, [], $get ) );
		$this->assertInstanceOf( WP_Error::class, $upload->check( null, [], $this->request( $path, [], '/wp/v2/media/' ) ) );

		$previous = new WP_Error( 'rest_forbidden', 'No.' );
		$this->assertSame( $previous, $upload->check( $previous, [], $this->request( $path ) ), 'Un error anterior se respeta.' );
	}

	public function test_the_browser_receives_the_upload_configuration(): void {
		Functions\when( 'rest_url' )->alias( static fn( string $path ): string => 'https://intranet.test/wp-json/' . $path );
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\expect( 'current_user_can' )->once()->with( 'upload_files' )->andReturn( true );

		$config = $this->upload()->client_config(
			[
				'media' => [ 'allowed_mimes' => [ 'application/pdf' ] ],
				'can'   => [ 'manage' => true ],
			]
		);

		$this->assertSame(
			[
				'allowed_mimes'    => [ 'application/pdf' ],
				'upload_url'       => 'https://intranet.test/wp-json/wp/v2/media',
				'upload_header'    => 'X-Eventos-Upload',
				'upload_context'   => 'event-attachment',
				'max_upload_bytes' => 2 * 1024 * 1024,
			],
			$config['media']
		);
		$this->assertSame(
			[
				'manage' => true,
				'upload' => true,
			],
			$config['can']
		);
	}

	public function test_the_provider_registers_and_connects_the_upload(): void {
		$container = new Container();
		$container->set( Config::class, static fn(): Config => new Config( [ 'media' => [ 'allowed_mimes' => [ 'application/pdf' ] ] ] ) );
		( new MediaServiceProvider() )->register( $container );

		Functions\expect( 'add_filter' )->once()->with( 'rest_request_before_callbacks', Mockery::type( 'array' ), 10, 3 );
		Functions\expect( 'add_filter' )->once()->with( 'eventos_client_config', Mockery::type( 'array' ) );
		( new MediaServiceProvider() )->boot( $container );

		$this->assertInstanceOf( AttachmentUpload::class, $container->get( AttachmentUpload::class ) );
	}

	/**
	 * Servicio con imágenes PNG/JPEG y PDF permitidos.
	 */
	private function upload(): AttachmentUpload {
		return new AttachmentUpload( new MediaPolicy( [ 'image/png', 'image/jpeg', 'application/pdf' ] ) );
	}

	/**
	 * Subida marcada desde el formulario de evento.
	 *
	 * @param string               $path  Archivo temporal.
	 * @param array<string, mixed> $file  Datos que cambian de la entrada de $_FILES.
	 * @param string               $route Ruta pedida.
	 *
	 * @return WP_REST_Request<array<string, mixed>>
	 */
	private function request( string $path, array $file = [], string $route = '/wp/v2/media' ): WP_REST_Request {
		$request = new WP_REST_Request( [], $route );
		$request->set_method( 'POST' );
		$request->set_header( 'X-Eventos-Upload', 'event-attachment' );
		$request->set_file_params(
			[
				'file' => array_merge(
					[
						'name'     => (string) preg_replace( '/^ep-upload-[a-z0-9.]+-/', '', basename( $path ) ),
						'type'     => 'application/octet-stream',
						'tmp_name' => $path,
						'error'    => UPLOAD_ERR_OK,
						'size'     => (int) filesize( $path ),
					],
					$file
				),
			]
		);

		return $request;
	}

	/**
	 * Crea un archivo temporal.
	 *
	 * @param string $name     Nombre con el que llega.
	 * @param string $contents Contenido.
	 */
	private function file( string $name, string $contents ): string {
		$path = sys_get_temp_dir() . '/ep-upload-' . uniqid() . '-' . $name;
		file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Archivo de prueba.
		$this->files[] = $path;

		return $path;
	}
}
