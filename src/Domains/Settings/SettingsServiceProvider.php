<?php
/**
 * Servicios del dominio Ajustes.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Settings;

use Probolsas\Eventos\Core\Admin\AdminMenu;
use Probolsas\Eventos\Core\BootableProvider;
use Probolsas\Eventos\Core\Container;
use Probolsas\Eventos\Core\Settings\PluginSettings;
use Probolsas\Eventos\Core\View\View;
use Probolsas\Eventos\Domains\Settings\Presentation\SettingsPage;
use Probolsas\Eventos\Domains\Settings\Presentation\SettingsRestController;

/**
 * Registra la pantalla «Ajustes» y su API (H-402, D-16). Los ajustes viven en el núcleo
 * (`PluginSettings`) porque el desinstalador los consulta.
 */
final class SettingsServiceProvider implements BootableProvider {

	/**
	 * Registra los servicios del dominio.
	 *
	 * @param Container $container Contenedor del plugin.
	 */
	public function register( Container $container ): void {
		$container->set( SettingsRestController::class, static fn( Container $c ): SettingsRestController => new SettingsRestController( $c->get( PluginSettings::class ) ) );
		$container->set( SettingsPage::class, static fn( Container $c ): SettingsPage => new SettingsPage( $c->get( View::class ) ) );
		$container->tag( AdminMenu::PAGES_TAG, SettingsPage::class );
	}

	/**
	 * Registra la API.
	 *
	 * @param Container $container Contenedor del plugin.
	 */
	public function boot( Container $container ): void {
		$container->get( SettingsRestController::class )->register();
	}
}
