// @vitest-environment happy-dom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { openDrawer } from '../../../assets/src/js/ui/drawer.js';

let opener;
const drawerElement = () => document.querySelector( 'dialog.ep-drawer' );

beforeEach( () => {
	document.body.innerHTML = '';
	opener = document.createElement( 'button' );
	document.body.append( opener );
	opener.focus();
} );

function open( onRequestClose ) {
	const body = document.createElement( 'div' );
	body.append( document.createElement( 'input' ) );
	return openDrawer( { title: 'Nuevo tipo de evento', body, onRequestClose } );
}

describe( 'openDrawer', () => {
	it( 'se abre como modal con título accesible y foco en el primer campo', () => {
		open();

		expect( drawerElement().open ).toBe( true );
		expect( document.getElementById( drawerElement().getAttribute( 'aria-labelledby' ) ).textContent ).toBe( 'Nuevo tipo de evento' );
		expect( document.activeElement.tagName ).toBe( 'INPUT' );
	} );

	it( 'se cierra con el botón Cerrar y devuelve el foco', async () => {
		open();
		drawerElement().querySelector( 'button[aria-label="Cerrar"]' ).click();
		await Promise.resolve();

		expect( drawerElement() ).toBeNull();
		expect( document.activeElement ).toBe( opener );
	} );

	it( 'no se cierra si onRequestClose devuelve false (CP-2.20)', async () => {
		const onRequestClose = vi.fn( async () => false );
		open( onRequestClose );

		drawerElement().dispatchEvent( new Event( 'cancel', { cancelable: true } ) );
		await Promise.resolve();
		await Promise.resolve();

		expect( onRequestClose ).toHaveBeenCalled();
		expect( drawerElement() ).not.toBeNull();
	} );

	it( 'close() cierra sin preguntar', () => {
		const onRequestClose = vi.fn( () => false );
		open( onRequestClose ).close();

		expect( onRequestClose ).not.toHaveBeenCalled();
		expect( drawerElement() ).toBeNull();
	} );

	it( 'al cerrarse devuelve a <body> los toasts que mostraba (QA-024)', () => {
		const drawer = open();
		const toasts = document.createElement( 'div' );
		toasts.setAttribute( 'data-ep-floating', '' );
		drawerElement().append( toasts );

		drawer.close();

		expect( toasts.parentElement ).toBe( document.body );
	} );
} );
