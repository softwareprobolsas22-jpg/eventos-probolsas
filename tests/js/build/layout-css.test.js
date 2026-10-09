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

	it( 'la casilla oculta del interruptor queda anclada a su etiqueta: el panel no salta al activarlo (H-407)', () => {
		const source = css( 'admin' );

		expect( lastValue( source, '.ep-app .ep-switch__label', 'position' ) ).toBe( 'relative' );
		expect( lastValue( source, '.ep-app .ep-switch__input', 'position' ) ).toBe( 'absolute' );
	} );

	it( 'en móvil (< 576 px) la paginación queda en anterior / «Página x de y» / siguiente, con botones de 44 px (R-23)', () => {
		const source = css( 'admin' );
		const mobile = /@media not \(min-width:576px\)\{([^@]*)\}/.exec( source.slice( source.indexOf( '.ep-pagination__compact{' ) ) );

		expect( mobile ).not.toBeNull();
		expect( mobile[ 1 ] ).toContain( '.ep-app .ep-pagination__pages{display:none}' );
		expect( mobile[ 1 ] ).toContain( '.ep-app .ep-pagination [data-ep-page-edge]{display:none}' );
		expect( mobile[ 1 ] ).toContain( '.ep-app .ep-pagination .ep-icon-button{width:var(--ep-touch-target);height:var(--ep-touch-target)}' );
		expect( source ).toContain( '--ep-touch-target:2.75rem' );

		// «Mostrando x–y de z» se oculta a la vista, no a los lectores de pantalla (QA-029).
		const range = /@media not \(min-width:576px\)\{\.ep-app \.ep-data-table__range\{([^}]*)\}/.exec( source );
		expect( range ).not.toBeNull();
		expect( range[ 1 ] ).toContain( 'position:absolute' );
		expect( range[ 1 ] ).toContain( 'clip-path:inset(50%)' );
		expect( range[ 1 ] ).not.toContain( 'display:none' );
	} );

	it( 'la tabla contiene sus elementos ocultos con posición absoluta: no ensanchan la página en móvil (R-13, QA-040)', () => {
		const source = css( 'admin' );

		expect( lastValue( source, '.ep-app .ep-data-table', 'position' ) ).toBe( 'relative' );
		expect( lastValue( source, '.ep-app .ep-data-table__scroll', 'position' ) ).toBe( 'relative' );
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

/**
 * Indica si un selector es solo de Tippy o Notyf: empieza por una de sus clases y no nombra ninguna
 * otra clase (QA-028: `.notyf .btn` sí se revisa).
 *
 * @param {string} selector Selector.
 * @returns {boolean} Si pertenece solo a esas librerías.
 */
function isFloatingLibrary( selector ) {
	return /^(\.tippy-|\[data-tippy-root\]|\.notyf)/.test( selector ) && ! /\.(?!tippy-|notyf)[a-z_-]/i.test( selector );
}

describe( 'Bootstrap encapsulado en los contenedores del plugin', () => {
	const bootstrap = rules( css( 'shared' ) )
		.flatMap( ( { selector } ) => splitSelector( selector ) )
		// Font Awesome, Tippy y Notyf se cargan en la raíz a propósito: sus clases (`.fa*`, `.tippy-*`,
		// `.notyf*`) no chocan con wp-admin ni el tema, y los tooltips y toasts viven en <body> o en el
		// diálogo abierto, fuera de los contenedores del plugin.
		.filter( ( selector ) => ! /^(:root|:host|\.fa|:is\(\.fas)/.test( selector ) && ! isFloatingLibrary( selector ) );

	it( 'solo omite los selectores propios de Tippy y Notyf (QA-028)', () => {
		expect( isFloatingLibrary( '.tippy-box[data-placement^=top]>.tippy-arrow:before' ) ).toBe( true );
		expect( isFloatingLibrary( '.notyf__toast--upper' ) ).toBe( true );
		expect( isFloatingLibrary( '[data-tippy-root]' ) ).toBe( true );
		expect( isFloatingLibrary( '.notyf .btn' ) ).toBe( false );
		expect( isFloatingLibrary( '.tippy-box .form-control' ) ).toBe( false );
	} );

	it( 'compila Bootstrap', () => {
		expect( bootstrap.length ).toBeGreaterThan( 500 );
		expect( bootstrap ).toContain( `${ SCOPE } .btn` );
	} );

	it( 'ningún selector de Bootstrap queda fuera de .ep-app o .ep-public (no toca wp-admin ni el tema)', () => {
		expect( bootstrap.filter( ( selector ) => ! isScoped( selector ) ) ).toEqual( [] );
	} );

	it( 'las hojas de los widgets (FullCalendar, Tom Select) solo actúan dentro de sus contenedores `.ep-*`', () => {
		const selectors = rules( css( 'widgets' ) ).flatMap( ( { selector } ) => splitSelector( selector ) );

		expect( selectors.length ).toBeGreaterThan( 20 );
		expect( selectors.filter( ( selector ) => ! /^(:where\()?\.ep-[a-z]/.test( selector ) ) ).toEqual( [] );
	} );

	it( 'la verificación detecta un selector sin encapsular', () => {
		expect( isScoped( '.btn' ) ).toBe( false );
		expect( isScoped( ':is(.btn,.ep-app .card)' ) ).toBe( false );
		expect( isScoped( `:-webkit-any(.ep-app,.ep-public) .btn` ) ).toBe( true );
	} );

	it( 'Bootstrap no cambia la caja del contenedor: respeta los márgenes de .wrap y el fondo del entorno (QA-009)', () => {
		const container = rules( css( 'shared' ) ).filter( ( { selector } ) => splitSelector( selector ).every( ( part ) => SCOPE === part ) );

		expect( container.length ).toBeGreaterThan( 0 );
		for ( const { body } of container ) {
			expect( body ).not.toMatch( /(?:^|;)(margin|padding|background)[a-z-]*:/ );
		}
		// La tipografía del reboot sí se conserva.
		expect( container.some( ( { body } ) => body.includes( 'font-family:var(--bs-body-font-family)' ) ) ).toBe( true );
	} );

	it( 'el plugin propio tampoco quita los márgenes de .wrap en wp-admin', () => {
		const app = rules( css( 'admin' ) ).filter( ( { selector } ) => splitSelector( selector ).includes( '.ep-app' ) );

		for ( const { body } of app ) {
			expect( body ).not.toMatch( /(?:^|;)margin[a-z-]*:/ );
		}
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
