// @vitest-environment happy-dom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createDateFormatter } from '../../../assets/src/js/core/date.js';
import { icsUrl, openEventModal } from '../../../assets/src/js/public/event-modal.js';

const UI = { timezone: 'America/Bogota', locale: 'es-CO', date_format: 'd/m/Y', time_format: 'h:i', meridiem: { am: 'a. m.', pm: 'p. m.' } };
const CONFIG = { restUrl: '/wp-json/eventos/v1/', restNonce: 'abc 123', ui: UI };
const dates = createDateFormatter( UI );

const TYPE = { id: 1, name: 'Cumpleaños', slug: 'cumpleanos', color: '#9D174D', text_tone: 'light', icon: 'cake-candles' };
const SAME_DAY = [
	{ id: 7, title: 'Cumpleaños de Ana', start_time: null },
	{ id: 8, title: 'Inducción', start_time: '08:00' },
	{ id: 9, title: 'Comité', start_time: '15:00' },
];

/**
 * Evento como lo entrega `GET /events/{id}`.
 *
 * @param {Object} changes Cambios.
 * @returns {Object} Evento.
 */
function detail( changes = {} ) {
	return {
		id: 8,
		title: 'Inducción de personal nuevo',
		description: 'Primera línea.\nSegunda línea.',
		type: TYPE,
		start_date: '2026-10-07',
		start_time: '08:00',
		attachment: null,
		same_day: SAME_DAY,
		...changes,
	};
}

/**
 * Abre el modal con una API simulada.
 *
 * @param {Record<number, Object|Error>} events Respuestas por ID.
 * @param {number} [id] Evento inicial.
 * @returns {Promise<Object>} Modal, API y elemento que lo abrió.
 */
async function open( events, id = 8 ) {
	const opener = document.createElement( 'button' );
	document.body.append( opener );
	const api = {
		get: vi.fn( ( path ) => {
			const response = events[ Number( path.replace( 'events/', '' ) ) ];
			return response instanceof Error ? Promise.reject( response ) : Promise.resolve( response );
		} ),
	};
	const modal = openEventModal( { id, api, config: CONFIG, dates, opener } );
	await modal.ready;
	return { modal, api, opener, dialog: modal.element };
}

