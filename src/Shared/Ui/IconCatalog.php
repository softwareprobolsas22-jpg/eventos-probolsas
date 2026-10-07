<?php
/**
 * Catálogo de íconos permitidos.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Ui;

use Probolsas\Eventos\Core\Config;

/**
 * Lee los íconos de `config/icons.php`: la API valida contra estas claves y el selector de la
 * interfaz recibe la misma lista en `epConfig.icons`.
 */
final class IconCatalog {

	/**
	 * Crea el catálogo.
	 *
	 * @param Config $config Configuración del plugin.
	 */
	public function __construct( private readonly Config $config ) {}

	/**
	 * Claves permitidas.
	 *
	 * @return list<string>
	 */
	public function keys(): array {
		return array_column( $this->all(), 'key' );
	}

	/**
	 * Indica si una clave está permitida.
	 *
	 * @param string $key Clave del ícono.
	 */
	public function has( string $key ): bool {
		return in_array( $key, $this->keys(), true );
	}

	/**
	 * Íconos para la interfaz, en el orden de la configuración.
	 *
	 * @return list<array{key: string, label: string, keywords: string}>
	 */
	public function all(): array {
		$icons = $this->config->get( 'icons', [] );
		$list  = [];

		foreach ( is_array( $icons ) ? $icons : [] as $key => $definition ) {
			$definition = is_array( $definition ) ? array_values( $definition ) : [];
			$list[]     = [
				'key'      => (string) $key,
				'label'    => (string) ( $definition[0] ?? $key ),
				'keywords' => (string) ( $definition[1] ?? '' ),
			];
		}

		return $list;
	}
}
