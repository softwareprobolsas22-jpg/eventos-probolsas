<?php
/**
 * Tipo MIME según el contenido de un archivo.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Media\Infrastructure;

use finfo;

/**
 * Lee el tipo MIME del contenido (R-09), no del nombre ni del que declara el navegador: un `.exe`
 * renombrado a `.pdf` no pasa por PDF.
 */
final class RealMime {

	/**
	 * Tipo MIME del archivo, en minúsculas, o null si no se puede leer.
	 *
	 * @param string|null $path Ruta del archivo.
	 */
	public static function of( ?string $path ): ?string {
		if ( null === $path || '' === $path || ! is_readable( $path ) || ! class_exists( finfo::class ) ) {
			return null;
		}

		$mime = ( new finfo( FILEINFO_MIME_TYPE ) )->file( $path );

		return is_string( $mime ) && '' !== $mime ? strtolower( $mime ) : null;
	}
}
