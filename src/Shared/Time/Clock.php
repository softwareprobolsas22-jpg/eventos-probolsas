<?php
/**
 * Contrato del reloj.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Time;

use DateTimeImmutable;

/**
 * Fuente de la hora actual. Se inyecta para que las pruebas puedan fijar el tiempo.
 */
interface Clock {

	/**
	 * Momento actual en UTC.
	 */
	public function now(): DateTimeImmutable;
}
