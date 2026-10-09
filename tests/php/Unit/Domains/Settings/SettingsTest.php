<?php
/**
 * Pruebas de los ajustes y de la desinstalación.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Domains\Settings;

use Brain\Monkey\Functions;
use Mockery;
use Probolsas\Eventos\Core\Admin\AdminMenu;
use Probolsas\Eventos\Core\Admin\AdminPage;
use Probolsas\Eventos\Core\Container;
use Probolsas\Eventos\Core\Database\Migrator;
use Probolsas\Eventos\Core\Database\Tables;
use Probolsas\Eventos\Core\Lifecycle\Uninstaller;
use Probolsas\Eventos\Core\Lifecycle\UninstallTask;
use Probolsas\Eventos\Core\Security\Capabilities;
use Probolsas\Eventos\Core\Settings\PluginSettings;
use Probolsas\Eventos\Core\View\View;
use Probolsas\Eventos\Domains\Settings\Presentation\SettingsPage;
use Probolsas\Eventos\Domains\Settings\Presentation\SettingsRestController;
use Probolsas\Eventos\Domains\Settings\SettingsServiceProvider;
use Probolsas\Eventos\Tests\Unit\Support\FakeWpdb;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;
use WP_Error;
use WP_REST_Request;

/**
 * «Borrar todos los datos al desinstalar» (H-402, D-16): falso por defecto, su API y el desinstalador.
 *
 * @covers \Probolsas\Eventos\Core\Settings\PluginSettings
 * @covers \Probolsas\Eventos\Core\Lifecycle\Uninstaller
 * @covers \Probolsas\Eventos\Domains\Settings\Presentation\SettingsRestController
 * @covers \Probolsas\Eventos\Domains\Settings\Presentation\SettingsPage
 * @covers \Probolsas\Eventos\Domains\Settings\SettingsServiceProvider
 */
final class SettingsTest extends UnitTestCase {

