/**
 * Utilidades para revisar el CSS compilado de assets/dist (el DOM simulado de las pruebas no aplica CSS).
 */
import { readFileSync, readdirSync } from 'node:fs';

const dist = new URL( '../../../assets/dist/', import.meta.url );

/**
 * CSS compilado de una entrada (`admin`, `public`) o de la hoja compartida (Bootstrap + Font Awesome).
 *
 * @param {'admin'|'public'|'shared'} name Hoja.
 * @returns {string} CSS minificado.
 */
export function css( name ) {
	if ( 'shared' !== name ) {
		return readFileSync( new URL( `css/${ name }.css`, dist ), 'utf8' );
	}
	const files = readdirSync( new URL( 'css/', dist ) ).filter( ( file ) => ! [ 'admin.css', 'public.css' ].includes( file ) );
	return files.map( ( file ) => readFileSync( new URL( `css/${ file }`, dist ), 'utf8' ) ).join( '\n' );
}

/**
 * Divide un selector compuesto por sus comas de primer nivel (no las de `:is()` o `:not()`).
 *
 * @param {string} selector Selector.
 * @returns {string[]} Selectores.
 */
export function splitSelector( selector ) {
	const parts = [];
	let depth = 0;
	let current = '';
	for ( const char of selector ) {
		if ( '(' === char ) {
			depth++;
		} else if ( ')' === char ) {
			depth--;
		} else if ( ',' === char && 0 === depth ) {
			parts.push( current.trim() );
			current = '';
			continue;
		}
		current += char;
	}
	parts.push( current.trim() );
	return parts.filter( Boolean );
}

/**
 * Reglas de estilo (selector y declaraciones) en orden de aparición, sin @keyframes ni @font-face.
 * Las reglas dentro de @media se devuelven con su selector, sin la condición.
 *
 * @param {string} source CSS minificado.
 * @returns {Array<{ selector: string, body: string }>} Reglas.
 */
export function rules( source ) {
	const cleaned = source
		.replace( /@keyframes[^{]+\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/g, '' )
		.replace( /@font-face\{[^}]*\}/g, '' )
		.replace( /@(?:media|supports|layer)[^{]*\{/g, '' );
	return [ ...cleaned.matchAll( /([^{}]+)\{([^{}]*)\}/g ) ]
		.map( ( [ , selector, body ] ) => ( { selector: selector.trim(), body } ) )
		.filter( ( { selector } ) => selector && ! selector.startsWith( '@' ) );
}
