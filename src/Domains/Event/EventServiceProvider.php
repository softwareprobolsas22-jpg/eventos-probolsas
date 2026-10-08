<?php
/**
 * Servicios del dominio Eventos.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event;

use Probolsas\Eventos\Core\Admin\AdminMenu;
use Probolsas\Eventos\Core\BootableProvider;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Core\Container;
use Probolsas\Eventos\Core\Database\Tables;
use Probolsas\Eventos\Core\View\View;
use Probolsas\Eventos\Domains\Event\Application\EventService;
use Probolsas\Eventos\Domains\Event\Domain\EventRepository;
use Probolsas\Eventos\Domains\Event\Infrastructure\WpdbEventRepository;
use Probolsas\Eventos\Domains\Event\Presentation\EventCsvExport;
use Probolsas\Eventos\Domains\Event\Presentation\EventRestController;
use Probolsas\Eventos\Domains\Event\Presentation\EventsPage;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeRepository;
use Probolsas\Eventos\Domains\Media\Domain\AttachmentGateway;
use Probolsas\Eventos\Domains\Media\Domain\MediaPolicy;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Ui\ColorContrast;

/**
 * Registra el repositorio, el servicio, la API, la exportación, la pantalla y las reglas de validación
 * que recibe el navegador. Depende de los dominios Tipos de evento y Medios.
 */
final class EventServiceProvider implements BootableProvider {

	/**
	 * Registra los servicios del dominio.
	 *
	 * @param Container $container Contenedor del plugin.
	 */
	public function register( Container $container ): void {
		$container->set(
			EventRepository::class,
			static function ( Container $c ): EventRepository {
				global $wpdb;

				return new WpdbEventRepository( $wpdb, $c->get( Tables::class ) );
			}
		);

		$container->set(
			EventService::class,
			static fn( Container $c ): EventService => new EventService(
				$c->get( EventRepository::class ),
				$c->get( EventTypeRepository::class ),
				$c->get( AttachmentGateway::class ),
				$c->get( MediaPolicy::class ),
				$c->get( DateFormatter::class ),
				$c->get( Config::class )
			)
		);

		$container->set( EventCsvExport::class, static fn( Container $c ): EventCsvExport => new EventCsvExport( $c->get( DateFormatter::class ), $c->get( Config::class ) ) );

		$container->set(
			EventRestController::class,
			static fn( Container $c ): EventRestController => new EventRestController(
				$c->get( EventService::class ),
				$c->get( EventTypeRepository::class ),
				$c->get( AttachmentGateway::class ),
				$c->get( EventCsvExport::class ),
				$c->get( DateFormatter::class ),
				$c->get( ColorContrast::class )
			)
		);

		$container->set( EventsPage::class, static fn( Container $c ): EventsPage => new EventsPage( $c->get( View::class ) ) );
		$container->tag( AdminMenu::PAGES_TAG, EventsPage::class );
	}

	/**
	 * Registra la API y publica las reglas de validación del formulario (R-24).
	 *
	 * @param Container $container Contenedor del plugin.
	 */
	public function boot( Container $container ): void {
		$container->get( EventRestController::class )->register();

		add_filter( 'eventos_client_config', [ self::class, 'add_client_rules' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_media_library' ] );
	}

	/**
	 * Carga el selector de la Biblioteca de Medios (`wp.media`) en la pantalla de eventos: el formulario
	 * elige ahí la imagen o el PDF (R-09). Solo en esa pantalla, para no cargarlo en todo wp-admin (R-17).
	 *
	 * @param string $hook_suffix Pantalla actual de wp-admin.
	 */
	public static function enqueue_media_library( string $hook_suffix ): void {
		if ( 'toplevel_page_' . EventsPage::SLUG === $hook_suffix ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Agrega `rules.event` a la configuración del navegador.
	 *
	 * @param array<string, mixed> $config Configuración del cliente.
	 *
	 * @return array<string, mixed>
	 */
	public static function add_client_rules( array $config ): array {
		$rules           = is_array( $config['rules'] ?? null ) ? $config['rules'] : [];
		$rules['event']  = EventService::client_rules();
		$config['rules'] = $rules;

		return $config;
	}
}
