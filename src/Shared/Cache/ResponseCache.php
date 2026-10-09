<?php
/**
 * Caché de respuestas de lectura.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Cache;

use Probolsas\Eventos\Core\Lifecycle\UninstallTask;

/**
 * Guarda respuestas ya armadas (por ejemplo, el feed del calendario de un rango) en transients de
 * WordPress, que usan la caché de objetos si el sitio la tiene (H-401, R-17).
 *
 * Invalidación por **versión**: cada clave incluye el número guardado en la opción `eventos_cache_version`
 * y flush() lo incrementa. Así un solo cambio invalida todas las entradas sin tener que buscarlas; las
 * viejas dejan de leerse y caducan solas (TTL). La respuesta HTTP sigue con `no-store`: esta caché vive en
 * el servidor y nunca en el navegador ni en un proxy.
 */
final class ResponseCache implements UninstallTask {

	public const VERSION_OPTION = 'eventos_cache_version';

	/**
	 * Duración de una entrada; solo sirve para que las versiones viejas se limpien solas.
	 */
	public const TTL = 12 * 3600;

	private const PREFIX = 'eventos_';

	/**
	 * Versión leída en esta petición.
	 *
	 * @var int|null
	 */
	private ?int $version = null;

	/**
	 * Devuelve la respuesta guardada o la arma y la guarda. Si `$build` lanza una excepción (por ejemplo,
	 * un 422), no se guarda nada.
	 *
	 * @template T
	 *
	 * @param string        $name  Nombre de la consulta (`calendar`, `upcoming`).
	 * @param array<mixed>  $parts Lo que distingue una respuesta de otra (rango, tipos, «hoy»…).
	 * @param callable(): T $build Arma la respuesta.
	 *
	 * @return T
	 */
	public function remember( string $name, array $parts, callable $build ): mixed {
		$key    = self::PREFIX . $name . '_' . md5( $this->version() . '|' . (string) wp_json_encode( $parts ) );
		$cached = get_transient( $key );

		if ( is_array( $cached ) && array_key_exists( 'value', $cached ) ) {
			return $cached['value'];
		}

		$value = $build();
		set_transient( $key, [ 'value' => $value ], self::TTL );

		return $value;
	}

	/**
	 * Invalida todas las respuestas guardadas.
	 */
	public function flush(): void {
		$this->version = $this->version() + 1;
		update_option( self::VERSION_OPTION, $this->version, true );
	}

	/**
	 * Al desinstalar, borra la opción de la versión (las entradas caducan solas).
	 */
	public function run(): void {
		delete_option( self::VERSION_OPTION );
	}

	/**
	 * Versión actual.
	 */
	private function version(): int {
		if ( null === $this->version ) {
			$this->version = max( 1, (int) get_option( self::VERSION_OPTION, 1 ) );
		}

		return $this->version;
	}
}
