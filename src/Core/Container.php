<?php
/**
 * Contenedor de inyección de dependencias.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core;

/**
 * Contenedor mínimo: servicios compartidos construidos de forma diferida y etiquetas para colecciones.
 *
 * Los identificadores son nombres de clase o interfaz, lo que permite validar el tipo del servicio construido.
 */
final class Container {

	/**
	 * Fábricas registradas por identificador.
	 *
	 * @var array<string, callable(Container): object>
	 */
	private array $factories = [];

	/**
	 * Servicios ya construidos por identificador.
	 *
	 * @var array<string, object>
	 */
	private array $instances = [];

	/**
	 * Identificadores agrupados por etiqueta, en orden de registro.
	 *
	 * @var array<string, list<class-string>>
	 */
	private array $tags = [];

	/**
	 * Registra un servicio compartido. Se construye una sola vez, la primera vez que se solicita.
	 *
	 * @param string                      $id      Nombre de la clase o interfaz que identifica el servicio.
	 * @param callable(Container): object $factory Fábrica que construye el servicio.
	 */
	public function set( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Indica si hay un servicio registrado con el identificador dado.
	 *
	 * @param string $id Identificador del servicio.
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] );
	}

	/**
	 * Obtiene un servicio, construyéndolo si aún no existe.
	 *
	 * @template T of object
	 *
	 * @param string $id Nombre de la clase o interfaz del servicio.
	 * @phpstan-param class-string<T> $id
	 *
	 * @return object
	 * @phpstan-return T
	 *
	 * @throws ContainerException Si el servicio no está registrado o la fábrica devuelve otro tipo.
	 */
	public function get( string $id ): object {
		if ( ! isset( $this->instances[ $id ] ) ) {
			if ( ! isset( $this->factories[ $id ] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Error interno de programación; no se imprime en HTML.
				throw ContainerException::not_found( $id );
			}

			$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );
		}

		$service = $this->instances[ $id ];

		if ( ! $service instanceof $id ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Error interno de programación; no se imprime en HTML.
			throw ContainerException::invalid_type( $id, $service );
		}

		return $service;
	}

	/**
	 * Agrega un servicio registrado a una etiqueta.
	 *
	 * @param string $tag Nombre de la etiqueta.
	 * @param string $id  Nombre de la clase o interfaz del servicio.
	 * @phpstan-param class-string $id
	 */
	public function tag( string $tag, string $id ): void {
		$this->tags[ $tag ][] = $id;
	}

	/**
	 * Obtiene todos los servicios de una etiqueta, en orden de registro.
	 *
	 * @template T of object
	 *
	 * @param string $tag  Nombre de la etiqueta.
	 * @param string $type Clase o interfaz que deben cumplir todos los servicios de la etiqueta.
	 * @phpstan-param class-string<T> $type
	 *
	 * @return object[]
	 * @phpstan-return list<T>
	 *
	 * @throws ContainerException Si algún servicio no está registrado o no es del tipo esperado.
	 */
	public function tagged( string $tag, string $type ): array {
		$services = [];

		foreach ( $this->tags[ $tag ] ?? [] as $id ) {
			$service = $this->get( $id );

			if ( ! $service instanceof $type ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Error interno de programación; no se imprime en HTML.
				throw ContainerException::invalid_type( $type, $service );
			}

			$services[] = $service;
		}

		return $services;
	}
}
