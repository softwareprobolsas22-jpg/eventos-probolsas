<?php
/**
 * Servicios del dominio Tipos de evento.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\EventType;

use Probolsas\Eventos\Core\Admin\AdminMenu;
use Probolsas\Eventos\Core\BootableProvider;
use Probolsas\Eventos\Core\Container;
use Probolsas\Eventos\Core\Database\Migrator;
use Probolsas\Eventos\Core\Database\Tables;
use Probolsas\Eventos\Core\View\View;
use Probolsas\Eventos\Domains\EventType\Application\EventTypeService;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeRepository;
use Probolsas\Eventos\Domains\EventType\Infrastructure\CacheFlushingEventTypeRepository;
use Probolsas\Eventos\Domains\EventType\Infrastructure\SeedDefaultEventTypes;
use Probolsas\Eventos\Domains\EventType\Infrastructure\WpdbEventTypeRepository;
use Probolsas\Eventos\Domains\EventType\Presentation\EventTypeRestController;
use Probolsas\Eventos\Domains\EventType\Presentation\EventTypesPage;
use Probolsas\Eventos\Shared\Cache\ResponseCache;
use Probolsas\Eventos\Shared\Text\Slugger;
use Probolsas\Eventos\Shared\Text\TextNormalizer;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Ui\ColorContrast;
use Probolsas\Eventos\Shared\Ui\IconCatalog;

/**
 * Registra el repositorio, el servicio, la API, la pantalla, los datos iniciales y las reglas de
 * validación que recibe el navegador.
 */
final class EventTypeServiceProvider implements BootableProvider {

	/**
	 * Registra los servicios del dominio.
	 *
	 * @param Container $container Contenedor del plugin.
	 */
	public function register( Container $container ): void {
		$container->set(
			EventTypeRepository::class,
			static function ( Container $c ): EventTypeRepository {
				global $wpdb;

				// Cada escritura invalida el feed del calendario y los próximos guardados en caché (H-401).
				return new CacheFlushingEventTypeRepository( new WpdbEventTypeRepository( $wpdb, $c->get( Tables::class ) ), $c->get( ResponseCache::class ) );
			}
		);

		$container->set(
			EventTypeService::class,
			static fn( Container $c ): EventTypeService => new EventTypeService(
				$c->get( EventTypeRepository::class ),
				$c->get( TextNormalizer::class ),
				$c->get( Slugger::class ),
				$c->get( DateFormatter::class ),
				$c->get( IconCatalog::class )
			)
		);

		$container->set(
			EventTypeRestController::class,
			static fn( Container $c ): EventTypeRestController => new EventTypeRestController(
				$c->get( EventTypeService::class ),
				$c->get( DateFormatter::class ),
				$c->get( ColorContrast::class )
			)
		);

		$container->set( EventTypesPage::class, static fn( Container $c ): EventTypesPage => new EventTypesPage( $c->get( View::class ) ) );
		$container->tag( AdminMenu::PAGES_TAG, EventTypesPage::class );

		$container->set(
			SeedDefaultEventTypes::class,
			static fn( Container $c ): SeedDefaultEventTypes => new SeedDefaultEventTypes( $c->get( EventTypeRepository::class ), $c->get( EventTypeService::class ) )
		);
		$container->tag( Migrator::MIGRATIONS_TAG, SeedDefaultEventTypes::class );
	}

	/**
	 * Registra la API y publica las reglas de validación del formulario (R-24).
	 *
	 * @param Container $container Contenedor del plugin.
	 */
	public function boot( Container $container ): void {
		$container->get( EventTypeRestController::class )->register();

		add_filter( 'eventos_client_config', [ self::class, 'add_client_rules' ] );
	}

	/**
	 * Agrega `rules.event_type` a la configuración del navegador.
	 *
	 * @param array<string, mixed> $config Configuración del cliente.
	 *
	 * @return array<string, mixed>
	 */
	public static function add_client_rules( array $config ): array {
		$rules               = is_array( $config['rules'] ?? null ) ? $config['rules'] : [];
		$rules['event_type'] = EventTypeService::client_rules();
		$config['rules']     = $rules;

		return $config;
	}
}
