<?php
/**
 * Falla de persistencia.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Persistence;

use RuntimeException;

/**
 * La base de datos rechazó una operación. El mensaje es apto para el usuario; el detalle técnico
 * (último error de MySQL) se guarda aparte para el log.
 */
class PersistenceException extends RuntimeException {

	/**
	 * Crea la excepción.
	 *
	 * @param string $message      Mensaje para el usuario.
	 * @param string $technical    Detalle técnico (no se muestra al usuario).
	 */
	public function __construct( string $message, private readonly string $technical = '' ) {
		parent::__construct( $message );
	}

	/**
	 * Detalle técnico para el log.
	 */
	public function technical_detail(): string {
		return $this->technical;
	}
}
