import { describe, expect, it } from 'vitest';
import { pageWindow, paginate } from '../../../assets/src/js/core/pagination.js';

describe( 'paginate', () => {
	it( 'calcula la primera página de 230 registros de a 25', () => {
		expect( paginate( 230, 1, 25 ) ).toMatchObject( { page: 1, pageCount: 10, startIndex: 0, endIndex: 25, from: 1, to: 25, total: 230 } );
	} );

	it( 'calcula la última página incompleta', () => {
		expect( paginate( 230, 10, 25 ) ).toMatchObject( { page: 10, from: 226, to: 230 } );
	} );

	it( 'ajusta páginas fuera de rango', () => {
		expect( paginate( 230, 99, 25 ).page ).toBe( 10 );
		expect( paginate( 230, 0, 25 ).page ).toBe( 1 );
		expect( paginate( 230, Number.NaN, 25 ).page ).toBe( 1 );
	} );

	it( 'sin registros hay una sola página vacía', () => {
		expect( paginate( 0, 1, 25 ) ).toMatchObject( { page: 1, pageCount: 1, from: 0, to: 0 } );
	} );
} );

describe( 'pageWindow', () => {
	it( 'muestra todas las páginas si son pocas', () => {
		expect( pageWindow( 2, 4 ) ).toEqual( [ 1, 2, 3, 4 ] );
	} );

	it( 'abrevia con huecos en el medio', () => {
		expect( pageWindow( 5, 10 ) ).toEqual( [ 1, 'gap', 4, 5, 6, 'gap', 10 ] );
	} );

	it( 'no deja un hueco para una sola página omitida', () => {
		expect( pageWindow( 4, 10 ) ).toEqual( [ 1, 2, 3, 4, 5, 'gap', 10 ] );
	} );

	it( 'en los extremos', () => {
		expect( pageWindow( 1, 10 ) ).toEqual( [ 1, 2, 'gap', 10 ] );
		expect( pageWindow( 10, 10 ) ).toEqual( [ 1, 'gap', 9, 10 ] );
	} );
} );
