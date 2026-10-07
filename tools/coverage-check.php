<?php
/**
 * Verifica el umbral de cobertura de la Definition of Done (DoD 3) sobre el reporte Clover de PHPUnit.
 *
 * Uso: php tools/coverage-check.php <clover.xml> <umbral> <carpeta> [<carpeta>…]
 * Ejemplo: php tools/coverage-check.php build/clover.xml 80 src/Shared src/Domains
 *
 * Suma las líneas ejecutables y cubiertas de los archivos que están dentro de las carpetas indicadas
 * (rutas relativas a la raíz del plugin). Las carpetas que todavía no existen se ignoran.
 *
 * Herramienta de desarrollo: no forma parte del paquete del plugin (.gitattributes).
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped -- Script de línea de comandos, fuera de WordPress.

if ( $argc < 4 ) {
	fwrite( STDERR, "Uso: php tools/coverage-check.php <clover.xml> <umbral> <carpeta> [<carpeta>…]\n" );
	exit( 2 );
}

$eventos_clover = simplexml_load_file( $argv[1] );

if ( false === $eventos_clover ) {
	fwrite( STDERR, "No se pudo leer el reporte de cobertura: {$argv[1]}\n" );
	exit( 2 );
}

$eventos_threshold = (float) $argv[2];
$eventos_root      = str_replace( '\\', '/', dirname( __DIR__ ) ) . '/';
$eventos_folders   = array_slice( $argv, 3 );
$eventos_results   = [];
$eventos_files     = [];
// En GitHub Actions el resultado se publica también como anotaciones (visibles sin abrir el registro).
$eventos_github = 'true' === getenv( 'GITHUB_ACTIONS' );

foreach ( $eventos_folders as $eventos_folder ) {
	$eventos_results[ $eventos_folder ] = [
		'lines'   => 0,
		'covered' => 0,
	];
}

foreach ( $eventos_clover->xpath( '//file' ) as $eventos_file ) {
	$eventos_path = str_replace( $eventos_root, '', str_replace( '\\', '/', (string) $eventos_file['name'] ) );

	foreach ( $eventos_folders as $eventos_folder ) {
		if ( str_starts_with( $eventos_path, rtrim( $eventos_folder, '/' ) . '/' ) ) {
			$eventos_lines   = (int) $eventos_file->metrics['statements'];
			$eventos_covered = (int) $eventos_file->metrics['coveredstatements'];

			$eventos_results[ $eventos_folder ]['lines']   += $eventos_lines;
			$eventos_results[ $eventos_folder ]['covered'] += $eventos_covered;

			if ( $eventos_lines > 0 ) {
				$eventos_files[ $eventos_path ] = 100 * $eventos_covered / $eventos_lines;
			}
		}
	}
}

$eventos_failed = false;

foreach ( $eventos_results as $eventos_folder => $eventos_result ) {
	if ( 0 === $eventos_result['lines'] ) {
		echo "{$eventos_folder}: sin código todavía, se omite.\n";
		continue;
	}

	$eventos_percent = 100 * $eventos_result['covered'] / $eventos_result['lines'];
	$eventos_ok      = $eventos_percent >= $eventos_threshold;
	$eventos_failed  = $eventos_failed || ! $eventos_ok;

	$eventos_summary = sprintf(
		'%s: %.1f %% (%d de %d líneas; mínimo %.0f %%)',
		$eventos_folder,
		$eventos_percent,
		$eventos_result['covered'],
		$eventos_result['lines'],
		$eventos_threshold
	);

	echo ( $eventos_ok ? 'OK    ' : 'FALLA ' ) . $eventos_summary . "\n";

	if ( $eventos_github ) {
		echo ( $eventos_ok ? '::notice title=Cobertura::' : '::error title=Cobertura::' ) . $eventos_summary . "\n";
	}
}

// Los archivos menos cubiertos, para saber dónde faltan pruebas.
asort( $eventos_files );
echo "\nArchivos con menor cobertura:\n";
foreach ( array_slice( $eventos_files, 0, 10, true ) as $eventos_path => $eventos_percent ) {
	printf( "  %5.1f %%  %s\n", $eventos_percent, $eventos_path );

	if ( $eventos_github && $eventos_percent < $eventos_threshold ) {
		printf( "::warning file=%s,title=Cobertura %.1f %%::Por debajo del mínimo de %.0f %%\n", $eventos_path, $eventos_percent, $eventos_threshold );
	}
}

exit( $eventos_failed ? 1 : 0 );
