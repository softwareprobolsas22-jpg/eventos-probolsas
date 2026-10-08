<?php
/**
 * Servicios del dominio Medios.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Media;

use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Core\Container;
use Probolsas\Eventos\Core\ServiceProvider;
use Probolsas\Eventos\Domains\Media\Domain\AttachmentGateway;
use Probolsas\Eventos\Domains\Media\Domain\MediaPolicy;
use Probolsas\Eventos\Domains\Media\Infrastructure\WpAttachmentGateway;

/**
 * Registra la política de archivos permitidos y el acceso a la Biblioteca de Medios. La configuración del
 * selector (`epConfig.media`) la publica Assets desde `config/media.php`.
 */
final class MediaServiceProvider implements ServiceProvider {

	/**
	 * Registra los servicios.
	 *
	 * @param Container $container Contenedor del plugin.
	 */
	public function register( Container $container ): void {
		$container->set( MediaPolicy::class, static fn( Container $c ): MediaPolicy => MediaPolicy::from_config( $c->get( Config::class ) ) );
		$container->set( AttachmentGateway::class, static fn(): AttachmentGateway => new WpAttachmentGateway() );
	}
}
