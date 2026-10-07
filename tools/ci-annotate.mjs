/**
 * Publica en GitHub Actions, como anotaciones, el resultado de las pruebas de integración: cada prueba
 * fallida (desde el reporte JUnit de PHPUnit) y las últimas líneas de la salida. Así el fallo se ve en el
 * resumen de la ejecución sin abrir el registro.
 *
 * Uso: node tools/ci-annotate.mjs <junit.xml> <salida.log>
 * Herramienta de desarrollo: no forma parte del paquete del plugin (.gitattributes).
 */
import { existsSync, readFileSync } from 'node:fs';

const [ junitFile, logFile ] = process.argv.slice( 2 );

/**
 * Escapa un texto para una anotación de GitHub Actions (una sola línea).
 *
 * @param {string} text Texto.
 * @returns {string} Texto escapado.
 */
const escape = ( text ) => text.replace( /%/g, '%25' ).replace( /\r/g, '' ).replace( /\n/g, '%0A' );

/**
 * Decodifica las entidades XML más comunes.
 *
 * @param {string} text Texto.
 * @returns {string} Texto decodificado.
 */
const decode = ( text ) =>
	text.replace( /&lt;/g, '<' ).replace( /&gt;/g, '>' ).replace( /&quot;/g, '"' ).replace( /&#039;|&apos;/g, "'" ).replace( /&amp;/g, '&' );

let annotated = 0;

if ( junitFile && existsSync( junitFile ) ) {
	const xml = readFileSync( junitFile, 'utf8' );
	const cases = xml.matchAll( /<testcase\s+name="([^"]+)"\s+class="([^"]+)"[^>]*>([\s\S]*?)<\/testcase>/g );

	for ( const [ , name, className, body ] of cases ) {
		const failure = /<(failure|error)[^>]*>([\s\S]*?)<\/\1>/.exec( body );
		if ( failure && annotated < 9 ) {
			const message = decode( failure[ 2 ] ).trim().split( '\n' ).slice( 0, 12 ).join( '\n' );
			console.log( `::error title=${ escape( `${ className.split( '\\' ).pop() }::${ name }` ) }::${ escape( message ) }` );
			annotated++;
		}
	}
} else {
	console.log( '::warning title=Integración::No se generó el reporte JUnit (la suite no llegó a ejecutarse).' );
}

if ( logFile && existsSync( logFile ) ) {
	const tail = readFileSync( logFile, 'utf8' ).trim().split( '\n' ).slice( -40 ).join( '\n' );
	console.log( `::error title=Integración (últimas líneas)::${ escape( tail ) }` );
}
