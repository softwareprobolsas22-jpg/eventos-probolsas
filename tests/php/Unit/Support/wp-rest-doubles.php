<?php
/**
 * Dobles mínimos de WP_Error y WP_REST_Response para las pruebas unitarias (sin WordPress).
 *
 * Reproducen solo la parte de la API pública que usa el plugin. Las pruebas de integración usan las
 * clases reales de WordPress, por eso estos dobles se cargan únicamente desde tests/php/Unit/bootstrap.php.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound, Generic.Files.OneObjectStructurePerFile.MultipleFound, Universal.Files.SeparateFunctionsFromOO.Mixed -- Dobles de clases de WordPress.

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Doble de WP_Error.
	 */
	class WP_Error {

		/**
		 * Crea el error.
		 *
		 * @param string $code    Código.
		 * @param string $message Mensaje.
		 * @param mixed  $data    Datos.
		 */
		public function __construct( private string $code = '', private string $message = '', private mixed $data = '' ) {}

		/**
		 * Código del error.
		 */
		public function get_error_code(): string {
			return $this->code;
		}

		/**
		 * Mensaje del error.
		 */
		public function get_error_message(): string {
			return $this->message;
		}

		/**
		 * Datos del error.
		 */
		public function get_error_data(): mixed {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Doble de WP_REST_Response.
	 */
	class WP_REST_Response {

		/**
		 * Cabeceras de la respuesta.
		 *
		 * @var array<string, string>
		 */
		private array $headers = [];

		/**
		 * Crea la respuesta.
		 *
		 * @param mixed $data   Datos.
		 * @param int   $status Estado HTTP.
		 */
		public function __construct( private mixed $data = null, private int $status = 200 ) {}

		/**
		 * Agrega una cabecera.
		 *
		 * @param string $key   Nombre.
		 * @param string $value Valor.
		 */
		public function header( string $key, string $value ): void {
			$this->headers[ $key ] = $value;
		}

		/**
		 * Datos.
		 */
		public function get_data(): mixed {
			return $this->data;
		}

		/**
		 * Estado HTTP.
		 */
		public function get_status(): int {
			return $this->status;
		}

		/**
		 * Cabeceras.
		 *
		 * @return array<string, string>
		 */
		public function get_headers(): array {
			return $this->headers;
		}
	}
}
