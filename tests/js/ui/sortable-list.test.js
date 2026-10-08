// @vitest-environment happy-dom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createSortableList } from '../../../assets/src/js/ui/sortable-list.js';

let container;
let onChange;
let list;

const items = [
	{ id: 1, name: 'Cumpleaños' },
	{ id: 2, name: 'Capacitaciones' },
	{ id: 3, name: 'Reuniones especiales' },
];

const rows = () => [ ...container.querySelectorAll( '.ep-sortable__item' ) ];
const names = () => rows().map( ( row ) => row.querySelector( '.ep-sortable__content' ).textContent );
const button = ( label ) => container.querySelector( `button[aria-label="${ label }"]` );

/** Evento de arrastre (happy-dom no construye DataTransfer). */
function drag( type, target, clientY = 0 ) {
	const event = new Event( type, { bubbles: true, cancelable: true } );
	Object.defineProperty( event, 'dataTransfer', { value: { setData: vi.fn(), effectAllowed: '' } } );
	Object.defineProperty( event, 'clientY', { value: clientY } );
	target.dispatchEvent( event );
	return event;
}

beforeEach( () => {
	document.body.innerHTML = '';
	container = document.createElement( 'div' );
	document.body.append( container );
	onChange = vi.fn();
	list = createSortableList( container, { label: 'Tipos de evento en orden', items, onChange } );
} );

describe( 'createSortableList', () => {
	it( 'muestra la lista numerada, con nombre accesible y los extremos sin flecha posible', () => {
		expect( container.querySelector( 'ol' ).getAttribute( 'aria-label' ) ).toBe( 'Tipos de evento en orden' );
		expect( names() ).toEqual( [ 'Cumpleaños', 'Capacitaciones', 'Reuniones especiales' ] );
		expect( rows().map( ( row ) => row.querySelector( '.ep-sortable__position' ).textContent ) ).toEqual( [ '1', '2', '3' ] );
		expect( button( 'Subir «Cumpleaños»' ).disabled ).toBe( true );
		expect( button( 'Bajar «Reuniones especiales»' ).disabled ).toBe( true );
		expect( rows().every( ( row ) => row.draggable ) ).toBe( true );
		expect( list.isDirty() ).toBe( false );
	} );

	it( 'sube y baja con los botones, anuncia la nueva posición y deja el foco en la fila (teclado y táctil)', () => {
		button( 'Bajar «Cumpleaños»' ).click();

		expect( names() ).toEqual( [ 'Capacitaciones', 'Cumpleaños', 'Reuniones especiales' ] );
		expect( list.getOrder() ).toEqual( [ 2, 1, 3 ] );
		expect( onChange ).toHaveBeenLastCalledWith( [ 2, 1, 3 ] );
		expect( container.querySelector( '[role="status"]' ).textContent ).toBe( '«Cumpleaños» ahora está en la posición 2 de 3.' );
		expect( document.activeElement ).toBe( button( 'Bajar «Cumpleaños»' ) );
		expect( list.isDirty() ).toBe( true );

		button( 'Bajar «Cumpleaños»' ).click();
		expect( list.getOrder() ).toEqual( [ 2, 3, 1 ] );
		// En el extremo inferior el foco pasa al botón que sigue activo.
		expect( document.activeElement ).toBe( button( 'Subir «Cumpleaños»' ) );

		button( 'Subir «Cumpleaños»' ).click();
		button( 'Subir «Cumpleaños»' ).click();
		expect( list.getOrder() ).toEqual( [ 1, 2, 3 ] );
		expect( list.isDirty() ).toBe( false );
	} );

	it( 'se reordena arrastrando una fila sobre otra con el mouse', () => {
		const [ first, , third ] = rows();
		third.getBoundingClientRect = () => ( { top: 100, height: 40 } );

		drag( 'dragstart', first );
		expect( first.classList.contains( 'is-dragging' ) ).toBe( true );

		const over = drag( 'dragover', third, 130 );
		expect( over.defaultPrevented ).toBe( true );
		expect( names() ).toEqual( [ 'Capacitaciones', 'Reuniones especiales', 'Cumpleaños' ] );
		expect( onChange ).not.toHaveBeenCalled();

		drag( 'dragend', first );
		expect( first.classList.contains( 'is-dragging' ) ).toBe( false );
		expect( onChange ).toHaveBeenCalledWith( [ 2, 3, 1 ] );
		expect( container.querySelector( '[role="status"]' ).textContent ).toBe( '«Cumpleaños» ahora está en la posición 3 de 3.' );
	} );
} );
