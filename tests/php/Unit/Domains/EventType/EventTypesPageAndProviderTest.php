<?php
/**
 * Pruebas de la pantalla de tipos de evento y del registro del dominio.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Domains\EventType;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Probolsas\Eventos\Core\Admin\AdminMenu;
use Probolsas\Eventos\Core\Admin\AdminPage;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Core\Container;
use Probolsas\Eventos\Core\Database\Migration;
use Probolsas\Eventos\Core\Database\Migrator;
use Probolsas\Eventos\Core\Database\Tables;
use Probolsas\Eventos\Core\View\View;
use Probolsas\Eventos\Domains\EventType\Application\EventTypeService;
use Probolsas\Eventos\Domains\EventType\EventTypeServiceProvider;
use Probolsas\Eventos\Domains\EventType\Infrastructure\SeedDefaultEventTypes;
use Probolsas\Eventos\Domains\EventType\Infrastructure\WpdbEventTypeRepository;
use Probolsas\Eventos\Domains\EventType\Presentation\EventTypeRestController;
use Probolsas\Eventos\Domains\EventType\Presentation\EventTypesPage;
use Probolsas\Eventos\Shared\SharedServiceProvider;
use Probolsas\Eventos\Tests\Unit\Support\FakeWpdb;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * @covers \Probolsas\Eventos\Domains\EventType\Presentation\EventTypesPage
 * @covers \Probolsas\Eventos\Domains\EventType\EventTypeServiceProvider
 */
final class EventTypesPageAndProviderTest extends UnitTestCase {

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
	}

	protected function tear_down(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tear_down();
	}

	public function test_page_metadata(): void {
		$page = new EventTypesPage( new View( $this->plugin_dir() . 'templates' ) );

		$this->assertSame( 'eventos-probolsas-tipos', $page->slug() );
		$this->assertSame( 'Tipos de evento', $page->page_title() );
		$this->assertSame( 'Tipos de evento', $page->menu_title() );
		$this->assertSame( 'eventos_manage', $page->capability() );
		$this->assertSame( 20, $page->position() );
	}

	public function test_page_renders_the_frontend_layout_with_its_mount_point(): void {
		$page = new EventTypesPage( new View( $this->plugin_dir() . 'templates' ) );

		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		// Contrato con el Frontend: templates/admin/layout.php y templates/partials/mount.php.
		$this->assertStringContainsString( 'class="wrap ep-app" data-ep-screen="eventos-probolsas-tipos"', $html );
		$this->assertStringContainsString( '<i class="fa-solid fa-tags"></i>', $html );
		$this->assertStringContainsString( '<h1 class="ep-page-header__title">Tipos de evento</h1>', $html );
		$this->assertStringContainsString( 'id="ep-event-types" class="ep-mount" aria-busy="true"', $html );
	}

	public function test_provider_wires_the_domain_into_the_core(): void {
		$GLOBALS['wpdb'] = FakeWpdb::create(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- El proveedor lee $wpdb; la prueba lo simula.
		$container       = new Container();
		$container->set( Config::class, fn(): Config => Config::from_directory( $this->plugin_dir() . 'config' ) );
		$container->set( View::class, fn(): View => new View( $this->plugin_dir() . 'templates' ) );
		$container->set( Tables::class, static fn(): Tables => new Tables( $GLOBALS['wpdb'] ) );
		( new SharedServiceProvider() )->register( $container );

		( new EventTypeServiceProvider() )->register( $container );

		$this->assertInstanceOf( EventTypeService::class, $container->get( EventTypeService::class ) );
		$this->assertInstanceOf( EventTypeRestController::class, $container->get( EventTypeRestController::class ) );
		$this->assertInstanceOf( WpdbEventTypeRepository::class, $container->get( \Probolsas\Eventos\Domains\EventType\Domain\EventTypeRepository::class ) );
		$this->assertSame( [ EventTypesPage::class ], array_map( 'get_class', $container->tagged( AdminMenu::PAGES_TAG, AdminPage::class ) ) );
		$this->assertSame( [ SeedDefaultEventTypes::class ], array_map( 'get_class', $container->tagged( Migrator::MIGRATIONS_TAG, Migration::class ) ) );
	}

	public function test_boot_registers_the_api_and_the_client_rules(): void {
		$GLOBALS['wpdb'] = FakeWpdb::create(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- El proveedor lee $wpdb; la prueba lo simula.
		$container       = new Container();
		$container->set( Config::class, fn(): Config => Config::from_directory( $this->plugin_dir() . 'config' ) );
		$container->set( Tables::class, static fn(): Tables => new Tables( $GLOBALS['wpdb'] ) );
		( new SharedServiceProvider() )->register( $container );
		$provider = new EventTypeServiceProvider();
		$provider->register( $container );

		Actions\expectAdded( 'rest_api_init' )->once();
		Filters\expectAdded( 'eventos_client_config' )->once()->with( [ EventTypeServiceProvider::class, 'add_client_rules' ] );

		$provider->boot( $container );
	}

	public function test_client_rules_are_added_without_replacing_other_domains(): void {
		$config = EventTypeServiceProvider::add_client_rules( [ 'rules' => [ 'event' => [ 'title' => [ 'required' => true ] ] ] ] );

		$this->assertSame( [ 'event', 'event_type' ], array_keys( $config['rules'] ) );
		$this->assertSame( EventTypeService::client_rules(), $config['rules']['event_type'] );
		$this->assertArrayHasKey( 'event_type', EventTypeServiceProvider::add_client_rules( [] )['rules'] );
	}
}
