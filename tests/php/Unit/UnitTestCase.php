<?php
/**
 * Clase base de las pruebas unitarias.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit;

use Brain\Monkey;
use Mockery;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Prepara Brain Monkey para simular las funciones de WordPress y cuenta las expectativas de Mockery
 * como aserciones.
 */
abstract class UnitTestCase extends TestCase {

	/**
	 * Inicia Brain Monkey.
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();
	}

	/**
	 * Verifica las expectativas y limpia Brain Monkey.
	 */
	protected function tear_down(): void {
		$this->addToAssertionCount( Mockery::getContainer()->mockery_getExpectationCount() );
		Monkey\tearDown();
		parent::tear_down();
	}

	/**
	 * Ruta absoluta de la raíz del plugin.
	 */
	protected function plugin_dir(): string {
		return dirname( __DIR__, 3 ) . '/';
	}
}
