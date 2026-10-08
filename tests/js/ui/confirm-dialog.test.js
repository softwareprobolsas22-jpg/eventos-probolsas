// @vitest-environment happy-dom
import { beforeEach, describe, expect, it } from 'vitest';
import { confirmDialog } from '../../../assets/src/js/ui/confirm-dialog.js';

let opener;

const dialog = () => document.querySelector( 'dialog.ep-dialog' );
const buttonByText = ( text ) => [ ...dialog().querySelectorAll( 'button' ) ].find( ( button ) => button.textContent === text );

beforeEach( () => {
	document.body.innerHTML = '';
	opener = document.createElement( 'button' );
	document.body.append( opener );
	opener.focus();
} );

function open() {
	return confirmDialog( { title: '¿Eliminar «Formatos»?', message: 'No se puede deshacer.', confirmLabel: 'Eliminar' } );
}

describe( 'confirmDialog (CP-1.30)', () => {
	it( 'se abre como modal accesible con el foco en Cancelar', () => {
		open();

		expect( dialog().open ).toBe( true );
		expect( document.getElementById( dialog().getAttribute( 'aria-labelledby' ) ).textContent ).toBe( '¿Eliminar «Formatos»?' );
		expect( document.getElementById( dialog().getAttribute( 'aria-describedby' ) ).textContent ).toBe( 'No se puede deshacer.' );
		expect( document.activeElement.textContent ).toBe( 'Cancelar' );
	} );

	it( 'resuelve true al confirmar, se retira y devuelve el foco', async () => {
		const result = open();
		buttonByText( 'Eliminar' ).click();

		await expect( result ).resolves.toBe( true );
		expect( dialog() ).toBeNull();
		expect( document.activeElement ).toBe( opener );
	} );

	it( 'resuelve false al cancelar', async () => {
		const result = open();
		buttonByText( 'Cancelar' ).click();

		await expect( result ).resolves.toBe( false );
	} );

	it( 'Esc cancela', async () => {
		const result = open();
		dialog().dispatchEvent( new Event( 'cancel', { cancelable: true } ) );

		await expect( result ).resolves.toBe( false );
	} );

	it( 'el texto nunca se interpreta como HTML', () => {
		confirmDialog( { title: '<img src=x>', message: '<b>x</b>' } );

		expect( dialog().querySelector( 'img, b' ) ).toBeNull();
	} );
} );
