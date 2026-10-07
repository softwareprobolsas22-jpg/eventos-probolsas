<?php
/**
 * Pruebas del menú de administración.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Core\Admin;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Probolsas\Eventos\Core\Admin\AdminMenu;
use Probolsas\Eventos\Core\Admin\AdminPage;
use Probolsas\Eventos\Core\Assets\Assets;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Core\PluginContext;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Time\SystemClock;
use Probolsas\Eventos\Shared\Ui\IconCatalog;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * @covers \Probolsas\Eventos\Core\Admin\AdminMenu
 */
final class AdminMenuTest extends UnitTestCase {

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
	}

	public function test_registers_root_menu_and_submenus_sorted_by_position(): void {
		$root  = $this->page( AdminMenu::ROOT_SLUG, 0 );
		$last  = $this->page( 'ep-tipos', 20 );
		$first = $this->page( 'ep-eventos', 10 );

		Functions\expect( 'add_menu_page' )
			->once()
			->with( 'Título eventos-probolsas', 'Eventos', 'eventos_manage', AdminMenu::ROOT_SLUG, [ $root, 'render' ], 'dashicons-calendar-alt', 26 );

		$registered = [];
		Functions\when( 'add_submenu_page' )->alias(
			static function ( string $parent_slug, string $title, string $menu, string $cap, string $slug ) use ( &$registered ): string {
				$registered[] = $slug;
				return 'hook-' . $slug;
			}
		);
		Actions\expectAdded( 'load-hook-eventos-probolsas' )->once();
		Actions\expectAdded( 'load-hook-ep-eventos' )->once();
		Actions\expectAdded( 'load-hook-ep-tipos' )->once();

		$this->menu( [ $last, $root, $first ] )->add_pages();

		$this->assertSame( [ 'eventos-probolsas', 'ep-eventos', 'ep-tipos' ], $registered );
	}

	public function test_does_not_register_menu_without_root_page(): void {
		Functions\expect( 'add_menu_page' )->never();
		Functions\expect( 'add_submenu_page' )->never();

		$this->menu( [ $this->page( 'ep-eventos', 10 ) ] )->add_pages();
	}

	public function test_body_class_and_assets_are_added_only_on_plugin_screens(): void {
		$menu = $this->menu( [] );

		$this->assertSame( 'folded', $menu->add_body_class( 'folded' ) );

		Actions\expectAdded( 'admin_enqueue_scripts' )->once();
		$menu->prepare_plugin_screen();

		$this->assertSame( 'folded ' . AdminMenu::BODY_CLASS, $menu->add_body_class( 'folded' ) );
	}

	/**
	 * Menú con las pantallas dadas.
	 *
	 * @param list<AdminPage> $pages Pantallas.
	 */
	private function menu( array $pages ): AdminMenu {
		$config = new Config( [] );
		$assets = new Assets( new PluginContext( '/tmp/eventos-probolsas.php', '/tmp/', 'https://intranet.test/', '0.1.0' ), $config, new IconCatalog( $config ), DateFormatter::from_config( $config, new SystemClock() ) );

		return new AdminMenu( $pages, $assets );
	}

	/**
	 * Pantalla falsa.
	 *
	 * @param string $slug     Slug.
	 * @param int    $position Posición.
	 */
	private function page( string $slug, int $position ): AdminPage {
		return new class( $slug, $position ) implements AdminPage {

			public function __construct( private string $slug, private int $position ) {}

			public function slug(): string {
				return $this->slug;
			}

			public function page_title(): string {
				return 'Título ' . $this->slug;
			}

			public function menu_title(): string {
				return 'Menú ' . $this->slug;
			}

			public function capability(): string {
				return 'eventos_manage';
			}

			public function position(): int {
				return $this->position;
			}

			public function render(): void {}
		};
	}
}
