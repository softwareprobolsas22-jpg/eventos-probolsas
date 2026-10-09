// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createFilterBar } from '../../../assets/src/js/ui/filter-bar.js';

let container;
let onChange;
let bar;

beforeEach( () => {
	vi.useFakeTimers();
	document.body.innerHTML = '';
	container = document.createElement( 'div' );
	document.body.append( container );
	onChange = vi.fn();
	bar = createFilterBar( container, {
		title: 'Filtrar eventos',
		fields: [
			{ key: 'search', type: 'search', label: 'Buscar', placeholder: 'Título' },
			{ key: 'type', type: 'select', label: 'Tipo', allLabel: 'Todos los tipos', options: [ { value: 1, label: 'Cumpleaños' } ] },
			{ key: 'dates', type: 'date-range', label: 'Fechas' },
		],
		onChange,
	} );
} );

afterEach( () => vi.useRealTimers() );

const input = ( selector ) => container.querySelector( selector );
const clear = () => container.querySelector( '.ep-filter-bar__clear' );

describe( 'createFilterBar', () => {
	it( 'cada control tiene su etiqueta y el título nombra la sección', () => {
		const section = container.querySelector( 'section' );
		expect( document.getElementById( section.getAttribute( 'aria-labelledby' ) ).textContent ).toBe( 'Filtrar eventos' );
		container.querySelectorAll( 'input, select' ).forEach( ( control ) => {
			expect( container.querySelector( `label[for="${ control.id }"]` ) ).not.toBeNull();
		} );
		expect( clear().disabled ).toBe( true );
	} );

	it( 'setValues() aplica valores desde fuera, conserva los demás y avisa una sola vez (H-206)', () => {
		input( 'input[type="search"]' ).value = 'ana';
		input( 'input[type="search"]' ).dispatchEvent( new Event( 'input' ) );
		vi.advanceTimersByTime( 300 );
		onChange.mockClear();

		bar.setValues( { type: '1', dates: { from: '2026-10-07', to: '2026-10-07' }, desconocido: 'x' } );

		expect( onChange ).toHaveBeenCalledTimes( 1 );
		expect( onChange ).toHaveBeenCalledWith( { search: 'ana', type: '1', dates: { from: '2026-10-07', to: '2026-10-07' } } );
		expect( input( 'select' ).value ).toBe( '1' );
		expect( container.querySelector( 'input[type="date"]' ).value ).toBe( '2026-10-07' );
		expect( clear().disabled ).toBe( false );
	} );

	it( 'la búsqueda espera a que el usuario deje de escribir', () => {
		input( 'input[type="search"]' ).value = 'ana';
		input( 'input[type="search"]' ).dispatchEvent( new Event( 'input' ) );
		expect( onChange ).not.toHaveBeenCalled();

		vi.advanceTimersByTime( 300 );
		expect( onChange ).toHaveBeenCalledWith( { search: 'ana', type: '', dates: { from: '', to: '' } } );
	} );

	it( 'el tipo y las fechas avisan de inmediato; cada fecha limita a la otra', () => {
		input( 'select' ).value = '1';
		input( 'select' ).dispatchEvent( new Event( 'change' ) );
		const [ from, to ] = container.querySelectorAll( 'input[type="date"]' );
		from.value = '2026-10-01';
		from.dispatchEvent( new Event( 'change' ) );

		expect( onChange ).toHaveBeenLastCalledWith( { search: '', type: '1', dates: { from: '2026-10-01', to: '' } } );
		expect( to.min ).toBe( '2026-10-01' );
		expect( clear().getAttribute( 'aria-label' ) ).toBe( 'Limpiar filtros (2 activos)' );
		expect( container.querySelector( '.ep-filter-bar__count' ).textContent ).toBe( '2' );
	} );

	it( 'limpiar vacía todo, avisa y lleva el foco al primer filtro', () => {
		input( 'select' ).value = '1';
		input( 'select' ).dispatchEvent( new Event( 'change' ) );

		clear().click();

		expect( onChange ).toHaveBeenLastCalledWith( { search: '', type: '', dates: { from: '', to: '' } } );
		expect( input( 'select' ).value ).toBe( '' );
		expect( document.activeElement ).toBe( input( 'input[type="search"]' ) );
		expect( clear().disabled ).toBe( true );
	} );

	it( 'reemplaza las opciones; si la elegida desaparece vuelve a «Todos» y avisa', () => {
		bar.setOptions( 'type', [ { value: 1, label: 'Cumpleaños' }, { value: 2, label: 'Reuniones' } ] );
		expect( [ ...input( 'select' ).options ].map( ( option ) => option.textContent ) ).toEqual( [ 'Todos los tipos', 'Cumpleaños', 'Reuniones' ] );
		expect( onChange ).not.toHaveBeenCalled();

		input( 'select' ).value = '2';
		input( 'select' ).dispatchEvent( new Event( 'change' ) );
		bar.setOptions( 'type', [ { value: 1, label: 'Cumpleaños' } ] );

		expect( input( 'select' ).value ).toBe( '' );
		expect( onChange ).toHaveBeenLastCalledWith( expect.objectContaining( { type: '' } ) );
	} );

	it( 'destroy cancela la búsqueda pendiente y quita la barra', () => {
		input( 'input[type="search"]' ).value = 'ana';
		input( 'input[type="search"]' ).dispatchEvent( new Event( 'input' ) );
		bar.destroy();
		vi.advanceTimersByTime( 300 );

		expect( onChange ).not.toHaveBeenCalled();
		expect( container.querySelector( 'section' ) ).toBeNull();
	} );
} );
