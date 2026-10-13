// @vitest-environment happy-dom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { copyText } from '../../../assets/src/js/core/clipboard.js';

describe( 'copyText', () => {
	afterEach( () => {
		vi.restoreAllMocks();
		delete document.execCommand;
		document.body.innerHTML = '';
	} );

	it( 'usa navigator.clipboard si está disponible', async () => {
		const clipboard = { writeText: vi.fn( async () => {} ) };

		expect( await copyText( '[eventos_calendario]', { clipboard } ) ).toBe( true );
		expect( clipboard.writeText ).toHaveBeenCalledWith( '[eventos_calendario]' );
	} );

	it( 'si el portapapeles falla o no existe, copia seleccionando un textarea temporal', async () => {
		let copiedValue = null;
		document.execCommand = vi.fn( () => {
			copiedValue = document.querySelector( 'textarea' )?.value;
			return true;
		} );
		const clipboard = { writeText: vi.fn( async () => Promise.reject( new Error( 'denegado' ) ) ) };

		expect( await copyText( '[eventos_proximos]', { clipboard } ) ).toBe( true );
		expect( document.execCommand ).toHaveBeenCalledWith( 'copy' );
		expect( copiedValue ).toBe( '[eventos_proximos]' );
		expect( document.querySelector( 'textarea' ) ).toBeNull();

		expect( await copyText( 'x', { clipboard: null } ) ).toBe( true );
	} );

	it( 'devuelve false si no hay forma de copiar', async () => {
		document.execCommand = vi.fn( () => {
			throw new Error( 'no soportado' );
		} );

		expect( await copyText( 'x', { clipboard: null } ) ).toBe( false );
	} );
} );
