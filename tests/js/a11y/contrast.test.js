/**
 * Contraste WCAG 2.1 AA (R-02) de los pares de colores de la interfaz, leídos de los tokens compilados
 * (`--ep-color-*` en assets/dist/css/admin.css). Si alguien cambia un token y rompe el contraste, esta
 * prueba falla. Incluye la regla de la paleta: #669F30 no cumple AA para texto normal y solo se usa en
 * acentos, bordes, íconos y el anillo de foco (≥ 3:1).
 */
import { describe, expect, it } from 'vitest';
import { css, rules } from '../build/css.js';

const admin = css( 'admin' );

/** Tokens de color compilados, por nombre. */
const tokens = Object.fromEntries( [ ...admin.matchAll( /--ep-color-([a-z-]+):\s*(#[0-9a-f]{3,8})/gi ) ].map( ( [ , name, value ] ) => [ name, value ] ) );

/**
 * Luminancia relativa (WCAG 2.1).
 *
 * @param {string} hex Color `#rgb` o `#rrggbb`.
 * @returns {number} Luminancia.
 */
function luminance( hex ) {
	const value = hex.slice( 1 );
	const full = 3 === value.length ? [ ...value ].map( ( char ) => char + char ).join( '' ) : value.slice( 0, 6 );
	const [ r, g, b ] = [ 0, 2, 4 ].map( ( index ) => {
		const channel = parseInt( full.slice( index, index + 2 ), 16 ) / 255;
		return channel <= 0.03928 ? channel / 12.92 : ( ( channel + 0.055 ) / 1.055 ) ** 2.4;
	} );
	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/**
 * Relación de contraste entre dos tokens.
 *
 * @param {string} foreground Token del texto o del elemento.
 * @param {string} background Token del fondo.
 * @returns {number} Relación (1 a 21).
 */
function contrast( foreground, background ) {
	const [ light, dark ] = [ luminance( tokens[ foreground ] ), luminance( tokens[ background ] ) ].sort( ( a, b ) => b - a );
	return ( light + 0.05 ) / ( dark + 0.05 );
}

/** Texto normal: 4,5:1 (WCAG 1.4.3). [texto, fondo, dónde se usa]. */
const TEXT_PAIRS = [
	[ 'text', 'surface', 'texto general' ],
	[ 'text', 'surface-muted', 'texto sobre fondos suaves' ],
	[ 'text-subtle', 'surface', 'etiquetas de campos' ],
	[ 'text-muted', 'surface', 'ayudas y metadatos' ],
	[ 'text-muted', 'surface-muted', 'estados vacíos' ],
	[ 'primary', 'surface', 'enlaces y botones secundarios' ],
	[ 'on-primary', 'primary', 'botón primario' ],
	[ 'on-primary', 'primary-hover', 'botón primario al pasar el mouse' ],
	[ 'primary', 'secondary-soft', 'conteos y pestañas' ],
	[ 'error', 'surface', 'mensajes de error de los campos (R-24)' ],
	[ 'error', 'error-soft', 'avisos de error' ],
	[ 'warning', 'surface', 'avisos, por ejemplo «Esta fecha ya pasó»' ],
	[ 'info', 'surface', 'avisos informativos' ],
	[ 'success', 'surface', 'mensajes de éxito' ],
];

/** Componentes y estados (no texto): 3:1 (WCAG 1.4.11). */
const NON_TEXT_PAIRS = [
	[ 'focus', 'surface', 'anillo de foco' ],
	[ 'secondary', 'surface', 'acentos e íconos (#669F30)' ],
	[ 'primary', 'surface', 'borde de campo enfocado' ],
];

describe( 'contraste de los tokens de color (R-02)', () => {
	it( 'lee los tokens compilados con la paleta de la marca (R-01)', () => {
		expect( Object.keys( tokens ).length ).toBeGreaterThan( 15 );
		expect( tokens.primary.toLowerCase() ).toBe( '#155728' );
		expect( tokens.secondary.toLowerCase() ).toBe( '#669f30' );
		expect( tokens.surface.toLowerCase() ).toMatch( /^#fff(fff)?$/ );
	} );

	it.each( TEXT_PAIRS )( 'texto «%s» sobre «%s» (%s) cumple AA: 4,5:1', ( foreground, background ) => {
		expect( contrast( foreground, background ) ).toBeGreaterThanOrEqual( 4.5 );
	} );

	it.each( NON_TEXT_PAIRS )( '«%s» sobre «%s» (%s) cumple 3:1', ( foreground, background ) => {
		expect( contrast( foreground, background ) ).toBeGreaterThanOrEqual( 3 );
	} );

	it( '#669F30 no cumple AA para texto normal: como color de texto solo se aplica a íconos', () => {
		expect( contrast( 'secondary', 'surface' ) ).toBeLessThan( 4.5 );

		const painted = rules( admin )
			.filter( ( { body } ) => /(?:^|;)color:var\(--ep-color-secondary\)/.test( body ) )
			.flatMap( ( { selector } ) => selector.split( ',' ) );
		for ( const selector of painted ) {
			expect( selector.trim(), `«${ selector.trim() }» pinta texto con #669F30` ).toMatch( / i$/ );
		}
	} );
} );
