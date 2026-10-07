<?php
/**
 * Pruebas de los códigos de error de la API.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Shared\Http;

use Probolsas\Eventos\Shared\Http\ErrorCode;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * Verifica que los códigos coincidan con el contrato publicado en docs/api/README.md.
 *
 * @covers \Probolsas\Eventos\Shared\Http\ErrorCode
 */
final class ErrorCodeTest extends UnitTestCase {

	public function test_codes_and_statuses_match_the_api_contract(): void {
		$contract = [];
		foreach ( ErrorCode::cases() as $code ) {
			$contract[ $code->value ] = $code->status();
		}

		$this->assertSame(
			[
				'eventos_validation_failed' => 422,
				'eventos_not_found'         => 404,
				'eventos_conflict'          => 409,
				'eventos_server_error'      => 500,
			],
			$contract
		);
	}

	public function test_every_code_is_documented(): void {
		$documentation = (string) file_get_contents( $this->plugin_dir() . 'docs/api/README.md' );

		foreach ( ErrorCode::cases() as $code ) {
			$this->assertStringContainsString( "`{$code->value}` | {$code->status()}", $documentation );
		}
	}
}
