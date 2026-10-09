// @vitest-environment happy-dom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from '../../../assets/src/js/core/api.js';
import { mount } from '../../../assets/src/js/screens/settings.js';
import { SCREENS } from '../../../assets/src/js/pages/admin.js';

const config = { restUrl: '/wp-json/eventos/v1/', restNonce: 'n', ui: {} };

/**
 * Monta la pantalla con una API y una confirmación simuladas.
 *
 * @param {Object} [options] Opciones.
 * @returns {Promise<Object>} Pantalla, API y confirmación.
 */
async function setup( { saved = false, confirmed = true, failGet = false, put } = {} ) {
	document.body.innerHTML = `
		<div class="wrap ep-app" data-ep-screen="eventos-probolsas-ajustes">
			<div id="ep-settings" class="ep-mount" aria-busy="true"></div>
		</div>`;
	const screen = document.querySelector( '[data-ep-screen]' );
	const api = {
		get: vi.fn( async () => {
			if ( failGet ) {
				throw new ApiError( 'x' );
			}
			return { delete_data_on_uninstall: saved };
		} ),
		put: vi.fn( put ?? ( async ( path, body ) => body ) ),
	};
	const confirm = vi.fn( async () => confirmed );

	await mount( screen, config, { api, confirm } );

	return { screen, api, confirm, toggle: screen.querySelector( 'input[role="switch"]' ), save: screen.querySelector( 'button[type="submit"]' ) };
}

const flush = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

describe( 'pantalla Ajustes (H-402, D-16)', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'admin.js monta esta pantalla con el slug de SettingsPage', () => {
		expect( Object.keys( SCREENS ) ).toContain( 'eventos-probolsas-ajustes' );
	} );

	it( 'muestra el interruptor con el valor guardado (desactivado por defecto) y su ayuda', async () => {
		const { screen, toggle, save } = await setup();

		expect( screen.querySelector( '#ep-settings' ).hasAttribute( 'aria-busy' ) ).toBe( false );
		expect( toggle.checked ).toBe( false );
		expect( screen.querySelector( `label[for="${ toggle.id }"]` ).textContent ).toBe( 'Borrar todos los datos al desinstalar' );
		expect( document.getElementById( toggle.getAttribute( 'aria-describedby' ).split( ' ' )[ 0 ] ).textContent ).toContain( 'Los archivos de la Biblioteca de Medios nunca se borran.' );
		expect( save.disabled ).toBe( true );
	} );

	it( 'activar el borrado pide confirmación y guarda', async () => {
		const { toggle, save, api, confirm } = await setup();

		toggle.click();
		expect( save.disabled ).toBe( false );
		save.click();
		await flush();

		expect( confirm ).toHaveBeenCalledWith( expect.objectContaining( { title: '¿Borrar los datos al desinstalar?' } ) );
		expect( api.put ).toHaveBeenCalledWith( 'settings', { delete_data_on_uninstall: true }, { silent: true } );
		expect( document.querySelector( '.notyf' ).textContent ).toContain( 'al desinstalar se borrarán los datos' );
		expect( save.disabled ).toBe( true );
	} );

	it( 'si no confirma, no guarda', async () => {
		const { toggle, save, api } = await setup( { confirmed: false } );

		toggle.click();
		save.click();
		await flush();

		expect( api.put ).not.toHaveBeenCalled();
	} );

	it( 'desactivarlo guarda sin preguntar', async () => {
		const { toggle, save, api, confirm } = await setup( { saved: true } );

		expect( toggle.checked ).toBe( true );
		toggle.click();
		save.click();
		await flush();

		expect( confirm ).not.toHaveBeenCalled();
		expect( api.put ).toHaveBeenCalledWith( 'settings', { delete_data_on_uninstall: false }, { silent: true } );
	} );

	it( 'un error de la API se muestra en un toast y bajo el campo', async () => {
		const { toggle, save, screen } = await setup( {
			put: async () => {
				throw new ApiError( 'Revisa los campos marcados.', { status: 422, fieldErrors: { delete_data_on_uninstall: [ 'El campo debe ser sí o no.' ] } } );
			},
		} );

		toggle.click();
		save.click();
		await flush();

		expect( screen.querySelector( '.ep-field__error' ).hidden ).toBe( false );
		expect( screen.querySelector( '.ep-field__error' ).textContent ).toBe( 'El campo debe ser sí o no.' );
		expect( save.disabled ).toBe( false );
	} );

	it( 'si no carga, muestra el error de carga', async () => {
		const { screen } = await setup( { failGet: true } );

		expect( screen.querySelector( '.ep-empty-state' ).textContent ).toContain( 'No se pudo cargar' );
	} );
} );
