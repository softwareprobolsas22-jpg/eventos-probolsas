import { afterEach, describe, expect, it, vi } from 'vitest';
import { __, _n, sprintf } from '../../../assets/src/js/core/i18n.js';

describe( 'i18n fuera de WordPress (pruebas, sin wp.i18n)', () => {
	it( '__ devuelve el texto original', () => {
		expect( __( 'Guardar', 'eventos-probolsas' ) ).toBe( 'Guardar' );
	} );

	it( '_n elige singular o plural según la cantidad', () => {
		expect( _n( '%d evento', '%d eventos', 1, 'eventos-probolsas' ) ).toBe( '%d evento' );
		expect( _n( '%d evento', '%d eventos', 0, 'eventos-probolsas' ) ).toBe( '%d eventos' );
		expect( _n( '%d evento', '%d eventos', 3, 'eventos-probolsas' ) ).toBe( '%d eventos' );
	} );

	it( 'sprintf reemplaza marcadores en orden y por posición', () => {
		expect( sprintf( '%d eventos el %s', 3, '07/10/2026' ) ).toBe( '3 eventos el 07/10/2026' );
		expect( sprintf( '%2$s: %1$d', 3, 'Cumpleaños' ) ).toBe( 'Cumpleaños: 3' );
	} );
} );

describe( 'i18n dentro de WordPress (con wp.i18n)', () => {
	afterEach( () => {
		delete globalThis.wp;
	} );

	it( 'delega en wp.i18n con el dominio del plugin', () => {
		const i18n = {
			__: vi.fn( () => 'Save' ),
			_n: vi.fn( () => 'events' ),
			sprintf: vi.fn( () => 'formatted' ),
		};
		globalThis.wp = { i18n };

		expect( __( 'Guardar' ) ).toBe( 'Save' );
		expect( _n( 'evento', 'eventos', 2 ) ).toBe( 'events' );
		expect( sprintf( '%s', 'x' ) ).toBe( 'formatted' );
		expect( i18n.__ ).toHaveBeenCalledWith( 'Guardar', 'eventos-probolsas' );
		expect( i18n._n ).toHaveBeenCalledWith( 'evento', 'eventos', 2, 'eventos-probolsas' );
	} );
} );
