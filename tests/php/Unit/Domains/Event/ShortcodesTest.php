<?php
/**
 * Pruebas de los shortcodes de la intranet.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Domains\Event;

use Brain\Monkey\Functions;
use Probolsas\Eventos\Core\Assets\Assets;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Core\Container;
use Probolsas\Eventos\Core\Frontend\Shortcode;
use Probolsas\Eventos\Core\Frontend\ShortcodeAttribute;
use Probolsas\Eventos\Core\Frontend\ShortcodeRegistry;
use Probolsas\Eventos\Core\Frontend\WidgetRenderer;
use Probolsas\Eventos\Core\PluginContext;
use Probolsas\Eventos\Core\View\View;
use Probolsas\Eventos\Domains\Event\EventServiceProvider;
use Probolsas\Eventos\Domains\Event\Presentation\CalendarShortcode;
use Probolsas\Eventos\Domains\Event\Presentation\ShortcodeTypes;
use Probolsas\Eventos\Domains\Event\Presentation\UpcomingShortcode;
use Probolsas\Eventos\Domains\EventType\Domain\EventType;
use Probolsas\Eventos\Domains\EventType\Domain\EventTypeRepository;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Time\SystemClock;
use Probolsas\Eventos\Shared\Ui\IconCatalog;
use Probolsas\Eventos\Tests\Unit\Support\InMemoryEventTypeRepository;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * `[eventos_calendario]` y `[eventos_proximos]`: el contenedor del widget con sus opciones, el aviso para
 * los visitantes sin sesión (D-1) y la guía de uso.
 *
 * @covers \Probolsas\Eventos\Domains\Event\Presentation\CalendarShortcode
 * @covers \Probolsas\Eventos\Domains\Event\Presentation\UpcomingShortcode
 * @covers \Probolsas\Eventos\Domains\Event\Presentation\ShortcodeTypes
 * @covers \Probolsas\Eventos\Core\Frontend\WidgetRenderer
 */
final class ShortcodesTest extends UnitTestCase {

	/**
	 * Tipos en memoria.
	 *
	 * @var InMemoryEventTypeRepository
	 */
	private InMemoryEventTypeRepository $types;

	/**
	 * Si hay sesión iniciada.
	 *
	 * @var bool
	 */
	private bool $logged_in = true;

	/**
	 * Si el usuario tiene `eventos_view`.
	 *
	 * @var bool
	 */
	private bool $can_view = true;

