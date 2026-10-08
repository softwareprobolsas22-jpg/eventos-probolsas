<?php
/**
 * Tipos de archivo permitidos en los eventos.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Media\Domain;

use Probolsas\Eventos\Core\Config;

/**
 * Regla R-09: los eventos solo aceptan imágenes o PDF, según el tipo MIME real del archivo. La lista sale
 * de `config/media.php` (`allowed_mimes`), la misma que recibe el selector del navegador.
 */
final class MediaPolicy {

	public const PDF_MIME = 'application/pdf';

	/**
	 * Tipos MIME permitidos, en minúsculas.
	 *
	 * @var list<string>
	 */
	private array $allowed_mimes;

	/**
	 * Crea la política.
	 *
	 * @param string[] $allowed_mimes Tipos MIME permitidos.
	 */
	public function __construct( array $allowed_mimes ) {
		$this->allowed_mimes = array_values( array_map( 'strtolower', $allowed_mimes ) );
	}

	/**
	 * Crea la política con `config/media.php`.
	 *
	 * @param Config $config Configuración del plugin.
	 */
	public static function from_config( Config $config ): self {
		$mimes = $config->get( 'media.allowed_mimes', [] );

		return new self( is_array( $mimes ) ? array_values( array_map( 'strval', $mimes ) ) : [] );
	}

	/**
	 * Indica si un adjunto se puede asociar a un evento.
	 *
	 * @param Attachment $attachment Adjunto.
	 */
	public function allows( Attachment $attachment ): bool {
		return in_array( strtolower( $attachment->mime ), $this->allowed_mimes, true );
	}

	/**
	 * Tipos MIME permitidos.
	 *
	 * @return list<string>
	 */
	public function allowed_mimes(): array {
		return $this->allowed_mimes;
	}
}
