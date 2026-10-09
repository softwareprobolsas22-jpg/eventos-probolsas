<?php
/**
 * Servicios del dominio Dashboard.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Dashboard;

use Probolsas\Eventos\Core\BootableProvider;
use Probolsas\Eventos\Core\Container;
use Probolsas\Eventos\Domains\Dashboard\Application\DashboardService;
use Probolsas\Eventos\Domains\Dashboard\Presentation\DashboardRestController;
use Probolsas\Eventos\Domains\Event\Domain\EventRepository;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeRepository;
use Probolsas\Eventos\Shared\Time\DateFormatter;

/**
 * Registra el resumen de las tarjetas de «Eventos» (H-206) y su API. Depende de los dominios Eventos y
 * Tipos de evento; no tiene pantalla propia (D-17).
 */
final class DashboardServiceProvider implements BootableProvider {

	/**
	 * Registra los servicios del dominio.
	 *
	 * @param Container $container Contenedor del plugin.
	 */
	public function register( Container $container ): void {
		$container->set(
			DashboardService::class,
			static fn( Container $c ): DashboardService => new DashboardService( $c->get( EventRepository::class ), $c->get( EventTypeRepository::class ), $c->get( DateFormatter::class ) )
		);

		$container->set( DashboardRestController::class, static fn( Container $c ): DashboardRestController => new DashboardRestController( $c->get( DashboardService::class ) ) );
	}

	/**
	 * Registra la API.
	 *
	 * @param Container $container Contenedor del plugin.
	 */
	public function boot( Container $container ): void {
		$container->get( DashboardRestController::class )->register();
	}
}
