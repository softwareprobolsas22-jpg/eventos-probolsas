<?php
/**
 * Reloj del sistema.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Time;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Devuelve la hora real del servidor, siempre en UTC.
 */
final class SystemClock implements Clock {

	/**
	 * Momento actual en UTC.
	 */
	public function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}
}
