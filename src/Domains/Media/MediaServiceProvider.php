<?php
/**
 * Servicios del dominio Medios.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Media;

use Probolsas\Eventos\Core\BootableProvider;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Core\Container;
use Probolsas\Eventos\Domains\Media\Domain\AttachmentGateway;
use Probolsas\Eventos\Domains\Media\Domain\MediaPolicy;
use Probolsas\Eventos\Domains\Media\Infrastructure\WpAttachmentGateway;
use Probolsas\Eventos\Domains\Media\Presentation\AttachmentUpload;

/**
 * Registra la política de archivos permitidos y el acceso a la Biblioteca de Medios. La configuración del
 * selector (`epConfig.media`) la publica Assets desde `config/media.php`; la de la subida al soltar un archivo
 * en el formulario (H-411), AttachmentUpload.
 */
final class MediaServiceProvider implements BootableProvider {

	/**
	 * Registra los servicios.
	 *
	 * @param Container $container Contenedor del plugin.
	 */
	public function register( Container $container ): void {
		$container->set( MediaPolicy::class, static fn( Container $c ): MediaPolicy => MediaPolicy::from_config( $c->get( Config::class ) ) );
		$container->set( AttachmentGateway::class, static fn(): AttachmentGateway => new WpAttachmentGateway() );
		$container->set( AttachmentUpload::class, static fn( Container $c ): AttachmentUpload => new AttachmentUpload( $c->get( MediaPolicy::class ) ) );
	}

	/**
	 * Conecta la revisión de la subida y su configuración para el navegador.
	 *
	 * @param Container $container Contenedor del plugin.
	 */
	public function boot( Container $container ): void {
		$container->get( AttachmentUpload::class )->register();
	}
}
