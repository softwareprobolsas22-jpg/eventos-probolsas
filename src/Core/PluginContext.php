<?php
/**
 * Datos de ubicación y versión del plugin.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core;

/**
 * Rutas, URL y versión del plugin, inyectables en cualquier servicio en lugar de usar constantes globales.
 */
final class PluginContext {

	/**
	 * Crea el contexto.
	 *
	 * @param string $file     Ruta absoluta del archivo principal del plugin.
	 * @param string $base_dir Directorio del plugin, terminado en "/".
	 * @param string $base_url URL del plugin, terminada en "/".
	 * @param string $version  Versión del plugin.
	 */
	public function __construct(
		public readonly string $file,
		public readonly string $base_dir,
		public readonly string $base_url,
		public readonly string $version
	) {}

	/**
	 * Crea el contexto a partir del archivo principal del plugin.
	 *
	 * @param string $file    Ruta absoluta del archivo principal del plugin.
	 * @param string $version Versión del plugin.
	 */
	public static function from_plugin_file( string $file, string $version ): self {
		return new self( $file, plugin_dir_path( $file ), plugin_dir_url( $file ), $version );
	}

	/**
	 * Ruta absoluta de un archivo o directorio del plugin.
	 *
	 * @param string $relative Ruta relativa a la raíz del plugin.
	 */
	public function path( string $relative ): string {
		return $this->base_dir . ltrim( $relative, '/' );
	}

	/**
	 * URL pública de un archivo del plugin.
	 *
	 * @param string $relative Ruta relativa a la raíz del plugin.
	 */
	public function url( string $relative ): string {
		return $this->base_url . ltrim( $relative, '/' );
	}
}
