<?php
/**
 * Pruebas del registro de shortcodes.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Core\Frontend;

use Brain\Monkey\Functions;
use Probolsas\Eventos\Core\Assets\Assets;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Core\Frontend\Shortcode;
use Probolsas\Eventos\Core\Frontend\ShortcodeAttribute;
use Probolsas\Eventos\Core\Frontend\ShortcodeGuide;
use Probolsas\Eventos\Core\Frontend\ShortcodeRegistry;
use Probolsas\Eventos\Core\PluginContext;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Time\SystemClock;
use Probolsas\Eventos\Shared\Ui\IconCatalog;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * @covers \Probolsas\Eventos\Core\Frontend\ShortcodeRegistry
 */
final class ShortcodeRegistryTest extends UnitTestCase {

	/**
	 * Shortcode de prueba que devuelve sus atributos como JSON.
	 *
	 * @param string $tag Nombre.
	 */
	private function shortcode( string $tag ): Shortcode {
		return new class( $tag ) implements Shortcode {
			public function __construct( private readonly string $name ) {}

			public function tag(): string {
				return $this->name;
			}

			public function render( array $atts ): string {
				return $this->name . ':' . (string) json_encode( $atts );
			}

			public function guide(): ShortcodeGuide {
				return new ShortcodeGuide( 'Guía de ' . $this->name, 'Descripción', "[{$this->name}]", [ new ShortcodeAttribute( 'titulo', 'Título', false, '', null ) ] );
			}
		};
	}

	private function registry(): ShortcodeRegistry {
		$config = new Config( [] );

		return new ShortcodeRegistry(
			[ $this->shortcode( 'eventos_calendario' ), $this->shortcode( 'eventos_proximos' ) ],
			new Assets( new PluginContext( '/tmp/eventos-probolsas.php', '/tmp/', 'https://intranet.test/', '0.1.0' ), $config, new IconCatalog( $config ), DateFormatter::from_config( $config, new SystemClock() ) )
		);
	}

	public function test_registers_every_shortcode(): void {
		$registered = [];
		Functions\when( 'add_shortcode' )->alias(
			static function ( string $tag ) use ( &$registered ): void {
				$registered[] = $tag;
			}
		);

		$this->registry()->add_shortcodes();

		$this->assertSame( [ 'eventos_calendario', 'eventos_proximos' ], $registered );
	}

	public function test_dispatch_passes_attributes_or_an_empty_array(): void {
		$registry = $this->registry();

		$this->assertSame( 'eventos_proximos:{"titulo":"Buscar"}', $registry->dispatch( [ 'titulo' => 'Buscar' ], '', 'eventos_proximos' ) );
		// Sin atributos, WordPress entrega un texto vacío.
		$this->assertSame( 'eventos_calendario:[]', $registry->dispatch( '', '', 'eventos_calendario' ) );
		$this->assertSame( '', $registry->dispatch( [], '', 'sgc_dashboard' ) );
	}

	public function test_detects_pages_that_use_the_shortcodes(): void {
		Functions\when( 'has_shortcode' )->alias( static fn( string $content, string $tag ): bool => str_contains( $content, "[{$tag}" ) );
		$registry = $this->registry();

		$this->assertTrue( $registry->uses_shortcodes( 'Intro [eventos_proximos titulo="Buscar"]' ) );
		$this->assertFalse( $registry->uses_shortcodes( 'Plugin anterior: [sgc_dashboard]' ) );
		$this->assertSame( [ 'eventos_calendario', 'eventos_proximos' ], $registry->tags() );
	}

	public function test_guides_describe_each_shortcode_for_the_browser(): void {
		$guides = $this->registry()->guides();

		$this->assertSame( [ 'eventos_calendario', 'eventos_proximos' ], array_column( $guides, 'tag' ) );
		$this->assertSame(
			[
				'tag'         => 'eventos_proximos',
				'title'       => 'Guía de eventos_proximos',
				'description' => 'Descripción',
				'example'     => '[eventos_proximos]',
				'attributes'  => [
					[
						'name'        => 'titulo',
						'description' => 'Título',
						'required'    => false,
						'default'     => '',
						'values'      => null,
					],
				],
			],
			$guides[1]
		);
	}
}
