<?php
/**
 * Errores del contenedor de dependencias.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core;

/**
 * Error de configuración del contenedor. Indica un fallo de programación, no un error del usuario.
 */
final class ContainerException extends \LogicException {

	/**
	 * Crea el error para un servicio que no está registrado.
	 *
	 * @param string $id Identificador solicitado.
	 */
	public static function not_found( string $id ): self {
		return new self( sprintf( 'Servicio no registrado en el contenedor: %s', $id ) );
	}

	/**
	 * Crea el error para un servicio cuyo tipo no coincide con el esperado.
	 *
	 * @param string $expected Clase o interfaz esperada.
	 * @param object $service  Servicio obtenido.
	 */
	public static function invalid_type( string $expected, object $service ): self {
		return new self( sprintf( 'Se esperaba un servicio de tipo %1$s y se obtuvo %2$s.', $expected, $service::class ) );
	}
}