describe( 'modal de detalle del evento (H-303)', () => {
	afterEach( () => {
		document.body.replaceChildren();
	} );

	it( 'muestra tipo, fecha larga y hora es-CO y la descripción con saltos de línea (R-07)', async () => {
		const { dialog, api } = await open( { 8: detail() } );

		expect( api.get ).toHaveBeenCalledWith( 'events/8', { silent: true } );
		expect( dialog.open ).toBe( true );
		expect( dialog.classList.contains( 'ep-public' ) ).toBe( true );
		expect( dialog.querySelector( '.ep-event-modal__title' ).textContent ).toBe( 'Inducción de personal nuevo' );
		expect( dialog.getAttribute( 'aria-labelledby' ) ).toBe( dialog.querySelector( '.ep-event-modal__title' ).id );
		expect( dialog.querySelector( '.ep-badge' ).textContent ).toBe( 'Cumpleaños' );
		expect( dialog.querySelector( '.ep-event-modal__date' ).textContent ).toBe( 'miércoles, 7 de octubre de 2026' );
		expect( dialog.querySelector( '.ep-event-modal__time' ).textContent ).toBe( '08:00 a. m.' );
		expect( dialog.querySelector( '.ep-event-modal__description' ).textContent ).toBe( 'Primera línea.\nSegunda línea.' );
		expect( dialog.querySelector( '.ep-event-modal__body' ).hasAttribute( 'aria-busy' ) ).toBe( false );
		expect( document.activeElement ).toBe( dialog.querySelector( '.ep-event-modal__title' ), 'El foco empieza en el título (R-16).' );
	} );

	it( 'un evento sin hora es de todo el día y sin descripción lo dice', async () => {
		const { dialog } = await open( { 8: detail( { start_time: null, description: '' } ) } );

		expect( dialog.querySelector( '.ep-event-modal__time' ).textContent ).toBe( 'Todo el día' );
		expect( dialog.querySelector( '.ep-event-modal__empty' ).textContent ).toBe( 'Este evento no tiene descripción.' );
	} );

	it( 'la imagen se amplía y se reduce dentro del modal', async () => {
		const image = { id: 3, kind: 'image', url: 'https://intranet.test/ana.jpg', title: 'Ana', filename: 'ana.jpg' };
		const { dialog } = await open( { 8: detail( { attachment: image } ) } );
		const zoom = dialog.querySelector( '.ep-event-modal__zoom' );

		expect( dialog.querySelector( 'img' ).getAttribute( 'alt' ) ).toBe( 'Ana' );
		expect( zoom.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		zoom.click();
		expect( zoom.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( zoom.getAttribute( 'aria-label' ) ).toBe( 'Reducir imagen' );
		expect( dialog.querySelector( '.ep-event-modal__figure' ).classList.contains( 'is-expanded' ) ).toBe( true );
		zoom.click();
		expect( zoom.getAttribute( 'aria-label' ) ).toBe( 'Ampliar imagen' );

		const original = dialog.querySelector( '.ep-event-modal__caption a' );
		expect( original.getAttribute( 'href' ) ).toBe( image.url );
		expect( original.getAttribute( 'rel' ) ).toBe( 'noopener noreferrer' );
	} );

	it( 'un PDF se abre en otra pestaña o se descarga', async () => {
		const pdf = { id: 4, kind: 'pdf', url: 'https://intranet.test/programa.pdf', title: 'programa', filename: 'programa.pdf' };
		const { dialog } = await open( { 8: detail( { attachment: pdf } ) } );
		const [ openLink, download ] = dialog.querySelectorAll( '.ep-event-modal__pdf-actions a' );

		expect( dialog.querySelector( '.ep-event-modal__pdf-name' ).textContent ).toBe( 'programa.pdf' );
		expect( openLink.getAttribute( 'target' ) ).toBe( '_blank' );
		expect( openLink.textContent ).toContain( 'Abrir PDF' );
		expect( download.getAttribute( 'download' ) ).toBe( 'programa.pdf' );
		expect( dialog.querySelector( 'img' ) ).toBeNull();
	} );

	it( '«Añadir a mi calendario» descarga el .ics con el nonce en la dirección', async () => {
		const { dialog } = await open( { 8: detail() } );
		const ics = dialog.querySelector( '.ep-event-modal__ics' );

		expect( ics.getAttribute( 'href' ) ).toBe( '/wp-json/eventos/v1/events/8/ics?_wpnonce=abc%20123' );
		expect( ics.hasAttribute( 'download' ) ).toBe( true );
		expect( icsUrl( CONFIG, 42 ) ).toBe( '/wp-json/eventos/v1/events/42/ics?_wpnonce=abc%20123' );
	} );

	it( 'navega entre los eventos del mismo día sin cerrar el modal', async () => {
		const events = { 7: detail( { id: 7, title: 'Cumpleaños de Ana', start_time: null } ), 8: detail(), 9: detail( { id: 9, title: 'Comité', start_time: '15:00' } ) };
		const { dialog, api } = await open( events );
		const position = () => dialog.querySelector( '.ep-event-modal__position' ).textContent;
		const nav = ( which ) => dialog.querySelector( `[data-nav="${ which }"]` );

		expect( position() ).toBe( '2 de 3' );
		expect( dialog.querySelector( '.ep-event-modal__position' ).getAttribute( 'aria-live' ) ).toBe( 'polite' );

		nav( 'next' ).click();
		await vi.waitFor( () => expect( position() ).toBe( '3 de 3' ) );
		expect( dialog.querySelector( '.ep-event-modal__title' ).textContent ).toBe( 'Comité' );
		expect( nav( 'next' ).disabled ).toBe( true );
		expect( document.activeElement ).toBe( nav( 'prev' ), 'El foco no se pierde al llegar al último.' );

		nav( 'prev' ).click();
		await vi.waitFor( () => expect( position() ).toBe( '2 de 3' ) );
		expect( api.get ).toHaveBeenCalledTimes( 2 );
		expect( document.activeElement ).toBe( nav( 'prev' ) );
	} );

	it( 'sin otros eventos ese día no muestra la navegación', async () => {
		const { dialog } = await open( { 8: detail( { same_day: [ { id: 8, title: 'x', start_time: '08:00' } ] } ) } );

		expect( dialog.querySelector( '.ep-event-modal__nav' ) ).toBeNull();
	} );

	it( 'se cierra con el botón, con Esc o al hacer clic fuera, y devuelve el foco (R-16)', async () => {
		for ( const action of [ 'button', 'cancel', 'backdrop' ] ) {
			const { dialog, opener } = await open( { 8: detail() } );

			if ( 'button' === action ) {
				dialog.querySelector( '.ep-icon-button' ).click();
			} else if ( 'cancel' === action ) {
				dialog.dispatchEvent( new Event( 'cancel', { cancelable: true } ) );
			} else {
				dialog.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );
			}

			expect( dialog.isConnected, action ).toBe( false );
			expect( document.activeElement, action ).toBe( opener );
		}
	} );

	it( 'si el evento ya no existe, avisa y cierra', async () => {
		const error = Object.assign( new Error( 'El evento no existe o fue eliminado.' ), { status: 404 } );
		const { dialog } = await open( { 8: error } );

		expect( dialog.isConnected ).toBe( false );
		expect( document.querySelector( '.notyf' )?.textContent ?? '' ).toContain( 'El evento no existe' );
	} );

	it( 'si falla al pasar a otro evento del día, se queda en el actual', async () => {
		const { dialog } = await open( { 8: detail(), 9: new Error( 'Sin conexión' ) } );

		dialog.querySelector( '[data-nav="next"]' ).click();
		await vi.waitFor( () => expect( dialog.querySelector( '.ep-event-modal__body' ).hasAttribute( 'aria-busy' ) ).toBe( false ) );

		expect( dialog.isConnected ).toBe( true );
		expect( dialog.querySelector( '.ep-event-modal__title' ).textContent ).toBe( 'Inducción de personal nuevo' );
	} );
} );
