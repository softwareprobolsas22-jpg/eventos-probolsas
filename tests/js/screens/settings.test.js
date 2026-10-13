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
async function setup( { saved = false, confirmed = true, failGet = false, failGuides = false, failTypes = false, types = TYPES, copied = true, put } = {} ) {
	document.body.innerHTML = `
		<div class="wrap ep-app" data-ep-screen="eventos-probolsas-ajustes">
			<div id="ep-settings" class="ep-mount" aria-busy="true"></div>
		</div>`;
	const screen = document.querySelector( '[data-ep-screen]' );
	const api = {
		get: vi.fn( async ( path ) => {
			if ( ( 'settings' === path && failGet ) || ( 'settings/shortcodes' === path && failGuides ) || ( 'event-types' === path && failTypes ) ) {
				throw new ApiError( 'x' );
			}
			return { settings: { delete_data_on_uninstall: saved }, 'settings/shortcodes': GUIDES, 'event-types': types }[ path ];
		} ),
		put: vi.fn( put ?? ( async ( path, body ) => body ) ),
	};
	const confirm = vi.fn( async () => confirmed );
	const copy = vi.fn( async () => copied );

	await mount( screen, config, { api, confirm, copy } );

	return { screen, api, confirm, copy, toggle: screen.querySelector( 'input[role="switch"]' ), save: screen.querySelector( 'button[type="submit"]' ) };
}

const GUIDES = [
	{ tag: 'eventos_calendario', title: 'Calendario de eventos', description: 'Calendario mensual.', example: '[eventos_calendario]', attributes: [ { name: 'tipos', description: 'Muestra solo estos tipos.', required: false, default: 'Todos', values: 'event_types' } ] },
	{ tag: 'eventos_proximos', title: 'Próximos eventos', description: 'Lista de los eventos que vienen.', example: '[eventos_proximos limite="5"]', attributes: [ { name: 'limite', description: 'Cuántos eventos mostrar.', required: false, default: '5', values: null }, { name: 'titulo', description: 'Título sobre la lista.', required: false, default: '', values: null } ] },
];

const TYPES = [
	{ id: 1, slug: 'cumpleanos', name: 'Cumpleaños' },
	{ id: 2, slug: 'capacitaciones', name: 'Capacitaciones' },
];

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
		expect( screen.querySelectorAll( '.ep-shortcode' ) ).toHaveLength( 2 );
	} );
} );

describe( 'sección Shortcodes de Ajustes (H-406)', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'pide la guía y los tipos sin avisar errores (cada sección muestra el suyo)', async () => {
		const { api } = await setup();

		expect( api.get ).toHaveBeenCalledWith( 'settings' );
		expect( api.get ).toHaveBeenCalledWith( 'settings/shortcodes', { silent: true } );
		expect( api.get ).toHaveBeenCalledWith( 'event-types', { silent: true } );
	} );

	it( 'muestra cada shortcode con título, descripción, ejemplo y atributos', async () => {
		const { screen } = await setup();

		const section = screen.querySelector( '.ep-shortcodes' );
		expect( document.getElementById( section.getAttribute( 'aria-labelledby' ) ).textContent ).toBe( 'Shortcodes' );

		const [ calendar, upcoming ] = section.querySelectorAll( '.ep-shortcode' );
		expect( calendar.querySelector( 'h3' ).textContent ).toBe( 'Calendario de eventos' );
		expect( calendar.querySelector( '.ep-shortcode__description' ).textContent ).toBe( 'Calendario mensual.' );
		expect( upcoming.querySelector( '.ep-shortcode__code' ).textContent ).toBe( '[eventos_proximos limite="5"]' );
		expect( [ ...upcoming.querySelectorAll( 'dt code' ) ].map( ( node ) => node.textContent ) ).toEqual( [ 'limite', 'titulo' ] );
		expect( upcoming.querySelector( 'dt' ).textContent ).toContain( 'opcional' );
		expect( upcoming.querySelector( '.ep-shortcode__default' ).textContent ).toBe( 'Por defecto: 5' );
		expect( upcoming.querySelectorAll( '.ep-shortcode__default' ) ).toHaveLength( 1 );
	} );

	it( 'el atributo tipos lista los slugs de los tipos con su nombre', async () => {
		const { screen } = await setup();

		const items = [ ...screen.querySelectorAll( '.ep-shortcode__values li' ) ];
		expect( items.map( ( item ) => item.querySelector( 'code' ).textContent ) ).toEqual( [ 'cumpleanos', 'capacitaciones' ] );
		expect( items[ 0 ].textContent ).toContain( 'Cumpleaños' );
	} );

	it( 'sin tipos, o si no cargaron, lo explica en lugar de la lista', async () => {
		let { screen } = await setup( { types: [] } );
		expect( screen.querySelector( '.ep-shortcode__values-note' ).textContent ).toBe( 'Aún no hay tipos de evento.' );

		( { screen } = await setup( { failTypes: true } ) );
		expect( screen.querySelector( '.ep-shortcode__values' ) ).toBeNull();
		expect( screen.querySelector( '.ep-shortcode__values-note' ).textContent ).toContain( '«Tipos de evento»' );
	} );

	it( 'copiar el ejemplo usa el portapapeles y lo confirma', async () => {
		const { screen, copy } = await setup();

		const copyButton = screen.querySelectorAll( '.ep-shortcode__example button' )[ 1 ];
		expect( copyButton.getAttribute( 'aria-label' ) ).toBe( 'Copiar el shortcode «Próximos eventos»' );
		copyButton.click();
		await flush();

		expect( copy ).toHaveBeenCalledWith( '[eventos_proximos limite="5"]' );
		expect( document.querySelector( '.notyf' ).textContent ).toContain( 'Shortcode copiado' );
	} );

	it( 'si no se puede copiar, selecciona el shortcode y lo indica', async () => {
		const { screen } = await setup( { copied: false } );

		screen.querySelector( '.ep-shortcode__example button' ).click();
		await flush();

		expect( window.getSelection().toString() ).toBe( '[eventos_calendario]' );
		expect( document.querySelector( '.notyf' ).textContent ).toContain( 'cópialo con Ctrl+C' );
	} );

	it( 'si la guía no carga, los ajustes de desinstalación se muestran igual', async () => {
		const { screen, toggle } = await setup( { failGuides: true } );

		expect( toggle ).not.toBeNull();
		expect( screen.querySelector( '.ep-empty-state' ).textContent ).toContain( 'No se pudo cargar la guía de los shortcodes' );
	} );
} );
