import { afterEach, describe, expect, it } from 'vitest';
import { readConfig } from '../../../assets/src/js/core/config.js';

const sample = () => ( {
	restUrl: 'https://intranet.test/wp-json/eventos/v1/',
	today: '2026-10-07',
	ui: { timezone: 'America/Bogota', page_sizes: [ 25, 50, 100 ] },
} );

describe( 'readConfig', () => {
	afterEach( () => {
		delete globalThis.epConfig;
	} );

	it( 'lee window.epConfig por defecto', () => {
		globalThis.epConfig = sample();

		expect( readConfig().ui.timezone ).toBe( 'America/Bogota' );
		expect( readConfig().today ).toBe( '2026-10-07' );
	} );

	it( 'devuelve una copia: cambiar el origen no altera el resultado', () => {
		const source = sample();
		const config = readConfig( source );

		source.ui.page_sizes.push( 200 );

		expect( config.ui.page_sizes ).toEqual( [ 25, 50, 100 ] );
	} );

	it( 'congela la configuración en profundidad (el JS nunca redefine valores SSOT)', () => {
		const config = readConfig( sample() );

		expect( Object.isFrozen( config ) ).toBe( true );
		expect( Object.isFrozen( config.ui ) ).toBe( true );
		expect( Object.isFrozen( config.ui.page_sizes ) ).toBe( true );
	} );

	it.each( [
		[ 'ausente', undefined ],
		[ 'nula', null ],
		[ 'un arreglo', [] ],
		[ 'un texto', 'config' ],
	] )( 'falla si la configuración es %s', ( _label, value ) => {
		expect( () => readConfig( value ) ).toThrow( /epConfig no está disponible/ );
	} );
} );
