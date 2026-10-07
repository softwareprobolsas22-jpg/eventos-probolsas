/**
 * Reglas transversales verificadas sobre el CSS compilado de assets/dist.
 */
import { describe, expect, it } from 'vitest';
import { css, rules, splitSelector } from './css.js';

const SCOPE = ':is(.ep-app,.ep-public)';

/**
 * Última declaración de una propiedad para un selector exacto, en el orden en que aparece en el CSS.
 *
 * @param {string} source CSS.
 * @param {string} selector Selector exacto (una de sus partes).
 * @param {string} property Propiedad.
 * @returns {string|undefined} Valor.
 */
function lastValue( source, selector, property ) {
	let value;
	for ( const rule of rules( source ) ) {
		if ( splitSelector( rule.selector ).includes( selector ) ) {
			const match = new RegExp( `(?:^|;)${ property }:([^;]+)` ).exec( rule.body );
			value = match ? match[ 1 ] : value;
		}
	}
	return value;
}

describe( 'reglas transversales en el CSS compilado', () => {
	it.each( [
		[ 'admin', '.ep-app' ],
		[ 'public', '.ep-public' ],
	] )( '%s: textarea sin redimensionar (R-03), th centrados (R-05) y [hidden] siempre oculto', ( entry, scope ) => {
		const source = css( entry );

		expect( lastValue( source, `${ scope } textarea`, 'resize' ) ).toBe( 'none' );
		expect( lastValue( source, `${ scope } th`, 'text-align' ) ).toBe( 'center' );
		expect( lastValue( source, `${ scope } [hidden]`, 'display' ) ).toBe( 'none!important' );
	} );

	it( 'Bootstrap también deja los textarea sin redimensionar y los th centrados (no depende del orden de carga)', () => {
		const shared = css( 'shared' );

		expect( lastValue( shared, `${ SCOPE } textarea`, 'resize' ) ).toBe( 'none' );
		expect( lastValue( shared, `${ SCOPE } th`, 'text-align' ) ).toBe( 'center' );
	} );
} );

/**
 * Indica si un selector solo actúa dentro de los contenedores del plugin. El minificador puede agrupar
 * selectores en `:is(…)` y reescribir `:is` como `:-webkit-any` para navegadores antiguos.
 *
 * @param {string} selector Selector (sin comas de primer nivel).
 * @returns {boolean} Si está encapsulado.
 */
function isScoped( selector ) {
	const normalized = selector.replace( /:-webkit-any\(/g, ':is(' );

	if ( normalized.startsWith( SCOPE ) ) {
		return true;
	}

	const group = /^:is\((.*)\)$/.exec( normalized );
	return null !== group && splitSelector( group[ 1 ] ).every( isScoped );
}

describe( 'Bootstrap encapsulado en los contenedores del plugin', () => {
	const bootstrap = rules( css( 'shared' ) )
		.flatMap( ( { selector } ) => splitSelector( selector ) )
		// Font Awesome se carga en la raíz a propósito: sus clases `.fa*` no chocan con wp-admin ni el tema.
		.filter( ( selector ) => ! /^(:root|:host|\.fa|:is\(\.fas)/.test( selector ) );

	it( 'compila Bootstrap', () => {
		expect( bootstrap.length ).toBeGreaterThan( 500 );
		expect( bootstrap ).toContain( `${ SCOPE } .btn` );
	} );

	it( 'ningún selector de Bootstrap queda fuera de .ep-app o .ep-public (no toca wp-admin ni el tema)', () => {
		expect( bootstrap.filter( ( selector ) => ! isScoped( selector ) ) ).toEqual( [] );
	} );

	it( 'la verificación detecta un selector sin encapsular', () => {
		expect( isScoped( '.btn' ) ).toBe( false );
		expect( isScoped( ':is(.btn,.ep-app .card)' ) ).toBe( false );
		expect( isScoped( `:-webkit-any(.ep-app,.ep-public) .btn` ) ).toBe( true );
	} );

	it( 'las variables de Bootstrap se declaran en el contenedor, no en :root, y usan el color de la marca', () => {
		const roots = rules( css( 'shared' ) ).filter( ( { body } ) => body.includes( '--bs-primary:' ) );

		expect( roots.length ).toBeGreaterThan( 0 );
		for ( const { selector, body } of roots ) {
			expect( splitSelector( selector )[ 0 ] ).toBe( SCOPE );
			expect( body ).toMatch( /--bs-primary:#155728/i );
		}
	} );
} );
