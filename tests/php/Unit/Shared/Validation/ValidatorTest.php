<?php
/**
 * Pruebas del validador (casos CP-1.13 y CP-1.14).
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Shared\Validation;

use Brain\Monkey\Functions;
use Probolsas\Eventos\Shared\Validation\ValidationException;
use Probolsas\Eventos\Shared\Validation\Validator;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * @covers \Probolsas\Eventos\Shared\Validation\Validator
 * @covers \Probolsas\Eventos\Shared\Validation\FieldRules
 * @covers \Probolsas\Eventos\Shared\Validation\ValidationException
 */
final class ValidatorTest extends UnitTestCase {

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
	}

	public function test_required_rejects_missing_empty_and_blank_values(): void {
		$validator = new Validator(
			[
				'empty' => '',
				'blank' => "  \t ",
			]
		);
		$validator->field( 'missing', 'Nombre' )->required();
		$validator->field( 'empty', 'Título' )->required();
		$validator->field( 'blank', 'Tipo' )->required();

		$this->assertSame(
			[
				'missing' => [ 'El campo «Nombre» es obligatorio.' ],
				'empty'   => [ 'El campo «Título» es obligatorio.' ],
				'blank'   => [ 'El campo «Tipo» es obligatorio.' ],
			],
			$validator->errors()
		);
	}

	public function test_reports_every_invalid_field_with_one_message_each(): void {
		$validator = new Validator(
			[
				'name'  => str_repeat( 'á', 151 ),
				'color' => 'verde',
				'url'   => 'ftp://intranet/doc.pdf',
			]
		);
		$validator->field( 'name', 'Nombre' )->required()->max_length( 150 );
		$validator->field( 'color', 'Color' )->required()->hex_color();
		$validator->field( 'url', 'URL' )->url()->max_length( 5 );

		$errors = $validator->errors();

		$this->assertSame( [ 'name', 'color', 'url' ], array_keys( $errors ) );
		$this->assertSame( [ 'El campo «Nombre» admite máximo 150 caracteres.' ], $errors['name'] );
		$this->assertCount( 1, $errors['url'], 'Debe detenerse en el primer error del campo.' );
	}

	public function test_max_length_counts_characters_not_bytes(): void {
		$validator = new Validator( [ 'name' => str_repeat( 'ñ', 150 ) ] );
		$validator->field( 'name', 'Nombre' )->max_length( 150 );

		$this->assertFalse( $validator->has_error( 'name' ) );
	}

	public function test_optional_empty_fields_skip_other_rules(): void {
		$validator = new Validator(
			[
				'url'   => '',
				'color' => null,
			]
		);
		$validator->field( 'url', 'URL' )->url();
		$validator->field( 'color', 'Color' )->hex_color();

		$this->assertSame( [], $validator->errors() );
	}

	public function test_valid_values_pass(): void {
		$validator = new Validator(
			[
				'color'  => '#669F30',
				'url'    => 'https://intranet.probolsas.co/doc.pdf',
				'type'   => '3',
				'suffix' => 'PR',
			]
		);
		$validator->field( 'color', 'Color' )->hex_color();
		$validator->field( 'url', 'URL' )->url();
		$validator->field( 'type', 'Tipo' )->required()->integer( 1 );
		$validator->field( 'suffix', 'Sufijo' )->one_of( [ 'PR', 'FO' ] );

		$validator->validate();
		$this->assertSame( [], $validator->errors() );
	}

	public function test_integer_and_one_of_reject_invalid_values(): void {
		$validator = new Validator(
			[
				'type'   => '0',
				'suffix' => 'XX',
			]
		);
		$validator->field( 'type', 'Tipo' )->integer( 1 );
		$validator->field( 'suffix', 'Sufijo' )->one_of( [ 'PR', 'FO' ] );

		$this->assertTrue( $validator->has_error( 'type' ) );
		$this->assertSame( [ 'Selecciona una opción válida en el campo «Sufijo».' ], $validator->errors()['suffix'] );
	}

	public function test_custom_rule(): void {
		$validator = new Validator( [ 'name' => 'Formatos' ] );
		$validator->field( 'name', 'Nombre' )->rule( static fn(): bool => false, 'Ya existe un tipo de evento llamado «Cumpleaños».' );

		$this->assertSame( [ 'name' => [ 'Ya existe un tipo de evento llamado «Cumpleaños».' ] ], $validator->errors() );
	}

	public function test_validate_throws_with_field_errors_and_general_message(): void {
		$validator = new Validator( [] );
		$validator->field( 'name', 'Nombre' )->required();

		try {
			$validator->validate();
			$this->fail( 'Se esperaba ValidationException.' );
		} catch ( ValidationException $error ) {
			$this->assertSame( 'Revisa los campos marcados.', $error->getMessage() );
			$this->assertSame( [ 'name' => [ 'El campo «Nombre» es obligatorio.' ] ], $error->errors() );
		}
	}

	public function test_min_length_counts_characters_and_ignores_edge_spaces(): void {
		$validator = new Validator(
			[
				'short'   => '  ab  ',
				'accents' => 'Día',
			]
		);
		$validator->field( 'short', 'Título' )->min_length( 3 );
		$validator->field( 'accents', 'Título' )->min_length( 3 );

		$this->assertSame( [ 'short' => [ 'El campo «Título» debe tener al menos 3 caracteres.' ] ], $validator->errors() );
	}

	public function test_calendar_date_accepts_only_real_dates(): void {
		$validator = new Validator(
			[
				'valid'     => '2024-02-29',
				'overflow'  => '2026-02-30',
				'colombian' => '07/10/2026',
				'with_time' => '2026-10-07 15:00:00',
			]
		);
		foreach ( [ 'valid', 'overflow', 'colombian', 'with_time' ] as $field ) {
			$validator->field( $field, 'Fecha' )->calendar_date();
		}

		$this->assertSame( [ 'overflow', 'colombian', 'with_time' ], array_keys( $validator->errors() ) );
		$this->assertSame( [ 'El campo «Fecha» debe ser una fecha válida.' ], $validator->errors()['overflow'] );
	}

	public function test_calendar_time_accepts_hours_with_or_without_seconds(): void {
		$validator = new Validator(
			[
				'short'    => '15:00',
				'long'     => '15:00:00',
				'single'   => '7:00',
				'overflow' => '24:00',
				'twelve'   => '3:00 p. m.',
			]
		);
		foreach ( [ 'short', 'long', 'single', 'overflow', 'twelve' ] as $field ) {
			$validator->field( $field, 'Hora' )->calendar_time();
		}

		$this->assertSame( [ 'single', 'overflow', 'twelve' ], array_keys( $validator->errors() ) );
		$this->assertSame( [ 'El campo «Hora» debe ser una hora válida.' ], $validator->errors()['single'] );
	}
}
