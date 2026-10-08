import { describe, expect, it } from 'vitest';
import { bestContrastRatio, contrastRatio, isHexColor, readableTextTone } from '../../../assets/src/js/core/color.js';
import fixture from '../../fixtures/color-contrast.json';

// Mismos casos que ColorContrastTest (PHP): el tono que calcula la vista previa del formulario
// coincide con el text_tone que devuelve la API (SSOT en tests/fixtures/color-contrast.json).
describe( 'readableTextTone y bestContrastRatio (fixture compartido con PHP)', () => {
	it.each( fixture.cases.map( ( sample ) => [ sample.case, sample ] ) )( '%s', ( _, sample ) => {
		expect( readableTextTone( sample.color ) ).toBe( sample.tone );
		expect( bestContrastRatio( sample.color ) ).toBeCloseTo( sample.best_ratio, 2 );
	} );

	it( 'acepta colores en minúscula', () => {
		expect( readableTextTone( '#9d174d' ) ).toBe( 'light' );
	} );

	it( 'cualquier fondo alcanza AA con el tono elegido (R-02)', () => {
		for ( let value = 0; value <= 255; value++ ) {
			const channel = value.toString( 16 ).padStart( 2, '0' );
			expect( bestContrastRatio( `#${ channel.repeat( 3 ) }` ) ).toBeGreaterThanOrEqual( fixture.minimum_best_ratio );
		}
		for ( let value = 0; value <= 0xffffff; value += 0x0f0f0f ) {
			expect( bestContrastRatio( `#${ value.toString( 16 ).padStart( 6, '0' ) }` ) ).toBeGreaterThanOrEqual( fixture.minimum_best_ratio );
		}
	} );

	it( 'un color incompleto (mientras se escribe) usa texto claro', () => {
		for ( const value of fixture.invalid ) {
			expect( readableTextTone( value ) ).toBe( 'light' );
		}
	} );
} );

describe( 'contrastRatio', () => {
	it( 'es simétrico y va de 1 a 21', () => {
		expect( contrastRatio( '#000000', '#FFFFFF' ) ).toBeCloseTo( 21, 5 );
		expect( contrastRatio( '#155728', '#155728' ) ).toBeCloseTo( 1, 5 );
		expect( contrastRatio( '#155728', '#FFFFFF' ) ).toBeCloseTo( contrastRatio( '#FFFFFF', '#155728' ), 5 );
	} );
} );

describe( 'isHexColor', () => {
	it( 'acepta solo #RRGGBB', () => {
		expect( isHexColor( '#669f30' ) ).toBe( true );
		for ( const value of fixture.invalid ) {
			expect( isHexColor( value ) ).toBe( false );
		}
	} );
} );
