/**
 * Font Awesome en el CSS compilado: solo los íconos que usa el plugin, y ninguno de ellos falta (QA-033).
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { usedIcons } from '../../../tools/used-icons.mjs';
import { css, rules, splitSelector } from './css.js';

const ROOT = fileURLToPath( new URL( '../../../', import.meta.url ) );

/**
 * Íconos (nombres sin `fa-`) declarados en una hoja: reglas `.fa-<nombre>` cuyo cuerpo es solo `--fa: …`
 * (el mismo criterio de vite.config.js).
 *
 * @param {string} source CSS.
 * @returns {Set<string>} Nombres.
 */
function declaredIcons( source ) {
	const names = new Set();
	for ( const { selector, body } of rules( source ) ) {
		const declarations = body.split( ';' ).map( ( item ) => item.trim() ).filter( Boolean );
		const parts = splitSelector( selector );
		const isIcon = declarations.length > 0 && declarations.every( ( item ) => /^--fa\s*:/.test( item ) ) && parts.every( ( part ) => /^\.fa-[a-z0-9-]+$/.test( part ) );
		if ( isIcon ) {
			parts.forEach( ( part ) => names.add( part.replace( /^\.fa-/, '' ) ) );
		}
	}
	return names;
}

const fontAwesome = declaredIcons( readFileSync( new URL( '../../../node_modules/@fortawesome/fontawesome-free/css/fontawesome.css', import.meta.url ), 'utf8' ) );
const compiled = declaredIcons( css( 'shared' ) );
const used = usedIcons( ROOT );

describe( 'íconos de Font Awesome en el CSS compilado (QA-033)', () => {
	it( 'detecta los íconos del código y los del catálogo, pero no las plantillas `fa-${ … }`', () => {
		expect( fontAwesome.size ).toBeGreaterThan( 1500 );
		// Código (data-table, drawer) y catálogo (config/icons.php).
		[ 'pen', 'trash', 'xmark', 'angles-left', 'cake-candles', 'graduation-cap', 'briefcase' ].forEach( ( name ) => expect( used ).toContain( name ) );
		expect( [ ...used ].some( ( name ) => name.includes( '$' ) || name.endsWith( '-' ) ) ).toBe( false );
	} );

	it( 'conserva todos los íconos que usa el plugin', () => {
		const missing = [ ...used ].filter( ( name ) => fontAwesome.has( name ) && ! compiled.has( name ) );
		expect( missing ).toEqual( [] );
	} );

	it( 'descarta los íconos que el plugin no usa', () => {
		expect( [ ...compiled ].filter( ( name ) => ! used.has( name ) ) ).toEqual( [] );
		expect( compiled.size ).toBeLessThan( 200 );
		expect( compiled.has( 'ghost' ) ).toBe( false );
	} );

	it( 'conserva las clases de estilo y de utilidad, también las que solo declaran variables --fa-*', () => {
		const shared = css( 'shared' );
		[ '.fa-solid', '.fa-spin', '.fa-2x', '.fa-fw', '.fa-width-auto', '.fa-spin-reverse' ].forEach( ( selector ) => expect( shared ).toContain( selector ) );
	} );
} );
