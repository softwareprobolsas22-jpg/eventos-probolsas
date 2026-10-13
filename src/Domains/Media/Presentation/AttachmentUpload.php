<?php
/**
 * Subida del adjunto de un evento arrastrando el archivo al formulario.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Media\Presentation;

use Probolsas\Eventos\Domains\Media\Domain\MediaPolicy;
use Probolsas\Eventos\Domains\Media\Infrastructure\RealMime;
use Probolsas\Eventos\Shared\Http\ErrorCode;
use WP_Error;
use WP_REST_Request;

/**
 * H-411. El formulario de evento sube el archivo soltado con la API de WordPress (`POST /wp/v2/media`, que
 * exige `upload_files`) y marca la petición con la cabecera `X-Eventos-Upload: event-attachment`.
 *
 * Antes de que WordPress guarde el archivo, esta clase revisa la petición marcada: solo deja pasar una
 * imagen o un PDF por su contenido real (R-09) y con una extensión que coincida. Si no, responde 422 y el
 * archivo nunca llega a la Biblioteca de Medios: el plugin no borra archivos (D-4), así que uno rechazado
 * no puede quedar guardado. Las subidas sin la marca (la Biblioteca de Medios, otros plugins) no cambian.
 *
 * También publica en `epConfig` lo que necesita el navegador: la dirección de subida, el tamaño máximo y
 * si el usuario puede subir archivos.
 */
final class AttachmentUpload {

	/**
	 * Cabecera que marca la subida del adjunto de un evento.
	 */
	public const HEADER = 'X-Eventos-Upload';

	/**
	 * Valor de la cabecera.
	 */
	public const CONTEXT = 'event-attachment';

	/**
	 * Ruta de la API de WordPress que guarda el archivo.
	 */
	public const ROUTE = '/wp/v2/media';

	/**
	 * Crea el servicio.
	 *
	 * @param MediaPolicy $policy Tipos de archivo permitidos.
	 */
	public function __construct( private readonly MediaPolicy $policy ) {}

	/**
	 * Conecta con WordPress.
	 */
	public function register(): void {
		add_filter( 'rest_request_before_callbacks', [ $this, 'check' ], 10, 3 );
		add_filter( 'eventos_client_config', [ $this, 'client_config' ] );
	}

	/**
	 * Revisa una subida marcada antes de que la API de WordPress la guarde. WordPress aplica este filtro
	 * antes del permiso y del guardado; devolver un WP_Error detiene la petición.
	 *
	 * @param mixed           $response Respuesta hasta ahora (null o un error de otro filtro).
	 * @param mixed           $handler  Endpoint que atenderá la petición.
	 * @param WP_REST_Request $request  Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function check( mixed $response, mixed $handler, WP_REST_Request $request ): mixed {
		if ( $response instanceof WP_Error || ! $this->is_event_upload( $request ) ) {
			return $response;
		}

		$files   = $request->get_file_params();
		$message = $this->problem( $files['file'] ?? null );

		if ( null === $message ) {
			return $response;
		}

		return new WP_Error(
			ErrorCode::ValidationFailed->value,
			$message,
			[
				'status' => ErrorCode::ValidationFailed->status(),
				'errors' => [ 'file' => [ $message ] ],
			]
		);
	}

	/**
	 * Agrega a `epConfig` la configuración de la subida.
	 *
	 * @param array<string, mixed> $config Configuración del cliente.
	 *
	 * @return array<string, mixed>
	 */
	public function client_config( array $config ): array {
		$media = is_array( $config['media'] ?? null ) ? $config['media'] : [];
		$can   = is_array( $config['can'] ?? null ) ? $config['can'] : [];

		$config['media'] = array_merge(
			$media,
			[
				'upload_url'       => esc_url_raw( rest_url( ltrim( self::ROUTE, '/' ) ) ),
				'upload_header'    => self::HEADER,
				'upload_context'   => self::CONTEXT,
				'max_upload_bytes' => (int) wp_max_upload_size(),
			]
		);
		$config['can']   = array_merge( $can, [ 'upload' => current_user_can( 'upload_files' ) ] );

		return $config;
	}

	/**
	 * Indica si la petición es la subida del adjunto de un evento.
	 *
	 * @param WP_REST_Request $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	private function is_event_upload( WP_REST_Request $request ): bool {
		return 'POST' === $request->get_method()
			&& self::ROUTE === untrailingslashit( $request->get_route() )
			&& self::CONTEXT === $request->get_header( self::HEADER );
	}

	/**
	 * Qué impide guardar el archivo, o null si se puede guardar.
	 *
	 * @param mixed $file Archivo recibido (una entrada de $_FILES).
	 */
	private function problem( mixed $file ): ?string {
		if ( ! is_array( $file ) || ! isset( $file['tmp_name'], $file['name'] ) ) {
			return __( 'No se recibió ningún archivo.', 'eventos-probolsas' );
		}

		// Un error de PHP al recibirlo (por ejemplo, demasiado grande para el servidor) lo explica WordPress.
		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_OK ) ) {
			return null;
		}

		$max_size = (int) wp_max_upload_size();
		if ( $max_size > 0 && (int) ( $file['size'] ?? 0 ) > $max_size ) {
			/* translators: %s: tamaño máximo, por ejemplo «10 MB». */
			return sprintf( __( 'El archivo supera el tamaño máximo permitido (%s).', 'eventos-probolsas' ), (string) size_format( $max_size ) );
		}

		$real_mime = RealMime::of( (string) $file['tmp_name'] );
		if ( null === $real_mime || ! $this->policy->allows_mime( $real_mime ) ) {
			return __( 'El archivo debe ser una imagen o un PDF.', 'eventos-probolsas' );
		}

		$by_name = wp_check_filetype( (string) $file['name'] );
		if ( ! is_string( $by_name['type'] ) || $by_name['type'] !== $real_mime ) {
			return __( 'La extensión del archivo no corresponde a su contenido.', 'eventos-probolsas' );
		}

		return null;
	}
}
