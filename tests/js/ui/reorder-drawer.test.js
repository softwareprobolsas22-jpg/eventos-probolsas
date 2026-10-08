// @vitest-environment happy-dom
import { beforeEach, describe, expect, it, vi } from 'vitest';

const toast = vi.hoisted( () => ( { success: vi.fn(), error: vi.fn() } ) );
const confirm = vi.hoisted( () => ( { answer: true, calls: 0 } ) );

vi.mock( '../../../assets/src/js/ui/toast.js', () => ( { toast } ) );
vi.mock( '../../../assets/src/js/ui/confirm-dialog.js', () => ( {
	confirmDialog: () => {
		confirm.calls++;
		return Promise.resolve( confirm.answer );
	},
} ) );

const { openReorderDrawer } = await import( '../../../assets/src/js/ui/reorder-drawer.js' );

const items = [
	{ id: 1, name: 'Cumpleaños' },
	{ id: 2, name: 'Capacitaciones' },
	{ id: 3, name: 'Reuniones especiales' },
];

const drawerElement = () => document.querySelector( 'dialog.ep-drawer' );
const buttonByText = ( text ) => [ ...drawerElement().querySelectorAll( 'button' ) ].find( ( element ) => element.textContent === text );
const buttonByLabel = ( label ) => drawerElement().querySelector( `button[aria-label="${ label }"]` );
const flush = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

/**
 * Abre el panel de prueba.
 *
 * @param {Function} save Guardado simulado.
 * @returns {{ onSaved: Function }} Callbacks.
 */
function open( save = vi.fn( async ( ids ) => ids.map( ( id ) => ( { id } ) ) ) ) {
	const onSaved = vi.fn();
	openReorderDrawer( { title: 'Ordenar tipos de evento', label: 'Tipos de evento en orden', hint: 'Arrastra o usa las flechas.', items, save, onSaved } );
	return { onSaved, save };
}

beforeEach( () => {
	document.body.innerHTML = '';
	toast.success.mockClear();
	toast.error.mockClear();
	confirm.answer = true;
	confirm.calls = 0;
} );

describe( 'openReorderDrawer', () => {
	it( 'sin cambios, Guardar orden solo cierra el panel', async () => {
		const { save } = open();

		buttonByText( 'Guardar orden' ).click();
		await flush();

		expect( save ).not.toHaveBeenCalled();
		expect( drawerElement() ).toBeNull();
	} );

	it( 'guarda el orden completo, entrega los registros y avisa con un toast', async () => {
		const { save, onSaved } = open();

		buttonByLabel( 'Bajar «Cumpleaños»' ).click();
		buttonByText( 'Guardar orden' ).click();
		await flush();

		expect( save ).toHaveBeenCalledWith( [ 2, 1, 3 ] );
		expect( onSaved ).toHaveBeenCalledWith( [ { id: 2 }, { id: 1 }, { id: 3 } ] );
		expect( toast.success ).toHaveBeenCalledWith( 'Orden guardado.' );
		expect( drawerElement() ).toBeNull();
	} );

	it( 'si falla, muestra el error y deja el panel abierto para reintentar', async () => {
		open( vi.fn( async () => {
			throw new Error( 'No se pudo guardar el orden.' );
		} ) );

		buttonByLabel( 'Bajar «Cumpleaños»' ).click();
		buttonByText( 'Guardar orden' ).click();
		await flush();

		expect( toast.error ).toHaveBeenCalledWith( 'No se pudo guardar el orden.' );
		expect( drawerElement() ).not.toBeNull();
		expect( buttonByText( 'Guardar orden' ).disabled ).toBe( false );
	} );

	it( 'pide confirmación antes de descartar un orden sin guardar', async () => {
		open();
		buttonByLabel( 'Bajar «Cumpleaños»' ).click();

		confirm.answer = false;
		buttonByText( 'Cancelar' ).click();
		await flush();
		expect( confirm.calls ).toBe( 1 );
		expect( drawerElement() ).not.toBeNull();

		confirm.answer = true;
		buttonByText( 'Cancelar' ).click();
		await flush();
		expect( drawerElement() ).toBeNull();
	} );
} );