	/**
	 * Veces que se encoló la entrada pública.
	 *
	 * @var int
	 */
	private int $enqueued = 0;

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $value ): string => trim( strip_tags( $value ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Simula sanitize_text_field.
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_user_logged_in' )->alias( fn(): bool => $this->logged_in );
		Functions\when( 'current_user_can' )->alias( fn(): bool => $this->can_view );
		Functions\when( 'get_permalink' )->justReturn( 'https://intranet.test/eventos/' );
		Functions\when( 'wp_login_url' )->alias( static fn( string $redirect = '' ): string => 'https://intranet.test/wp-login.php?redirect_to=' . rawurlencode( $redirect ) );
		Functions\when( 'wp_script_is' )->justReturn( false );
		Functions\when( 'wp_enqueue_style' )->justReturn( null );
		Functions\when( 'wp_set_script_translations' )->justReturn( true );
		Functions\when( 'wp_add_inline_script' )->justReturn( true );
		Functions\when( 'rest_url' )->justReturn( 'https://intranet.test/wp-json/eventos/v1/' );
		Functions\when( 'wp_create_nonce' )->justReturn( 'nonce' );
		Functions\when( 'get_option' )->justReturn( 1 );
		Functions\when( 'wp_enqueue_script' )->alias(
			function (): void {
				++$this->enqueued;
			}
		);

		$this->types = new InMemoryEventTypeRepository();
		$this->types->insert( new EventType( null, 'Cumpleaños', 'cumpleaños', 'cumpleanos', '#9D174D', 'cake-candles', true, '', 1, '2026-10-01 00:00:00', '2026-10-01 00:00:00' ) );
		$this->types->insert( new EventType( null, 'Capacitaciones', 'capacitaciones', 'capacitaciones', '#155728', 'graduation-cap', false, '', 2, '2026-10-01 00:00:00', '2026-10-01 00:00:00' ) );
	}

	public function test_the_calendar_mounts_its_widget_with_the_chosen_types(): void {
		$html = $this->calendar()->render( [ 'tipos' => ' Capacitaciones , cumpleanos, eliminado' ] );

		$this->assertSame( [ 'calendar', [ 'types' => [ 1, 2 ] ] ], $this->widget( $html ), 'Los slugs desconocidos se ignoran; los IDs van en el orden de los tipos.' );
		$this->assertSame( 1, $this->enqueued, 'Los assets públicos se encolan con el widget.' );
		$this->assertStringNotContainsString( 'Cumpleaños', $html, 'El HTML no lleva datos de eventos ni de tipos (R-22).' );
	}

	public function test_without_types_the_calendar_shows_all(): void {
		$this->assertSame( [ 'calendar', [ 'types' => [] ] ], $this->widget( $this->calendar()->render( [] ) ) );
		$this->assertSame( [ 'calendar', [ 'types' => [] ] ], $this->widget( $this->calendar()->render( [ 'tipos' => ' , ' ] ) ) );
	}

	public function test_upcoming_mounts_its_widget_with_limit_types_and_title(): void {
		$html = $this->upcoming()->render(
			[
				'limite' => '3',
				'tipos'  => 'cumpleanos',
				'titulo' => '<b>Lo que viene</b>',
			]
		);

		$this->assertSame(
			[
				'upcoming',
				[
					'limit' => 3,
					'types' => [ 1 ],
					'title' => 'Lo que viene',
				],
			],
			$this->widget( $html )
		);
	}

	/**
	 * Límites escritos en el shortcode y el que recibe el widget.
	 *
	 * @return array<string, array{0: array<string, string>, 1: int}>
	 */
	public static function limits(): array {
		return [
			'por defecto' => [ [], 5 ],
			'texto'       => [ [ 'limite' => 'diez' ], 5 ],
			'cero'        => [ [ 'limite' => '0' ], 1 ],
			'negativo'    => [ [ 'limite' => '-4' ], 1 ],
			'máximo'      => [ [ 'limite' => '20' ], 20 ],
			'excedido'    => [ [ 'limite' => '100' ], 20 ],
		];
	}

	/**
	 * El límite se ajusta a 1–20.
	 *
	 * @dataProvider limits
	 *
	 * @param array<string, string> $atts  Atributos.
	 * @param int                   $limit Límite esperado.
	 */
	public function test_the_upcoming_limit_is_clamped( array $atts, int $limit ): void {
		$props = $this->widget( $this->upcoming()->render( $atts ) )[1];

		$this->assertSame( $limit, $props['limit'] );
		$this->assertSame( 'Próximos eventos', $props['title'] );
	}

	public function test_an_empty_title_hides_it(): void {
		$this->assertSame( '', $this->widget( $this->upcoming()->render( [ 'titulo' => '' ] ) )[1]['title'] );
	}

	public function test_a_visitor_without_session_sees_the_login_notice(): void {
		$this->logged_in = false;
		$this->can_view  = false;

		$html = $this->calendar()->render( [] );

		$this->assertStringContainsString( 'Inicia sesión para ver el calendario de eventos.', $html );
		$this->assertStringContainsString( 'https://intranet.test/wp-login.php?redirect_to=https%3A%2F%2Fintranet.test%2Feventos%2F', $html, 'Vuelve a la página después de iniciar sesión.' );
		$this->assertStringNotContainsString( 'data-ep-widget', $html );
		$this->assertSame( 0, $this->enqueued, 'Sin sesión no se carga el JS del calendario.' );
	}

	public function test_a_user_without_permission_sees_a_notice(): void {
		$this->can_view = false;

		$html = $this->upcoming()->render( [] );

		$this->assertStringContainsString( 'No tienes permiso para ver el calendario de eventos.', $html );
		$this->assertStringNotContainsString( 'data-ep-widget', $html );
	}

	public function test_the_guides_describe_the_attributes_that_render_reads(): void {
		$calendar = $this->calendar()->guide()->to_array( CalendarShortcode::TAG );
		$upcoming = $this->upcoming()->guide()->to_array( UpcomingShortcode::TAG );

		$this->assertSame( '[eventos_calendario]', $calendar['example'] );
		$this->assertSame( [ 'tipos' ], array_column( $calendar['attributes'], 'name' ) );
		$this->assertSame( ShortcodeAttribute::EVENT_TYPES, $calendar['attributes'][0]['values'] );
		$this->assertSame( [ 'limite', 'tipos', 'titulo' ], array_column( $upcoming['attributes'], 'name' ) );
		$this->assertSame( 'Cuántos eventos mostrar, de 1 a 20.', $upcoming['attributes'][0]['description'] );
	}

	public function test_the_provider_contributes_both_shortcodes_to_the_registry(): void {
		$container = new Container();
		$container->set( EventTypeRepository::class, fn(): EventTypeRepository => $this->types );
		$container->set( WidgetRenderer::class, fn(): WidgetRenderer => $this->renderer() );
		( new EventServiceProvider() )->register( $container );

		$tags = array_map( static fn( Shortcode $shortcode ): string => $shortcode->tag(), $container->tagged( ShortcodeRegistry::SHORTCODES_TAG, Shortcode::class ) );

		$this->assertSame( [ 'eventos_calendario', 'eventos_proximos' ], $tags );
	}

	/**
	 * Nombre y opciones del widget impreso.
	 *
	 * @param string $html HTML del shortcode.
	 *
	 * @return array{0: string, 1: array<string, mixed>}
	 */
	private function widget( string $html ): array {
		$this->assertSame( 1, preg_match( '/data-ep-widget="([^"]+)" data-ep-props="([^"]+)"/', $html, $matches ), $html );

		return [ $matches[1], json_decode( html_entity_decode( $matches[2] ), true ) ];
	}

	private function calendar(): CalendarShortcode {
		return new CalendarShortcode( $this->renderer(), new ShortcodeTypes( $this->types ) );
	}

	private function upcoming(): UpcomingShortcode {
		return new UpcomingShortcode( $this->renderer(), new ShortcodeTypes( $this->types ) );
	}

	/**
	 * Renderizador real con las plantillas del plugin.
	 */
	private function renderer(): WidgetRenderer {
		$config = new Config( [] );
		$assets = new Assets( new PluginContext( '/tmp/eventos-probolsas.php', '/tmp/', 'https://intranet.test/', '0.1.0' ), $config, new IconCatalog( $config ), DateFormatter::from_config( $config, new SystemClock() ) );

		return new WidgetRenderer( new View( $this->plugin_dir() . 'templates' ), $assets );
	}
}
