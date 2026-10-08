<?php
/**
 * Biblioteca de Medios de WordPress.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Media\Infrastructure;

use finfo;
use Probolsas\Eventos\Domains\Media\Domain\Attachment;
use Probolsas\Eventos\Domains\Media\Domain\AttachmentGateway;
use WP_Post;

/**
 * Lee los adjuntos con las funciones de WordPress. No tiene métodos para borrar: eliminar un evento nunca
 * borra su archivo (D-4, RL-07).
 *
 * El tipo MIME se toma del contenido del archivo cuando se puede leer (R-09): un `.exe` renombrado a `.pdf`
 * no pasa por PDF aunque WordPress lo haya registrado así. Si el archivo no está en el disco (por ejemplo,
 * descargado a un CDN), se usa el tipo que registró WordPress al subirlo.
 */
final class WpAttachmentGateway implements AttachmentGateway {

	/**
	 * Adjunto por ID.
	 *
	 * @param int $id ID del adjunto.
	 */
	public function find( int $id ): ?Attachment {
		$post = $id > 0 ? get_post( $id ) : null;

		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
			return null;
		}

		$url = wp_get_attachment_url( $id );
		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}

		$path      = get_attached_file( $id );
		$path      = is_string( $path ) && '' !== $path && is_readable( $path ) ? $path : null;
		$mime      = $this->real_mime( $path ) ?? strtolower( (string) $post->post_mime_type );
		$thumbnail = str_starts_with( $mime, 'image/' ) ? wp_get_attachment_image_src( $id, 'thumbnail' ) : false;

		return new Attachment(
			$id,
			$mime,
			$url,
			is_array( $thumbnail ) ? (string) $thumbnail[0] : null,
			(string) $post->post_title,
			wp_basename( $path ?? $url ),
			null !== $path ? (int) filesize( $path ) : 0
		);
	}

	/**
	 * Tipo MIME según el contenido del archivo, o null si no se puede determinar.
	 *
	 * @param string|null $path Ruta del archivo.
	 */
	private function real_mime( ?string $path ): ?string {
		if ( null === $path || ! class_exists( finfo::class ) ) {
			return null;
		}

		$mime = ( new finfo( FILEINFO_MIME_TYPE ) )->file( $path );

		return is_string( $mime ) && '' !== $mime ? strtolower( $mime ) : null;
	}
}
