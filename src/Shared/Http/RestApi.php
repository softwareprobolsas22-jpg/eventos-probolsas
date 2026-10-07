<?php
/**
 * Datos comunes de la API REST.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Http;

/**
 * Fuente única de verdad de la API REST del plugin. Las convenciones están en docs/api/README.md.
 */
final class RestApi {

	/**
	 * Namespace de la versión 1 de la API.
	 */
	public const NAMESPACE_V1 = 'eventos/v1';

	/**
	 * Acción del nonce que WordPress valida en las peticiones REST autenticadas por cookie.
	 */
	public const NONCE_ACTION = 'wp_rest';
}
