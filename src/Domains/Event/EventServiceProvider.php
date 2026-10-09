<?php
/**
 * Servicios del dominio Eventos.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event;

use DateTimeZone;
use Probolsas\Eventos\Core\Admin\AdminMenu;
use Probolsas\Eventos\Core\BootableProvider;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Core\Container;
use Probolsas\Eventos\Core\Database\Tables;
use Probolsas\Eventos\Core\Frontend\ShortcodeRegistry;
use Probolsas\Eventos\Core\Frontend\WidgetRenderer;
use Probolsas\Eventos\Core\View\View;
use Probolsas\Eventos\Domains\Event\Application\CalendarService;
use Probolsas\Eventos\Domains\Event\Application\EventService;
use Probolsas\Eventos\Domains\Event\Domain\EventRepository;
use Probolsas\Eventos\Domains\Event\Infrastructure\CacheFlushingEventRepository;
use Probolsas\Eventos\Domains\Event\Infrastructure\WpdbEventRepository;
use Probolsas\Eventos\Domains\Event\Presentation\CalendarFeedController;
use Probolsas\Eventos\Domains\Event\Presentation\CalendarShortcode;
use Probolsas\Eventos\Domains\Event\Presentation\EventCsvExport;
use Probolsas\Eventos\Domains\Event\Presentation\EventPresenter;
use Probolsas\Eventos\Domains\Event\Presentation\EventRestController;
use Probolsas\Eventos\Domains\Event\Presentation\EventsPage;
use Probolsas\Eventos\Domains\Event\Presentation\IcsCalendar;
use Probolsas\Eventos\Domains\Event\Presentation\IcsController;
use Probolsas\Eventos\Domains\Event\Presentation\ShortcodeTypes;
use Probolsas\Eventos\Domains\Event\Presentation\UpcomingController;
use Probolsas\Eventos\Domains\Event\Presentation\UpcomingShortcode;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeRepository;
use Probolsas\Eventos\Domains\Media\Domain\AttachmentGateway;
use Probolsas\Eventos\Domains\Media\Domain\MediaPolicy;
use Probolsas\Eventos\Shared\Cache\ResponseCache;
use Probolsas\Eventos\Shared\Time\Clock;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Ui\ColorContrast;

/**
 * Registra el repositorio, los servicios, la API (gestión, calendario, próximos y `.ics`), la exportación,
 * la pantalla, los shortcodes de la intranet y las reglas de validación que recibe el navegador. Depende
 * de los dominios Tipos de evento y Medios.
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

				// Cada escritura invalida el feed del calendario y los próximos guardados en caché (H-401).
				return new CacheFlushingEventRepository( new WpdbEventRepository( $wpdb, $c->get( Tables::class ) ), $c->get( ResponseCache::class ) );
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

		$container->set( CalendarService::class, static fn( Container $c ): CalendarService => new CalendarService( $c->get( EventRepository::class ), $c->get( DateFormatter::class ) ) );

		$container->set( EventCsvExport::class, static fn( Container $c ): EventCsvExport => new EventCsvExport( $c->get( DateFormatter::class ), $c->get( Config::class ) ) );

		$container->set(
			EventPresenter::class,
			static fn( Container $c ): EventPresenter => new EventPresenter(
				$c->get( EventService::class ),
				$c->get( EventTypeRepository::class ),
				$c->get( AttachmentGateway::class ),
				$c->get( DateFormatter::class ),
				$c->get( ColorContrast::class )
			)
		);

		$container->set(
			EventRestController::class,
			static fn( Container $c ): EventRestController => new EventRestController(
				$c->get( EventService::class ),
				$c->get( EventPresenter::class ),
				$c->get( EventCsvExport::class ),
				$c->get( DateFormatter::class )
			)
		);

		$this->register_calendar( $container );

		$container->set( EventsPage::class, static fn( Container $c ): EventsPage => new EventsPage( $c->get( View::class ) ) );
		$container->tag( AdminMenu::PAGES_TAG, EventsPage::class );
	}

	/**
	 * Registra lo que usan los colaboradores en la intranet: feed del calendario, próximos eventos, `.ics`
	 * y los shortcodes `[eventos_calendario]` y `[eventos_proximos]`.
	 *
	 * @param Container $container Contenedor del plugin.
	 */
	private function register_calendar( Container $container ): void {
		$container->set(
			CalendarFeedController::class,
			static fn( Container $c ): CalendarFeedController => new CalendarFeedController( $c->get( CalendarService::class ), $c->get( EventPresenter::class ), $c->get( ColorContrast::class ), $c->get( ResponseCache::class ) )
		);

		$container->set(
			UpcomingController::class,
			static fn( Container $c ): UpcomingController => new UpcomingController( $c->get( CalendarService::class ), $c->get( EventPresenter::class ), $c->get( ResponseCache::class ), $c->get( DateFormatter::class ) )
		);

		$container->set(
			IcsCalendar::class,
			static fn( Container $c ): IcsCalendar => new IcsCalendar(
				new DateTimeZone( (string) $c->get( Config::class )->get( 'ui.timezone', 'America/Bogota' ) ),
				(string) wp_parse_url( home_url(), PHP_URL_HOST )
			)
		);

		$container->set(
			IcsController::class,
			static fn( Container $c ): IcsController => new IcsController(
				$c->get( EventService::class ),
				$c->get( EventPresenter::class ),
				$c->get( IcsCalendar::class ),
				$c->get( Clock::class )
			)
		);

		$container->set( ShortcodeTypes::class, static fn( Container $c ): ShortcodeTypes => new ShortcodeTypes( $c->get( EventTypeRepository::class ) ) );
		$container->set( CalendarShortcode::class, static fn( Container $c ): CalendarShortcode => new CalendarShortcode( $c->get( WidgetRenderer::class ), $c->get( ShortcodeTypes::class ) ) );
		$container->set( UpcomingShortcode::class, static fn( Container $c ): UpcomingShortcode => new UpcomingShortcode( $c->get( WidgetRenderer::class ), $c->get( ShortcodeTypes::class ) ) );
		$container->tag( ShortcodeRegistry::SHORTCODES_TAG, CalendarShortcode::class );
		$container->tag( ShortcodeRegistry::SHORTCODES_TAG, UpcomingShortcode::class );
	}

	/**
	 * Registra la API y publica las reglas de validación del formulario (R-24).
	 *
	 * @param Container $container Contenedor del plugin.
	 */
	public function boot( Container $container ): void {
		$container->get( EventRestController::class )->register();
		$container->get( CalendarFeedController::class )->register();
		$container->get( UpcomingController::class )->register();
		$container->get( IcsController::class )->register();

		add_filter( 'eventos_client_config', [ self::class, 'add_client_rules' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_media_library' ] );

		// El feed y los próximos llevan la dirección y el tipo del adjunto: si se edita o se borra en la
		// Biblioteca de Medios, las respuestas guardadas dejan de valer (H-401).
		$cache = $container->get( ResponseCache::class );
		add_action( 'edit_attachment', [ $cache, 'flush' ] );
		add_action( 'delete_attachment', [ $cache, 'flush' ] );

		// Tipos y usuarios leídos una vez por petición REST, no por proceso.
		add_filter( 'rest_pre_dispatch', [ $container->get( EventPresenter::class ), 'forget' ] );
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
