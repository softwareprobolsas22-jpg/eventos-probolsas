<?php
/**
 * Pruebas de la caché de respuestas.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Shared\Cache;

use Brain\Monkey\Functions;
use Probolsas\Eventos\Domains\Event\Domain\Event;
use Probolsas\Eventos\Domains\Event\Domain\EventSchedule;
use Probolsas\Eventos\Domains\Event\Infrastructure\CacheFlushingEventRepository;
use Probolsas\Eventos\Domains\EventType\Domain\EventType;
use Probolsas\Eventos\Domains\EventType\Infrastructure\CacheFlushingEventTypeRepository;
use Probolsas\Eventos\Shared\Cache\ResponseCache;
use Probolsas\Eventos\Shared\Validation\ValidationException;
use Probolsas\Eventos\Tests\Unit\Support\InMemoryEventRepository;
use Probolsas\Eventos\Tests\Unit\Support\InMemoryEventTypeRepository;
use Probolsas\Eventos\Tests\Unit\Support\TransientStore;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * Caché por versión (H-401) y repositorios que la invalidan al escribir.
 *
 * @covers \Probolsas\Eventos\Shared\Cache\ResponseCache
 * @covers \Probolsas\Eventos\Domains\Event\Infrastructure\CacheFlushingEventRepository
 * @covers \Probolsas\Eventos\Domains\EventType\Infrastructure\CacheFlushingEventTypeRepository
 */
final class ResponseCacheTest extends UnitTestCase {

	/**
	 * Transients en memoria.
	 *
	 * @var TransientStore
	 */
	private TransientStore $store;

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
		$this->store = TransientStore::install();
	}

	public function test_remember_builds_once_per_key_and_version(): void {
		$cache  = new ResponseCache();
		$builds = 0;
		$build  = static function () use ( &$builds ): array {
			++$builds;
			return [ 'eventos' => $builds ];
		};

		$this->assertSame( [ 'eventos' => 1 ], $cache->remember( 'calendar', [ 'start' => '2026-10-01' ], $build ) );
		$this->assertSame( [ 'eventos' => 1 ], $cache->remember( 'calendar', [ 'start' => '2026-10-01' ], $build ) );
		$this->assertSame( [ 'eventos' => 2 ], $cache->remember( 'calendar', [ 'start' => '2026-11-01' ], $build ), 'Otro rango, otra clave.' );
		$this->assertSame( [ 'eventos' => 3 ], $cache->remember( 'upcoming', [ 'start' => '2026-10-01' ], $build ), 'Otra consulta, otra clave.' );

		$cache->flush();
		$this->assertSame( [ 'eventos' => 4 ], $cache->remember( 'calendar', [ 'start' => '2026-10-01' ], $build ), 'Después de invalidar se arma de nuevo.' );
		$this->assertSame( 2, $this->store->options[ ResponseCache::VERSION_OPTION ] );
	}

	public function test_an_empty_result_is_cached_too(): void {
		$cache  = new ResponseCache();
		$builds = 0;
		$build  = static function () use ( &$builds ): array {
			++$builds;
			return [];
		};

		$cache->remember( 'calendar', [], $build );
		$cache->remember( 'calendar', [], $build );

		$this->assertSame( 1, $builds );
	}

	public function test_errors_are_not_cached(): void {
		$cache = new ResponseCache();

		try {
			$cache->remember( 'calendar', [], static fn(): array => throw new ValidationException( [ 'start' => [ 'x' ] ] ) );
		} catch ( ValidationException ) {
			$this->assertSame( 0, $this->store->writes );
			return;
		}

		$this->fail( 'Se esperaba la excepción.' );
	}

	public function test_uninstall_removes_the_version_option(): void {
		$cache = new ResponseCache();
		$cache->flush();

		$cache->run();

		$this->assertArrayNotHasKey( ResponseCache::VERSION_OPTION, $this->store->options );
	}

	public function test_event_writes_invalidate_and_reads_do_not(): void {
		$cache      = new ResponseCache();
		$repository = new CacheFlushingEventRepository( new InMemoryEventRepository(), $cache );
		$event      = new Event( null, 1, 'Reunión', '', new EventSchedule( '2026-10-07' ), null, 1, 1, '', '' );

		$saved = $repository->insert( $event );
		$repository->find( (int) $saved->id );
		$repository->on_date( '2026-10-07' );
		$repository->in_range( '2026-10-01', '2026-10-31' );
		$repository->count_in_range( '2026-10-01', '2026-10-31' );
		$this->assertSame( 2, $this->version(), 'Solo la escritura cambia la versión.' );

		$repository->update( $saved );
		$repository->delete( (int) $saved->id );
		$this->assertSame( 4, $this->version() );
		$this->assertNull( $repository->find( (int) $saved->id ) );
	}

	public function test_type_writes_invalidate_and_reads_do_not(): void {
		$cache      = new ResponseCache();
		$repository = new CacheFlushingEventTypeRepository( new InMemoryEventTypeRepository(), $cache );
		$type       = new EventType( null, 'Pausas', 'pausas', 'pausas', '#FDE68A', 'mug-hot', false, '', 1, '', '' );

		$saved = $repository->insert( $type );
		$repository->find( (int) $saved->id );
		$repository->all_with_event_counts();
		$repository->is_empty();
		$repository->name_key_exists( 'pausas' );
		$repository->slug_exists( 'pausas' );
		$repository->count_events( (int) $saved->id );
		$repository->next_sort_order();
		$this->assertSame( 2, $this->version() );

		$repository->update( $saved );
		$repository->reorder( [ (int) $saved->id => 1 ] );
		$repository->delete( (int) $saved->id );
		$this->assertSame( 5, $this->version() );
	}

	/**
	 * Versión guardada.
	 */
	private function version(): int {
		return (int) ( $this->store->options[ ResponseCache::VERSION_OPTION ] ?? 1 );
	}
}
