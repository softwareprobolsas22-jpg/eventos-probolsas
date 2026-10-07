<?php
/**
 * Renderizado de plantillas.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core\View;

/**
 * Renderiza las plantillas de `templates/`. Cada plantilla recibe sus datos en la variable `$data`
 * y es responsable de escapar todo lo que imprime.
 */
final class View {

	/**
	 * Crea el renderizador.
	 *
	 * @param string $templates_dir Directorio raíz de las plantillas.
	 */
	public function __construct( private readonly string $templates_dir ) {}

	/**
	 * Imprime una plantilla.
	 *
	 * @param string               $template Ruta relativa sin extensión, por ejemplo `admin/layout`.
	 * @param array<string, mixed> $data     Datos de la plantilla.
	 */
	public function render( string $template, array $data = [] ): void {
		$file = $this->resolve( $template );

		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $data la consume la plantilla incluida.
		( static function ( string $eventos_template_file, array $data ): void {
			require $eventos_template_file;
		} )( $file, $data );
	}

	/**
	 * Devuelve el HTML de una plantilla en lugar de imprimirlo.
	 *
	 * @param string               $template Ruta relativa sin extensión.
	 * @param array<string, mixed> $data     Datos de la plantilla.
	 *
	 * @throws \Throwable Cualquier error de la plantilla, después de descartar la salida parcial.
	 */
	public function fetch( string $template, array $data = [] ): string {
		ob_start();

		try {
			$this->render( $template, $data );
		} catch ( \Throwable $error ) {
			ob_end_clean();
			throw $error;
		}

		return (string) ob_get_clean();
	}

	/**
	 * Valida el nombre de la plantilla y devuelve su ruta absoluta.
	 *
	 * @param string $template Ruta relativa sin extensión.
	 *
	 * @throws \InvalidArgumentException Si el nombre no es válido o la plantilla no existe.
	 */
	private function resolve( string $template ): string {
		if ( 1 !== preg_match( '#^[a-z0-9-]+(/[a-z0-9-]+)*$#', $template ) ) {
			throw new \InvalidArgumentException( 'Nombre de plantilla no válido.' );
		}

		$file = rtrim( $this->templates_dir, '/\\' ) . '/' . $template . '.php';

		if ( ! is_readable( $file ) ) {
			throw new \InvalidArgumentException( 'La plantilla solicitada no existe.' );
		}

		return $file;
	}
}
