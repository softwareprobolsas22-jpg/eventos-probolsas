// @vitest-environment happy-dom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createDataTable } from '../../../assets/src/js/ui/data-table.js';

const rows = Array.from( { length: 230 }, ( _, index ) => ( { id: index + 1, code: `EV-${ String( index + 1 ).padStart( 3, '0' ) }`, name: `Evento ${ index + 1 }` } ) );

let container;
let onEdit;

/**
 * Tabla de prueba.
 *
 * @returns {ReturnType<typeof createDataTable>} Tabla.
 */
function createTable() {
	return createDataTable( container, {
		caption: 'Eventos',
		pageSizes: [ 25, 50, 100 ],
		pageSize: 25,
		columns: [
			{ key: 'code', label: 'Código' },
			{ key: 'name', label: 'Nombre', truncate: true },
		],
		actions: ( row ) => [ { icon: 'fa-solid fa-pen', label: 'Editar', onClick: () => onEdit( row ) } ],
	} );
}

const bodyRows = () => container.querySelectorAll( 'tbody tr' );
const range = () => container.querySelector( '.ep-data-table__range' ).textContent;

beforeEach( () => {
	document.body.innerHTML = '';
	container = document.createElement( 'div' );
	document.body.append( container );
	onEdit = vi.fn();
} );

describe( 'createDataTable', () => {
	it( 'muestra 25 registros por página y el rango (CP-1.18)', () => {
		createTable().setRows( rows );

		expect( bodyRows() ).toHaveLength( 25 );
		expect( range() ).toBe( 'Mostrando 1–25 de 230' );
	} );

	it( 'la columna de acciones es la primera, con nombre accesible y tooltip (CP-1.21)', () => {
		createTable().setRows( rows );

		const headers = [ ...container.querySelectorAll( 'thead th' ) ].map( ( th ) => th.textContent );
		expect( headers ).toEqual( [ 'Acciones', 'Código', 'Nombre' ] );

		const action = bodyRows()[ 0 ].querySelector( 'td:first-child button' );
		expect( action.getAttribute( 'aria-label' ) ).toBe( 'Editar' );
		expect( action.getAttribute( 'data-ep-tooltip' ) ).toBe( 'Editar' );

		action.click();
		expect( onEdit ).toHaveBeenCalledWith( rows[ 0 ] );
	} );

	it( 'cambia el tamaño de página y vuelve a la primera (CP-1.19)', () => {
		createTable().setRows( rows );
		// Desde la página 1 la paginación muestra «1, 2, …, 10»: se avanza con «Página siguiente».
		container.querySelector( 'button[aria-label="Página siguiente"]' ).click();
		container.querySelector( 'button[aria-label="Página siguiente"]' ).click();
		expect( range() ).toBe( 'Mostrando 51–75 de 230' );

		const select = container.querySelector( '.ep-data-table__page-size select' );
		select.value = '100';
		select.dispatchEvent( new Event( 'change' ) );

		expect( bodyRows() ).toHaveLength( 100 );
		expect( range() ).toBe( 'Mostrando 1–100 de 230' );
	} );

	it( 'navega con los botones y marca la página actual', () => {
		createTable().setRows( rows );

		container.querySelector( 'button[aria-label="Última página"]' ).click();
		expect( range() ).toBe( 'Mostrando 226–230 de 230' );
		expect( container.querySelector( '[aria-current="page"]' ).textContent ).toBe( '10' );
		expect( container.querySelector( 'button[aria-label="Página siguiente"]' ).disabled ).toBe( true );
	} );

	it( 'marca primera y última página para ocultarlas en móvil y muestra «Página x de y» (R-23)', () => {
		createTable().setRows( rows );

		const edges = [ ...container.querySelectorAll( '[data-ep-page-edge]' ) ].map( ( button ) => button.getAttribute( 'aria-label' ) );
		expect( edges ).toEqual( [ 'Primera página', 'Última página' ] );
		expect( container.querySelector( '.ep-pagination__compact' ).textContent ).toBe( 'Página 1 de 10' );
	} );

	it( 'filtra sin perder datos y vuelve a la primera página', () => {
		const table = createTable();
		table.setRows( rows );
		container.querySelector( 'button[aria-label="Página 2"]' ).click();

		table.setFilter( ( row ) => row.id <= 30 );
		expect( range() ).toBe( 'Mostrando 1–25 de 30' );

		table.setFilter( null );
		expect( range() ).toBe( 'Mostrando 1–25 de 230' );
	} );

	it( 'distingue «sin registros» de «sin resultados» (CP-1.27)', () => {
		const table = createTable();
		table.setRows( [] );
		expect( container.querySelector( '.ep-empty-state__title' ).textContent ).toBe( 'Todavía no hay registros' );

		table.setRows( rows );
		table.setFilter( () => false );
		expect( container.querySelector( '.ep-empty-state__title' ).textContent ).toBe( 'Ningún registro coincide con los filtros' );
		expect( container.querySelector( '.ep-data-table__footer' ).hidden ).toBe( true );
	} );

	it( 'muestra filas skeleton mientras carga (CP-1.28)', () => {
		const table = createTable();
		table.setLoading( true );

		expect( container.querySelectorAll( '.ep-skeleton' ).length ).toBeGreaterThan( 0 );
		expect( container.querySelector( 'table' ).getAttribute( 'aria-busy' ) ).toBe( 'true' );

		table.setRows( rows );
		expect( container.querySelector( '.ep-skeleton' ) ).toBeNull();
	} );

	it( 'marca los textos truncables y muestra «—» si no hay valor', () => {
		createTable().setRows( [ { id: 1, code: 'EV-001', name: null }, { id: 2, code: 'EV-002', name: 'Reunión de planeación anual' } ] );

		expect( bodyRows()[ 0 ].querySelector( 'td:last-child' ).textContent ).toBe( '—' );
		expect( bodyRows()[ 1 ].querySelector( '.ep-truncate' ).textContent ).toBe( 'Reunión de planeación anual' );
	} );

	it( 'el contenido de las celdas nunca se interpreta como HTML', () => {
		createTable().setRows( [ { id: 1, code: '<b>X</b>', name: '<img src=x>' } ] );
		expect( container.querySelector( 'tbody img, tbody b' ) ).toBeNull();
	} );
} );
