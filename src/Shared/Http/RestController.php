<?php
/**
 * Base de los controladores REST.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Http;

use Probolsas\Eventos\Core\Security\Capabilities;
use Probolsas\Eventos\Shared\Errors\ConflictException;
use Probolsas\Eventos\Shared\Errors\NotFoundException;
use Probolsas\Eventos\Shared\Persistence\PersistenceException;
use Probolsas\Eventos\Shared\Validation\ValidationException;
use WP_Error;
use WP_REST_Response;

/**
 * Comportamiento común de la API de cada dominio: rutas bajo `eventos/v1`, permisos, formato de respuesta
 * `{ "data": … }` y conversión de las excepciones de dominio en errores HTTP (ver docs/api/README.md).
 *
 * La autenticación por cookie y el nonce `X-WP-Nonce` los valida WordPress antes de llegar aquí: sin nonce
 * válido el usuario se considera sin sesión y los permisos fallan con 401.
 */
abstract class RestController {

	/**
	 * Registra las rutas cuando WordPress inicializa la API.
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Declara las rutas del recurso con add_route().
	 */
	abstract public function register_routes(): void;

	/**
	 * Segmento base del recurso, en inglés y en plural (por ejemplo `event-types`).
	 */
	abstract protected function rest_base(): string;

	/**
	 * Permiso de lectura: cualquier usuario con sesión.
	 */
	public function can_view(): bool {
		return current_user_can( Capabilities::VIEW );
	}

	/**
	 * Permiso de escritura: gestores de eventos.
	 */
	public function can_manage(): bool {
		return current_user_can( Capabilities::MANAGE );
	}

	/**
	 * Registra una ruta del recurso.
	 *
	 * @param string              $path      Ruta relativa al recurso, por ejemplo `/(?P<id>\d+)`. Vacío para la raíz.
	 * @param array<mixed, mixed> $endpoints Definición de endpoints de register_rest_route().
	 */
	protected function add_route( string $path, array $endpoints ): void {
		register_rest_route( RestApi::NAMESPACE_V1, '/' . $this->rest_base() . $path, $endpoints );
	}

	/**
	 * Respuesta exitosa con el formato `{ "data": … }`, sin caché.
	 *
	 * Los datos cambian con cada guardado en las tablas propias, que no purgan ninguna caché: si LiteSpeed
	 * (opción «Caché de la API REST») o un proxy guardan un listado, se seguiría mostrando el anterior.
	 *
	 * @param mixed $data   Datos de la respuesta.
	 * @param int   $status Estado HTTP.
	 */
	protected function ok( mixed $data, int $status = 200 ): WP_REST_Response {
		do_action( 'litespeed_control_set_nocache', 'Eventos: la API REST siempre responde datos actuales' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- API del plugin LiteSpeed Cache.

		$response = new WP_REST_Response( [ 'data' => $data ], $status );
		$response->header( 'Cache-Control', 'no-store, private' );
		$response->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );

		return $response;
	}

	/**
	 * Ejecuta la acción del endpoint y convierte las excepciones de dominio en errores HTTP.
	 *
	 * @param callable(): WP_REST_Response $action Acción del endpoint.
	 */
	protected function handle( callable $action ): WP_REST_Response|WP_Error {
		try {
			return $action();
		} catch ( ValidationException $error ) {
			return $this->error( ErrorCode::ValidationFailed, $error->getMessage(), $error->errors() );
		} catch ( NotFoundException $error ) {
			return $this->error( ErrorCode::NotFound, $error->getMessage() );
		} catch ( ConflictException $error ) {
			return $this->error( ErrorCode::Conflict, $error->getMessage() );
		} catch ( PersistenceException $error ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Detalle técnico para diagnóstico; no se envía al usuario.
			error_log( 'Eventos: ' . $error->technical_detail() );
			return $this->error( ErrorCode::ServerError, $error->getMessage() );
		}
	}

	/**
	 * Error con el formato estándar de WordPress y, si aplica, los errores por campo en `data.errors`.
	 *
	 * @param ErrorCode                   $code         Código del error.
	 * @param string                      $message      Mensaje para el usuario.
	 * @param array<string, list<string>> $field_errors Errores por campo.
	 */
	protected function error( ErrorCode $code, string $message, array $field_errors = [] ): WP_Error {
		$data = [ 'status' => $code->status() ];

		if ( [] !== $field_errors ) {
			$data['errors'] = $field_errors;
		}

		return new WP_Error( $code->value, $message, $data );
	}
}
