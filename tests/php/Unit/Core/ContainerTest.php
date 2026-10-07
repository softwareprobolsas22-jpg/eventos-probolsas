<?php
/**
 * Pruebas del contenedor de dependencias.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Core;

use ArrayObject;
use Countable;
use Probolsas\Eventos\Core\Container;
use Probolsas\Eventos\Core\ContainerException;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;
use SplObjectStorage;
use stdClass;

/**
 * @covers \Probolsas\Eventos\Core\Container
 */
final class ContainerTest extends UnitTestCase {

	public function test_get_builds_the_service_only_once(): void {
		$container = new Container();
		$builds    = 0;

		$container->set(
			stdClass::class,
			static function () use ( &$builds ): stdClass {
				++$builds;
				return new stdClass();
			}
		);

		$first  = $container->get( stdClass::class );
		$second = $container->get( stdClass::class );

		$this->assertSame( $first, $second );
		$this->assertSame( 1, $builds );
	}

	public function test_factory_receives_the_container(): void {
		$container = new Container();
		$container->set( stdClass::class, static fn(): stdClass => new stdClass() );
		$container->set(
			ArrayObject::class,
			static fn( Container $c ): ArrayObject => new ArrayObject( [ $c->get( stdClass::class ) ] )
		);

		$this->assertSame( $container->get( stdClass::class ), $container->get( ArrayObject::class )[0] );
	}

	public function test_has_reports_registered_services(): void {
		$container = new Container();
		$container->set( stdClass::class, static fn(): stdClass => new stdClass() );

		$this->assertTrue( $container->has( stdClass::class ) );
		$this->assertFalse( $container->has( ArrayObject::class ) );
	}

	public function test_get_fails_for_unregistered_service(): void {
		$this->expectException( ContainerException::class );
		$this->expectExceptionMessage( 'Servicio no registrado' );

		( new Container() )->get( stdClass::class );
	}

	public function test_get_fails_when_factory_returns_another_type(): void {
		$container = new Container();
		$container->set( ArrayObject::class, static fn(): stdClass => new stdClass() );

		$this->expectException( ContainerException::class );

		$container->get( ArrayObject::class );
	}

	public function test_tagged_returns_services_in_registration_order(): void {
		$container = new Container();
		$container->set( ArrayObject::class, static fn(): ArrayObject => new ArrayObject() );
		$container->set( SplObjectStorage::class, static fn(): SplObjectStorage => new SplObjectStorage() );
		$container->tag( 'countables', SplObjectStorage::class );
		$container->tag( 'countables', ArrayObject::class );

		$services = $container->tagged( 'countables', Countable::class );

		$this->assertInstanceOf( SplObjectStorage::class, $services[0] );
		$this->assertInstanceOf( ArrayObject::class, $services[1] );
	}

	public function test_tagged_returns_empty_list_for_unknown_tag(): void {
		$this->assertSame( [], ( new Container() )->tagged( 'desconocida', Countable::class ) );
	}

	public function test_tagged_fails_when_a_service_does_not_match_the_type(): void {
		$container = new Container();
		$container->set( stdClass::class, static fn(): stdClass => new stdClass() );
		$container->tag( 'countables', stdClass::class );

		$this->expectException( ContainerException::class );

		$container->tagged( 'countables', Countable::class );
	}
}
