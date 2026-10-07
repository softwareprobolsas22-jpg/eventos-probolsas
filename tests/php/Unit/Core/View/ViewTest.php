<?php
/**
 * Pruebas del renderizador de plantillas.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Core\View;

use Brain\Monkey\Functions;
use InvalidArgumentException;
use Probolsas\Eventos\Core\View\View;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;
use RuntimeException;

/**
 * @covers \Probolsas\Eventos\Core\View\View
 */
final class ViewTest extends UnitTestCase {

	protected function set_up(): void {
		parent::set_up();
		Functions\stubEscapeFunctions();
	}

	public function test_fetch_passes_data_to_the_template(): void {
		$this->assertSame( "<p>Hola, Ana &amp; Luis</p>\n", $this->view()->fetch( 'greeting', [ 'name' => 'Ana & Luis' ] ) );
	}

	public function test_render_prints_the_template(): void {
		$this->expectOutputString( "<p>Hola, Ana</p>\n" );

		$this->view()->render( 'greeting', [ 'name' => 'Ana' ] );
	}

	public function test_fetch_discards_partial_output_when_template_fails(): void {
		$level = ob_get_level();

		try {
			$this->view()->fetch( 'failing' );
			$this->fail( 'Se esperaba una excepción.' );
		} catch ( RuntimeException $error ) {
			$this->assertSame( 'Fallo en la plantilla', $error->getMessage() );
		}

		$this->assertSame( $level, ob_get_level() );
	}

	/**
	 * @dataProvider invalid_names
	 *
	 * @param string $template Nombre no válido.
	 */
	public function test_rejects_invalid_or_missing_templates( string $template ): void {
		$this->expectException( InvalidArgumentException::class );

		$this->view()->fetch( $template );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalid_names(): array {
		return [
			'recorrido de directorios' => [ '../greeting' ],
			'ruta absoluta'            => [ '/etc/passwd' ],
			'con extensión'            => [ 'greeting.php' ],
			'inexistente'              => [ 'no-existe' ],
		];
	}

	/**
	 * Renderizador sobre las plantillas de prueba.
	 */
	private function view(): View {
		return new View( dirname( __DIR__, 3 ) . '/fixtures/templates' );
	}
}
