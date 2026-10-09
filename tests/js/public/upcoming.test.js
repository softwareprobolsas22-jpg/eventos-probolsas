// @vitest-environment happy-dom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createDateFormatter } from '../../../assets/src/js/core/date.js';
import { createUpcoming } from '../../../assets/src/js/public/upcoming.js';

const UI = { timezone: 'America/Bogota', locale: 'es-CO', date_format: 'd/m/Y', time_format: 'h:i', meridiem: { am: 'a. m.', pm: 'p. m.' } };
const CONFIG = { today: '2026-10-07', ui: UI };
const TYPE = { id: 1, name: 'Cumpleaños', color: '#9D174D', text_tone: 'light', icon: 'cake-candles' };

const EVENTS = [
	{ id: 7, title: 'Cumpleaños de Ana', type: TYPE, start_date: '2026-10-07', start_time: null },
	{ id: 9, title: 'Comité de calidad', type: TYPE, start_date: '2026-10-20', start_time: '15:00' },
];

/**
 * Monta el widget con una API simulada.
 *
 * @param {Object} [options] Opciones.
 * @returns {Promise<Object>} Contenedor, API y apertura del modal.
 */
async function setup( { props = { limit: 5, types: [], title: 'Próximos eventos' }, response = EVENTS, fail = false, now = '2026-10-08T01:30:00Z' } = {} ) {
	const element = document.createElement( 'div' );
	element.className = 'ep-public';
	element.setAttribute( 'aria-busy', 'true' );
	document.body.append( element );
	const api = { get: vi.fn( () => ( fail ? Promise.reject( new Error( 'Sin conexión' ) ) : Promise.resolve( response ) ) ) };
	const onOpen = vi.fn();

	await createUpcoming( element, props, { config: CONFIG, dates: createDateFormatter( UI ) }, { api, onOpen, clock: () => new Date( now ) } );

	return { element, api, onOpen };
}

describe( 'próximos eventos (H-304)', () => {
	afterEach( () => {
		document.body.replaceChildren();
	} );

	it( 'pide los próximos con el límite y los tipos del shortcode', async () => {
		const { api } = await setup( { props: { limit: 3, types: [ 1, 4 ], title: '' } } );

		expect( api.get ).toHaveBeenCalledWith( 'upcoming?limit=3&types%5B%5D=1&types%5B%5D=4' );
	} );

	it( 'muestra fecha, hora es-CO, tipo y marca «Hoy» con el día de Bogotá (R-07, RL-02)', async () => {
		// 2026-10-08 01:30 UTC = 7 de octubre, 8:30 p. m. en Bogotá.
		const { element } = await setup();
		const [ first, second ] = element.querySelectorAll( '.ep-upcoming__event' );

		expect( element.querySelector( 'h2' ).textContent ).toBe( 'Próximos eventos' );
		expect( element.querySelector( 'section' ).getAttribute( 'aria-labelledby' ) ).toBe( element.querySelector( 'h2' ).id );
		expect( first.querySelector( '.ep-upcoming__date' ).classList.contains( 'is-today' ) ).toBe( true );
		expect( first.querySelector( '.ep-upcoming__month' ).textContent ).toBe( 'Hoy' );
		expect( first.querySelector( '.ep-upcoming__time' ).textContent ).toBe( 'Todo el día' );
		expect( second.querySelector( '.ep-upcoming__day' ).textContent ).toBe( '20' );
		expect( second.querySelector( '.ep-upcoming__month' ).textContent ).toBe( 'oct' );
		expect( second.querySelector( '.ep-upcoming__time' ).textContent ).toBe( '03:00 p. m.' );
		expect( second.querySelector( '.ep-visually-hidden' ).textContent ).toBe( 'martes, 20 de octubre de 2026, ' );
		expect( second.querySelector( '.ep-badge' ).textContent ).toBe( 'Cumpleaños' );
		expect( second.querySelector( '.ep-upcoming__name' ).classList.contains( 'ep-truncate' ) ).toBe( true );
		expect( element.querySelector( '[aria-busy]' ) ).toBeNull();
	} );

	it( 'sin título, la sección conserva un nombre accesible', async () => {
		const { element } = await setup( { props: { title: '' } } );

		expect( element.querySelector( 'h2' ) ).toBeNull();
		expect( element.querySelector( 'section' ).getAttribute( 'aria-label' ) ).toBe( 'Próximos eventos' );
	} );

	it( 'cada evento abre el modal de detalle con el foco de regreso', async () => {
		const { element, onOpen } = await setup();
		const button = element.querySelectorAll( '.ep-upcoming__event' )[ 1 ];

		button.click();

		expect( onOpen ).toHaveBeenCalledWith( 9, button );
	} );

	it( 'sin eventos muestra el estado vacío', async () => {
		const { element } = await setup( { response: [] } );

		expect( element.querySelector( '.ep-empty-state' ).textContent ).toContain( 'No hay eventos próximos' );
		expect( element.querySelector( 'ul' ) ).toBeNull();
	} );

	it( 'si la API falla, muestra el error en lugar de quedarse cargando', async () => {
		const { element } = await setup( { fail: true } );

		expect( element.querySelector( '.ep-empty-state' ).textContent ).toContain( 'No se pudieron cargar los próximos eventos' );
		expect( element.querySelector( '[aria-busy]' ) ).toBeNull();
	} );
} );
