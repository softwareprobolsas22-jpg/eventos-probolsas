<?php
/**
 * Servicios del shared kernel.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared;

use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Core\Container;
use Probolsas\Eventos\Core\ServiceProvider;
use Probolsas\Eventos\Shared\Text\Slugger;
use Probolsas\Eventos\Shared\Text\TextNormalizer;
use Probolsas\Eventos\Shared\Time\Clock;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Time\SystemClock;
use Probolsas\Eventos\Shared\Ui\ColorContrast;
use Probolsas\Eventos\Shared\Ui\IconCatalog;

/**
 * Servicios reutilizables por todos los dominios: reloj, fechas, normalización de texto, slugs e íconos.
 */
final class SharedServiceProvider implements ServiceProvider {

	/**
	 * Registra los servicios.
	 *
	 * @param Container $container Contenedor del plugin.
	 */
	public function register( Container $container ): void {
		$container->set( Clock::class, static fn(): Clock => new SystemClock() );

		$container->set(
			DateFormatter::class,
			static fn( Container $c ): DateFormatter => DateFormatter::from_config( $c->get( Config::class ), $c->get( Clock::class ) )
		);

		$container->set( TextNormalizer::class, static fn(): TextNormalizer => new TextNormalizer() );

		$container->set(
			Slugger::class,
			static fn( Container $c ): Slugger => new Slugger( $c->get( TextNormalizer::class ) )
		);

		$container->set( IconCatalog::class, static fn( Container $c ): IconCatalog => new IconCatalog( $c->get( Config::class ) ) );

		$container->set( ColorContrast::class, static fn(): ColorContrast => new ColorContrast() );
	}
}
