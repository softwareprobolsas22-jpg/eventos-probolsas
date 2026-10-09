<?php
/**
 * Doble de WP_Post para las pruebas unitarias (sin WordPress).
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Doble de una clase de WordPress.

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Entrada de WordPress con las propiedades que usa el plugin.
	 */
	final class WP_Post {

		/**
		 * ID (nombre de la propiedad en WordPress).
		 *
		 * @var int
		 */
		public int $ID; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase -- Nombre de WordPress.

		/**
		 * Contenido (donde se buscan los shortcodes).
		 *
		 * @var string
		 */
		public string $post_content = '';

		/**
		 * Crea la entrada.
		 *
		 * @param int    $id             ID.
		 * @param string $post_type      Tipo de entrada.
		 * @param string $post_mime_type Tipo MIME registrado (adjuntos).
		 * @param string $post_title     Título.
		 */
		public function __construct(
			int $id = 0,
			public string $post_type = 'post',
			public string $post_mime_type = '',
			public string $post_title = ''
		) {
			$this->ID = $id; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Nombre de WordPress.
		}
	}
}
