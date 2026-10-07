<?php
/**
 * Pruebas del orden manual de una lista.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Shared\Ordering;

use Brain\Monkey\Functions;
use Probolsas\Eventos\Shared\Ordering\Reordering;
use Probolsas\Eventos\Shared\Validation\ValidationException;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * @covers \Probolsas\Eventos\Shared\Ordering\Reordering
 */
final class ReorderingTest extends UnitTestCase {

	private const MESSAGE = 'El orden debe incluir cada tipo de evento una sola vez.';

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
	}

	public function test_assigns_consecutive_positions_in_the_received_order(): void {
		$this->assertSame(
			[
				3 => 1,
				1 => 2,
				2 => 3,
			],
			Reordering::positions( [ 3, 1, 2 ], [ 1, 2, 3 ], self::MESSAGE )
		);
	}

	public function test_accepts_numeric_strings_as_received_from_json(): void {
		$this->assertSame(
			[
				2 => 1,
				1 => 2,
			],
			Reordering::positions( [ '2', '1' ], [ 1, 2 ], self::MESSAGE )
		);
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function invalid_lists(): array {
		return [
			'falta uno'       => [ [ 1, 2 ] ],
			'sobra uno'       => [ [ 1, 2, 3, 4 ] ],
			'repetido'        => [ [ 1, 1, 2 ] ],
			'ID inexistente'  => [ [ 1, 2, 9 ] ],
			'no numérico'     => [ [ 1, 2, 'tres' ] ],
			'vacío'           => [ [] ],
			'no es una lista' => [ 'no es lista' ],
			'nulo'            => [ null ],
		];
	}

	/**
	 * @dataProvider invalid_lists
	 *
	 * @param mixed $ids IDs recibidos.
	 */
	public function test_rejects_lists_that_do_not_match_the_existing_records( mixed $ids ): void {
		try {
			Reordering::positions( $ids, [ 1, 2, 3 ], self::MESSAGE );
			$this->fail( 'Se aceptó una lista no válida.' );
		} catch ( ValidationException $error ) {
			$this->assertSame( self::MESSAGE, $error->getMessage() );
			$this->assertSame( [ 'ids' => [ self::MESSAGE ] ], $error->errors() );
		}
	}
}
