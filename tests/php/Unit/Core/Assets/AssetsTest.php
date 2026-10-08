<?php
/**
 * Pruebas del gestor de assets.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Core\Assets;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use Mockery;
use Probolsas\Eventos\Core\Assets\Assets;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Core\PluginContext;
use Probolsas\Eventos\Shared\Time\Clock;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Ui\IconCatalog;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * @covers \Probolsas\Eventos\Core\Assets\Assets
 */
final class AssetsTest extends UnitTestCase {

	private const BASE_URL = 'https://intranet.test/wp-content/plugins/eventos-probolsas/';

	/**
	 * Directorio temporal que simula la raíz del plugin.
	 *
	 * @var string
	 */
	private string $plugin_dir;

	protected function set_up(): void {
		parent::set_up();

		$this->plugin_dir = sys_get_temp_dir() . '/ep-assets-' . uniqid() . '/';
		mkdir( $this->plugin_dir . 'assets/dist/js', 0777, true );
		mkdir( $this->plugin_dir . 'assets/dist/css', 0777, true );
		touch( $this->plugin_dir . 'assets/dist/js/admin.js' );

		Functions\when( 'rest_url' )->alias( static fn( string $path ): string => 'https://intranet.test/wp-json/' . $path );
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'wp_create_nonce' )->justReturn( 'nonce-123' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'get_option' )->alias( static fn( string $name, mixed $fallback = false ): mixed => 'start_of_week' === $name ? '1' : $fallback );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'wp_login_url' )->justReturn( 'https://intranet.test/wp-login.php' );
	}

	protected function tear_down(): void {
		foreach ( [ 'js/admin.js', 'css/admin.css', 'css/vendor.css', '.vite/manifest.json' ] as $file ) {
			if ( file_exists( $this->plugin_dir . 'assets/dist/' . $file ) ) {
				unlink( $this->plugin_dir . 'assets/dist/' . $file );
			}
		}
		if ( is_dir( $this->plugin_dir . 'assets/dist/.vite' ) ) {
			rmdir( $this->plugin_dir . 'assets/dist/.vite' );
		}
		foreach ( [ 'assets/dist/js', 'assets/dist/css', 'assets/dist', 'assets', '' ] as $dir ) {
			rmdir( $this->plugin_dir . $dir );
		}

		parent::tear_down();
	}

	public function test_client_config_exposes_rest_data_and_ui_ssot(): void {
		$config = $this->assets()->client_config();

		$this->assertSame( '0.1.0', $config['version'] );
		$this->assertSame( 'https://intranet.test/wp-json/eventos/v1/', $config['restUrl'] );
		$this->assertSame( 'nonce-123', $config['restNonce'] );
		$this->assertSame( [ 'timezone' => 'America/Bogota' ], $config['ui'] );
		$this->assertSame(
			[
				[
					'key'      => 'award',
					'label'    => 'Distinción',
					'keywords' => 'calidad',
				],
			],
			$config['icons']
		);
		$this->assertSame( [ 'allowed_mimes' => [ 'application/pdf' ] ], $config['media'] );
		$this->assertSame( 1, $config['firstDay'] );
		$this->assertSame( [ 'manage' => false ], $config['can'] );
		$this->assertSame( 'https://intranet.test/wp-login.php', $config['loginUrl'] );
	}

	public function test_today_is_the_colombian_date_even_when_utc_is_already_tomorrow(): void {
		// 8:30 p. m. del 7 de octubre en Bogotá = 01:30 del 8 de octubre en UTC (defecto del legado).
		$this->assertSame( '2026-10-07', $this->assets()->client_config()['today'] );
	}

	public function test_managers_are_flagged_for_the_interface(): void {
		Functions\when( 'current_user_can' )->alias( static fn( string $capability ): bool => 'eventos_manage' === $capability );

		$this->assertSame( [ 'manage' => true ], $this->assets()->client_config()['can'] );
	}

	public function test_domains_can_extend_the_client_config(): void {
		Filters\expectApplied( 'eventos_client_config' )->once()->andReturnUsing(
			static function ( array $config ): array {
				$config['rules'] = [ 'event' => [ 'title' => [ 'required' => true ] ] ];

				return $config;
			}
		);

		$this->assertSame( [ 'event' => [ 'title' => [ 'required' => true ] ] ], $this->assets()->client_config()['rules'] );
	}

	public function test_enqueue_admin_loads_style_script_and_config(): void {
		touch( $this->plugin_dir . 'assets/dist/css/admin.css' );

		Functions\expect( 'wp_enqueue_style' )
			->once()
			->with( 'ep-admin', self::BASE_URL . 'assets/dist/css/admin.css', [], Mockery::type( 'string' ) );
		Functions\expect( 'wp_enqueue_script' )
			->once()
			->with( 'ep-admin', self::BASE_URL . 'assets/dist/js/admin.js', [ 'wp-i18n' ], Mockery::type( 'string' ), [ 'in_footer' => true ] );
		Functions\expect( 'wp_set_script_translations' )
			->once()
			->with( 'ep-admin', 'eventos-probolsas', $this->plugin_dir . 'languages' );
		Functions\expect( 'wp_add_inline_script' )
			->once()
			->with( 'ep-admin', Mockery::pattern( '/^window\.epConfig = \{.*"timezone":"America\\\\\/Bogota".*\};$/' ), 'before' );

		$this->assets()->enqueue_admin();
	}

	public function test_enqueue_admin_also_loads_the_shared_styles_listed_in_the_vite_manifest(): void {
		mkdir( $this->plugin_dir . 'assets/dist/.vite' );
		touch( $this->plugin_dir . 'assets/dist/css/admin.css' );
		touch( $this->plugin_dir . 'assets/dist/css/vendor.css' );
		file_put_contents(
			$this->plugin_dir . 'assets/dist/.vite/manifest.json',
			(string) json_encode(
				[
					'_vendor.js'                   => [
						'file' => 'js/chunks/vendor.js',
						'css'  => [ 'css/vendor.css' ],
					],
					'_api.js'                      => [
						'file'    => 'js/chunks/api.js',
						'imports' => [ '_vendor.js' ],
					],
					'assets/src/js/pages/admin.js' => [
						'file'           => 'js/admin.js',
						'css'            => [ 'css/admin.css' ],
						'imports'        => [ '_vendor.js', '_api.js' ],
						'dynamicImports' => [ 'assets/src/js/screens/documents.js' ],
						'isEntry'        => true,
					],
				]
			)
		);

		Functions\when( 'wp_json_file_decode' )->alias( static fn( string $file ): mixed => json_decode( (string) file_get_contents( $file ), true ) );
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'wp_enqueue_script' )->justReturn( null );
		Functions\when( 'wp_set_script_translations' )->justReturn( true );
		Functions\when( 'wp_add_inline_script' )->justReturn( true );

		$styles = [];
		Functions\when( 'wp_enqueue_style' )->alias(
			static function ( string $handle, string $src, array $deps, mixed $version ) use ( &$styles ): void {
				$styles[] = [ $handle, basename( $src ), $deps, null === $version ];
			}
		);

		$this->assets()->enqueue_admin();

		$this->assertSame(
			[
				[ 'ep-shared-vendor', 'vendor.css', [], true ],
				[ 'ep-admin', 'admin.css', [ 'ep-shared-vendor' ], false ],
			],
			$styles,
			'El CSS compartido se encola una vez, antes que el de la entrada y sin ?ver= (su nombre lleva hash).'
		);
	}

	public function test_the_entry_style_goes_last_even_if_the_manifest_lists_it_first(): void {
		mkdir( $this->plugin_dir . 'assets/dist/.vite' );
		touch( $this->plugin_dir . 'assets/dist/css/admin.css' );
		file_put_contents(
			$this->plugin_dir . 'assets/dist/.vite/manifest.json',
			(string) json_encode(
				[
					'_runtime.js'                  => [
						'file' => 'js/chunks/runtime.js',
						'css'  => [ 'css/runtime-abc.css' ],
					],
					'assets/src/js/pages/admin.js' => [
						'file'    => 'js/admin.js',
						// Así lo escribe Vite cuando una hoja la comparten las dos entradas (Bootstrap).
						'css'     => [ 'css/admin.css', 'css/bootstrap-def.css' ],
						'imports' => [ '_runtime.js' ],
						'isEntry' => true,
					],
				]
			)
		);

		Functions\when( 'wp_json_file_decode' )->alias( static fn( string $file ): mixed => json_decode( (string) file_get_contents( $file ), true ) );
		Functions\when( 'sanitize_key' )->alias( static fn( string $key ): string => strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $key ) ) );
		Functions\when( 'wp_enqueue_script' )->justReturn( null );
		Functions\when( 'wp_set_script_translations' )->justReturn( true );
		Functions\when( 'wp_add_inline_script' )->justReturn( true );

		$styles = [];
		Functions\when( 'wp_enqueue_style' )->alias(
			static function ( string $handle, string $src, array $deps ) use ( &$styles ): void {
				$styles[] = [ $handle, basename( $src ), $deps ];
			}
		);

		$this->assets()->enqueue_admin();

		$this->assertSame(
			[
				[ 'ep-shared-runtime-abc', 'runtime-abc.css', [] ],
				[ 'ep-shared-bootstrap-def', 'bootstrap-def.css', [] ],
				[ 'ep-admin', 'admin.css', [ 'ep-shared-runtime-abc', 'ep-shared-bootstrap-def' ] ],
			],
			$styles,
			'QA-039: el CSS del plugin depende de todas las hojas compartidas y gana en la cascada.'
		);
	}

	public function test_enqueue_admin_skips_style_when_bundle_has_no_css(): void {
		Functions\expect( 'wp_enqueue_style' )->never();
		Functions\when( 'wp_enqueue_script' )->justReturn( null );
		Functions\when( 'wp_set_script_translations' )->justReturn( true );
		Functions\when( 'wp_add_inline_script' )->justReturn( true );

		$this->assets()->enqueue_admin();
	}

	public function test_only_enqueued_plugin_scripts_are_printed_as_modules(): void {
		Functions\when( 'wp_enqueue_script' )->justReturn( null );
		Functions\when( 'wp_set_script_translations' )->justReturn( true );
		Functions\when( 'wp_add_inline_script' )->justReturn( true );
		$assets = $this->assets();

		$this->assertArrayNotHasKey( 'type', $assets->add_module_type( [ 'id' => 'ep-admin-js' ] ) );

		$assets->enqueue_admin();

		$this->assertSame( 'module', $assets->add_module_type( [ 'id' => 'ep-admin-js' ] )['type'] );
		$this->assertArrayNotHasKey( 'type', $assets->add_module_type( [ 'id' => 'jquery-core-js' ] ) );
		$this->assertArrayNotHasKey( 'type', $assets->add_module_type( [ 'id' => 'ep-admin-js-before' ] ) );
		$this->assertArrayNotHasKey( 'type', $assets->add_module_type( [ 'src' => 'sin-id.js' ] ) );
	}

	public function test_enqueue_public_loads_the_public_entry_only_once(): void {
		Functions\expect( 'wp_enqueue_script' )
			->once()
			->with( 'ep-public', self::BASE_URL . 'assets/dist/js/public.js', [ 'wp-i18n' ], Mockery::type( 'string' ), [ 'in_footer' => true ] );
		Functions\expect( 'wp_add_inline_script' )->once();
		Functions\when( 'wp_set_script_translations' )->justReturn( true );
		Functions\when( 'wp_script_is' )->justReturn( true );
		$assets = $this->assets();

		// Una página con tres shortcodes del plugin.
		$assets->enqueue_public();
		$assets->enqueue_public();
		$assets->enqueue_public();

		$this->assertSame( 'module', $assets->add_module_type( [ 'id' => 'ep-public-js' ] )['type'] );
	}

	/**
	 * Gestor de assets sobre el directorio temporal.
	 */
	private function assets(): Assets {
		$config = new Config(
			[
				'ui'    => [ 'timezone' => 'America/Bogota' ],
				'icons' => [ 'award' => [ 'Distinción', 'calidad' ] ],
				'media' => [ 'allowed_mimes' => [ 'application/pdf' ] ],
			]
		);
		$clock  = new class() implements Clock {
			public function now(): DateTimeImmutable {
				return new DateTimeImmutable( '2026-10-08 01:30:00', new DateTimeZone( 'UTC' ) );
			}
		};

		return new Assets(
			new PluginContext( $this->plugin_dir . 'eventos-probolsas.php', $this->plugin_dir, self::BASE_URL, '0.1.0' ),
			$config,
			new IconCatalog( $config ),
			DateFormatter::from_config( $config, $clock )
		);
	}
}
