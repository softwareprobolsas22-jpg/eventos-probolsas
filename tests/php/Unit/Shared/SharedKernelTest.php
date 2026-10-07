<?php
/**
 * Pruebas de las piezas pequeñas del shared kernel: reloj, excepciones y proveedor de servicios.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Shared;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Probolsas\Eventos\Core\Config;
use Probolsas\Eventos\Core\Container;
use Probolsas\Eventos\Shared\Persistence\DuplicateEntryException;
use Probolsas\Eventos\Shared\Persistence\PersistenceException;
use Probolsas\Eventos\Shared\SharedServiceProvider;
use Probolsas\Eventos\Shared\Text\Slugger;
use Probolsas\Eventos\Shared\Text\TextNormalizer;
use Probolsas\Eventos\Shared\Time\Clock;
use Probolsas\Eventos\Shared\Time\DateFormatter;
use Probolsas\Eventos\Shared\Time\SystemClock;
use Probolsas\Eventos\Shared\Ui\IconCatalog;
use Probolsas\Eventos\Shared\Validation\ValidationException;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * @covers \Probolsas\Eventos\Shared\Time\SystemClock
 * @covers \Probolsas\Eventos\Shared\Persistence\PersistenceException
 * @covers \Probolsas\Eventos\Shared\Validation\ValidationException
 * @covers \Probolsas\Eventos\Shared\SharedServiceProvider
 */
final class SharedKernelTest extends UnitTestCase {

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
	}

	public function test_system_clock_returns_the_current_moment_in_utc(): void {
		$before = time();
		$now    = ( new SystemClock() )->now();

		$this->assertSame( 'UTC', $now->getTimezone()->getName() );
		$this->assertGreaterThanOrEqual( $before, $now->getTimestamp() );
		$this->assertLessThanOrEqual( time(), $now->getTimestamp() );
	}

	public function test_persistence_errors_keep_the_technical_detail_apart_from_the_user_message(): void {
		$error = new PersistenceException( 'No se pudo guardar la información.', "Table 'wp_eventos_events' doesn't exist" );

		$this->assertSame( 'No se pudo guardar la información.', $error->getMessage() );
		$this->assertSame( "Table 'wp_eventos_events' doesn't exist", $error->technical_detail() );
		$this->assertSame( '', ( new PersistenceException( 'Mensaje' ) )->technical_detail() );
		$this->assertInstanceOf( PersistenceException::class, new DuplicateEntryException( 'Repetido', 'Duplicate entry' ) );
	}

	public function test_validation_exception_has_a_general_message_for_the_toast(): void {
		$error = new ValidationException( [ 'title' => [ 'El campo «Título» es obligatorio.' ] ] );

		$this->assertSame( 'Revisa los campos marcados.', $error->getMessage() );
		$this->assertSame( [ 'title' => [ 'El campo «Título» es obligatorio.' ] ], $error->errors() );
		$this->assertSame( 'Otro mensaje', ( new ValidationException( [], 'Otro mensaje' ) )->getMessage() );
	}

	public function test_provider_registers_the_shared_services(): void {
		$container = new Container();
		$container->set( Config::class, static fn(): Config => new Config( [ 'ui' => [ 'timezone' => 'America/Bogota' ] ] ) );

		( new SharedServiceProvider() )->register( $container );

		$this->assertInstanceOf( SystemClock::class, $container->get( Clock::class ) );
		$this->assertInstanceOf( DateFormatter::class, $container->get( DateFormatter::class ) );
		$this->assertInstanceOf( TextNormalizer::class, $container->get( TextNormalizer::class ) );
		$this->assertSame( 'reuniones-laborales', $container->get( Slugger::class )->slugify( 'Reuniones Laborales' ) );
		$this->assertSame( [], $container->get( IconCatalog::class )->keys() );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $container->get( DateFormatter::class )->today() );
		$this->assertInstanceOf( DateTimeImmutable::class, $container->get( Clock::class )->now() );
	}
}