	/**
	 * Opciones en memoria.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'get_option' )->alias( fn( string $name, mixed $fallback = false ): mixed => $this->options[ $name ] ?? $fallback );
		Functions\when( 'update_option' )->alias(
			function ( string $name, mixed $value ): bool {
				$this->options[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( string $name ): bool {
				unset( $this->options[ $name ] );
				return true;
			}
		);
	}

	public function test_data_is_kept_by_default(): void {
		$settings = new PluginSettings();

		$this->assertFalse( $settings->delete_data_on_uninstall() );
		$this->assertSame( [ 'delete_data_on_uninstall' => false ], $settings->to_array() );

		$settings->set_delete_data_on_uninstall( true );
		$this->assertTrue( $settings->delete_data_on_uninstall() );
		$this->assertSame( '1', $this->options[ PluginSettings::DELETE_DATA_OPTION ] );

		$settings->forget();
		$this->assertArrayNotHasKey( PluginSettings::DELETE_DATA_OPTION, $this->options );
	}

	public function test_uninstall_without_the_checkbox_only_removes_capabilities(): void {
		[ $uninstaller, $wpdb, $task, $role ] = $this->uninstaller();
		$role->expects( 'remove_cap' )->once()->with( Capabilities::MANAGE );
		$wpdb->shouldNotReceive( 'query' );
		$task->expects( 'run' )->never();

		$uninstaller->uninstall();

		$this->assertSame( 3, $this->options[ Migrator::OPTION ], 'La versión del esquema se conserva con las tablas.' );
	}

	public function test_uninstall_with_the_checkbox_removes_everything(): void {
		( new PluginSettings() )->set_delete_data_on_uninstall( true );
		[ $uninstaller, $wpdb, $task, $role ] = $this->uninstaller();
		$role->expects( 'remove_cap' )->once()->with( Capabilities::MANAGE );
		$wpdb->expects( 'query' )->atLeast()->once();
		$task->expects( 'run' )->once();

		$uninstaller->uninstall();

		$this->assertArrayNotHasKey( Migrator::OPTION, $this->options );
		$this->assertArrayNotHasKey( PluginSettings::DELETE_DATA_OPTION, $this->options, 'El ajuste también se borra.' );
	}

	public function test_the_api_reads_and_saves_the_setting(): void {
		$controller = new SettingsRestController( new PluginSettings() );

		$this->assertSame( [ 'delete_data_on_uninstall' => false ], $controller->show()->get_data()['data'] );

		foreach ( [ true, 'true', '1', 1 ] as $yes ) {
			$this->assertTrue( $controller->update( new WP_REST_Request( [ 'delete_data_on_uninstall' => $yes ] ) )->get_data()['data']['delete_data_on_uninstall'], (string) wp_json_encode( $yes ) );
		}
		foreach ( [ false, 'false', '0', 0, 'FALSE' ] as $no ) {
			$this->assertFalse( $controller->update( new WP_REST_Request( [ 'delete_data_on_uninstall' => $no ] ) )->get_data()['data']['delete_data_on_uninstall'] );
		}
	}

	/**
	 * Valores que no son sí o no.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public static function invalid_values(): array {
		return [
			'falta'  => [ null ],
			'texto'  => [ 'quizá' ],
			'número' => [ 2 ],
			'lista'  => [ [ true ] ],
		];
	}

	/**
	 * Un valor que no es sí o no responde 422 y no cambia el ajuste.
	 *
	 * @dataProvider invalid_values
	 *
	 * @param mixed $value Valor recibido.
	 */
	public function test_the_api_rejects_values_that_are_not_yes_or_no( mixed $value ): void {
		( new PluginSettings() )->set_delete_data_on_uninstall( true );
		$controller = new SettingsRestController( new PluginSettings() );

		$response = $controller->update( new WP_REST_Request( null === $value ? [] : [ 'delete_data_on_uninstall' => $value ] ) );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 422, $response->get_error_data()['status'] );
		$this->assertSame( [ 'El campo «Borrar todos los datos al desinstalar» debe ser sí o no.' ], $response->get_error_data()['errors']['delete_data_on_uninstall'] );
		$this->assertTrue( ( new PluginSettings() )->delete_data_on_uninstall() );
	}

	public function test_routes_page_and_provider(): void {
		$routes = [];
		Functions\when( 'register_rest_route' )->alias(
			static function ( string $route_namespace, string $route, array $endpoints ) use ( &$routes ): void {
				foreach ( $endpoints as $endpoint ) {
					$routes[] = $endpoint['methods'] . ' /' . $route_namespace . $route . ' → ' . $endpoint['permission_callback'][1];
				}
			}
		);
		( new SettingsRestController( new PluginSettings() ) )->register_routes();
		$this->assertSame( [ 'GET /eventos/v1/settings → can_manage', 'PUT /eventos/v1/settings → can_manage' ], $routes );

		$container = new Container();
		$container->set( PluginSettings::class, static fn(): PluginSettings => new PluginSettings() );
		$container->set( View::class, fn(): View => new View( $this->plugin_dir() . 'templates' ) );
		( new SettingsServiceProvider() )->register( $container );
		$pages = $container->tagged( AdminMenu::PAGES_TAG, AdminPage::class );
		$this->assertSame( [ SettingsPage::class ], array_map( 'get_class', $pages ) );
		$this->assertSame( [ 'eventos-probolsas-ajustes', 'Ajustes', 'eventos_manage', 30 ], [ $pages[0]->slug(), $pages[0]->menu_title(), $pages[0]->capability(), $pages[0]->position() ] );

		Functions\expect( 'add_action' )->once()->with( 'rest_api_init', Mockery::type( 'array' ) );
		( new SettingsServiceProvider() )->boot( $container );
	}

	/**
	 * Desinstalador real con dobles de WordPress: la base de datos, un rol y una tarea de un dominio.
	 *
	 * @return array{0: Uninstaller, 1: Mockery\MockInterface, 2: Mockery\MockInterface, 3: Mockery\MockInterface}
	 */
	private function uninstaller(): array {
		$wpdb = FakeWpdb::create();
		$role = Mockery::mock( 'WP_Role' );
		Functions\when( 'wp_roles' )->justReturn( (object) [ 'role_objects' => [ 'administrator' => $role ] ] );
		$task                              = Mockery::mock( UninstallTask::class );
		$this->options[ Migrator::OPTION ] = 3;

		return [ new Uninstaller( new Migrator( [], new Tables( $wpdb ) ), new Capabilities(), new PluginSettings(), [ $task ] ), $wpdb, $task, $role ];
	}
}
