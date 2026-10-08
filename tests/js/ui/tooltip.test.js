// @vitest-environment happy-dom
import { afterEach, describe, expect, it, vi } from 'vitest';

const tippy = vi.hoisted( () => ( { calls: [], destroy: vi.fn() } ) );

vi.mock( 'tippy.js', () => ( {
	delegate: ( root, options ) => {
		tippy.calls.push( { root, options } );
		return { destroy: tippy.destroy };
	},
} ) );

const { initTooltips, isTruncated } = await import( '../../../assets/src/js/ui/tooltip.js' );

/**
 * Elemento con medidas simuladas (happy-dom no calcula el layout).
 *
 * @param {number} scrollWidth Ancho del contenido.
 * @returns {HTMLElement} Elemento.
 */
function measured( scrollWidth ) {
	const element = document.createElement( 'span' );
	Object.defineProperty( element, 'clientWidth', { value: 100 } );
	Object.defineProperty( element, 'scrollWidth', { value: scrollWidth } );
	return element;
}

afterEach( () => {
	tippy.calls.length = 0;
	tippy.destroy.mockClear();
	vi.unstubAllGlobals();
} );

describe( 'tooltips (D-11)', () => {
	it( 'delegan en el contenedor: [data-ep-tooltip] y textos truncados, con el tema del plugin', () => {
		const disable = initTooltips( document.body );
		const [ labels, truncated ] = tippy.calls.map( ( call ) => call.options );

		expect( tippy.calls.every( ( call ) => document.body === call.root ) ).toBe( true );
		expect( labels.target ).toBe( '[data-ep-tooltip]' );
		expect( labels.theme ).toBe( 'ep' );
		expect( labels.trigger ).toBe( 'mouseenter focus' );
		expect( labels.appendTo( document.body.appendChild( document.createElement( 'button' ) ) ) ).toBe( document.body );
		expect( truncated.target ).toBe( '.ep-truncate' );

		const action = document.createElement( 'button' );
		action.setAttribute( 'data-ep-tooltip', 'Editar' );
		expect( labels.content( action ) ).toBe( 'Editar' );

		const text = document.createElement( 'span' );
		text.textContent = '  Reunión de planeación anual  ';
		expect( truncated.content( text ) ).toBe( 'Reunión de planeación anual' );

		disable();
		expect( tippy.destroy ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'el texto truncado solo muestra tooltip si está cortado con «…» (D-12)', () => {
		initTooltips( document.body );
		const truncated = tippy.calls[ 1 ].options;

		expect( isTruncated( measured( 100 ) ) ).toBe( false );
		expect( truncated.onShow( { reference: measured( 100 ) } ) ).toBe( false );
		expect( isTruncated( measured( 180 ) ) ).toBe( true );
		expect( truncated.onShow( { reference: measured( 180 ) } ) ).toBe( true );
	} );

	it( 'respeta la preferencia de movimiento reducido', () => {
		vi.stubGlobal( 'matchMedia', () => ( { matches: true } ) );

		initTooltips( document.body );

		expect( tippy.calls[ 0 ].options.animation ).toBe( false );
		expect( tippy.calls[ 0 ].options.duration ).toBe( 0 );
	} );

	it( 'dentro de un drawer o diálogo abierto, el tooltip se inserta en él (QA-024)', () => {
		initTooltips( document.body );
		const { appendTo } = tippy.calls[ 0 ].options;

		const dialog = document.createElement( 'dialog' );
		const close = document.createElement( 'button' );
		dialog.append( close );
		document.body.append( dialog );
		dialog.setAttribute( 'open', '' );

		expect( appendTo( close ) ).toBe( dialog );

		dialog.removeAttribute( 'open' );
		expect( appendTo( close ) ).toBe( document.body );
	} );
} );
