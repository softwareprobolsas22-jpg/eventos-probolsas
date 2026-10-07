<?php
/**
 * Servicios del núcleo del plugin.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core;

use Probolsas\Eventos\Core\Admin\AdminMenu;
use Probolsas\Eventos\Core\Admin\AdminPage;
use Probolsas\Eventos\Core\Assets\Assets;
use Probolsas\Eventos\Core\Database\Migration;
use Probolsas\Eventos\Core\Database\Migrator;
use Probolsas\Eventos\Core\Database\Tables;
use Probolsas\Eventos\Core\Frontend\Shortcode;
use Probolsas\Eventos\Core\Frontend\ShortcodeRegistry;
use Probolsas\Eventos\Core\Frontend\WidgetRenderer;
use Probolsas\Eventos\Core\Lifecycle\Activator;
use Probolsas\Eventos\Core\Lifecycle\Uninstaller;
use Probolsas\Eventos\Core\Lifecycle\UninstallTask;
use Probolsas\Eventos\Core\Security\Capabilities;
use Probolsas\Eventos\Core\View\View;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Ui\IconCatalog;

/**
 * Infraestructura común: configuración, base de datos, permisos, assets, vistas, menú de administración
 * y shortcodes. Las migraciones las aportan los dominios con la etiqueta `Migrator::MIGRATIONS_TAG`.
 */
final class CoreServiceProvider implements BootableProvider {

	/**
	 * Registra los servicios del núcleo.
	 *
	 * @param Container $container Contenedor del plugin.
	 */
	public function register( Container $container ): void {
		$container->set(
			Config::class,
			static fn( Container $c ): Config => Config::from_directory( $c->get( PluginContext::class )->path( 'config' ) )
		);

		$container->set(
			View::class,
			static fn( Container $c ): View => new View( $c->get( PluginContext::class )->path( 'templates' ) )
		);

		$container->set(
			Tables::class,
			static function (): Tables {
				global $wpdb;

				return new Tables( $wpdb );
			}
		);

		$container->set(
			Migrator::class,
			static fn( Container $c ): Migrator => new Migrator(
				$c->tagged( Migrator::MIGRATIONS_TAG, Migration::class ),
				$c->get( Tables::class )
			)
		);

		$container->set( Capabilities::class, static fn(): Capabilities => new Capabilities() );

		$container->set(
			Activator::class,
			static fn( Container $c ): Activator => new Activator( $c->get( Migrator::class ), $c->get( Capabilities::class ) )
		);

		$container->set(
			Uninstaller::class,
			static fn( Container $c ): Uninstaller => new Uninstaller(
				$c->get( Migrator::class ),
				$c->get( Capabilities::class ),
				$c->tagged( Uninstaller::TASKS_TAG, UninstallTask::class )
			)
		);

		$container->set(
			Assets::class,
			static fn( Container $c ): Assets => new Assets(
				$c->get( PluginContext::class ),
				$c->get( Config::class ),
				$c->get( IconCatalog::class ),
				$c->get( DateFormatter::class )
			)
		);

		$container->set(
			AdminMenu::class,
			static fn( Container $c ): AdminMenu => new AdminMenu(
				$c->tagged( AdminMenu::PAGES_TAG, AdminPage::class ),
				$c->get( Assets::class )
			)
		);

		$container->set(
			WidgetRenderer::class,
			static fn( Container $c ): WidgetRenderer => new WidgetRenderer( $c->get( View::class ), $c->get( Assets::class ) )
		);

		$container->set(
			ShortcodeRegistry::class,
			static fn( Container $c ): ShortcodeRegistry => new ShortcodeRegistry(
				$c->tagged( ShortcodeRegistry::SHORTCODES_TAG, Shortcode::class ),
				$c->get( Assets::class )
			)
		);
	}

	/**
	 * Conecta el núcleo con WordPress y aplica migraciones pendientes tras una actualización.
	 *
	 * @param Container $container Contenedor del plugin.
	 */
	public function boot( Container $container ): void {
		$container->get( Capabilities::class )->register();
		$container->get( Assets::class )->register();

		$migrator = $container->get( Migrator::class );

		if ( $migrator->needs_migration() ) {
			$migrator->migrate();
		}

		if ( is_admin() ) {
			$container->get( AdminMenu::class )->register();
		}

		$container->get( ShortcodeRegistry::class )->register();

		$plugin_file = $container->get( PluginContext::class )->file;

		add_action(
			'init',
			static function () use ( $plugin_file ): void {
				load_plugin_textdomain( 'eventos-probolsas', false, dirname( plugin_basename( $plugin_file ) ) . '/languages' );
			}
		);
	}
}
