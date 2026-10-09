/**
 * `[eventos_calendario]` en WordPress real (H-302, H-305): FullCalendar cargado bajo demanda por la
 * página pública, sin desfases aunque el navegador esté en otra zona horaria.
 */
import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { bogotaToday, createEvent, createPage, deleteEvent, deletePage, restNonce, typeId, updateSettings } from './helpers.js';

/**
 * Página con la sesión del administrador, para preparar y limpiar los datos (las opciones de `use` no se
 * aplican a los contextos que se crean a mano).
 *
 * @param {import('@playwright/test').Browser} browser Navegador.
 * @param {import('@playwright/test').TestInfo} testInfo Prueba.
 * @returns {Promise<import('@playwright/test').Page>} Página.
 */
async function adminPage( browser, testInfo ) {
	const { baseURL, storageState } = testInfo.project.use;
	const context = await browser.newContext( { baseURL, storageState } );
	return context.newPage();
}

/** Título único por ejecución (los reintentos del CI no chocan entre sí). */
const unique = ( text ) => `${ text } ${ Date.now().toString( 36 ) }`;

test.describe( 'Calendario de la intranet', () => {
	// El equipo del colaborador está en Tokio (UTC+9): 14 horas por delante de Bogotá.
	test.use( { timezoneId: 'Asia/Tokyo' } );

	const today = bogotaToday();
	const created = { events: [], pages: [] };
	let nonce = '';
	let afternoon = '';
	let allDay = '';
	let pageUrl = '';
	let twoCalendarsUrl = '';
	let upcomingUrl = '';

	test.beforeAll( async ( { browser }, testInfo ) => {
		const page = await adminPage( browser, testInfo );
		nonce = await restNonce( page );
		const meetings = await typeId( page, nonce, 'Reuniones laborales' );

		afternoon = unique( 'Comité E2E' );
		allDay = unique( 'Jornada E2E' );
		created.events.push( await createEvent( page, nonce, { title: afternoon, type_id: meetings, start_date: today, start_time: '15:00' } ) );
		created.events.push( await createEvent( page, nonce, { title: allDay, type_id: meetings, start_date: today, start_time: '' } ) );

		const single = await createPage( page, nonce, unique( 'Calendario E2E' ), '[eventos_calendario]' );
		const double = await createPage( page, nonce, unique( 'Dos calendarios E2E' ), '[eventos_calendario]\n\n[eventos_calendario tipos="reuniones-laborales"]' );
		const upcoming = await createPage( page, nonce, unique( 'Próximos E2E' ), '[eventos_proximos limite="20" tipos="reuniones-laborales" titulo="Lo que viene"]' );
		created.pages.push( single.id, double.id, upcoming.id );
		pageUrl = single.link;
		twoCalendarsUrl = double.link;
		upcomingUrl = upcoming.link;
		await page.close();
	} );

	test.afterAll( async ( { browser }, testInfo ) => {
		const page = await adminPage( browser, testInfo );
		for ( const id of created.events ) {
			await deleteEvent( page, nonce, id );
		}
		for ( const id of created.pages ) {
			await deletePage( page, nonce, id );
		}
		await page.close();
	} );

	test( 'el evento aparece en su día y a su hora de Colombia, y «hoy» es el de Bogotá (RL-01, RL-02, R-08)', async ( { page } ) => {
		await page.setViewportSize( { width: 1280, height: 900 } );
		await page.goto( pageUrl );

		const day = page.locator( `.fc-daygrid-day[data-date="${ today }"]` );
		await expect( day.locator( '.fc-event', { hasText: afternoon } ) ).toContainText( '03:00 p. m.' );
		await expect( day.locator( '.fc-event', { hasText: allDay } ) ).toBeVisible();
		await expect( page.locator( '.fc-day-today' ) ).toHaveAttribute( 'data-date', today );
		await expect( page.locator( '.fc-col-header-cell' ).first() ).toHaveText( /lun/i );

		// El número del día abre la lista de ese día con sus eventos (RL-01).
		await day.locator( '.fc-daygrid-day-number' ).click();
		await expect( page.locator( '.fc-list-event', { hasText: afternoon } ) ).toContainText( '03:00 p. m.' );
		await expect( page.locator( '.fc-list-event', { hasText: allDay } ) ).toContainText( 'Todo el día' );
	} );

	test( 'el modal muestra el detalle, pasa entre los eventos del día y descarga el .ics (H-303, RL-03, R-16)', async ( { page } ) => {
		await page.setViewportSize( { width: 1280, height: 900 } );
		await page.goto( pageUrl );
		const [ year, month, dayOfMonth ] = today.split( '-' ).map( Number );
		const longDate = new Intl.DateTimeFormat( 'es-CO', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' } ).format( Date.UTC( year, month - 1, dayOfMonth ) );
		const compact = today.replaceAll( '-', '' );

		// Se abre con el teclado.
		const event = page.locator( `.fc-daygrid-day[data-date="${ today }"] .fc-event`, { hasText: afternoon } );
		await event.focus();
		await page.keyboard.press( 'Enter' );

		const modal = page.locator( 'dialog.ep-event-modal[open]' );
		await expect( modal.locator( '.ep-event-modal__title' ) ).toHaveText( afternoon );
		await expect( modal.locator( '.ep-event-modal__title' ) ).toBeFocused();
		await expect( modal.locator( '.ep-event-modal__date' ) ).toHaveText( longDate );
		await expect( modal.locator( '.ep-event-modal__time' ) ).toHaveText( '03:00 p. m.' );
		await expect( modal.locator( '.ep-badge' ) ).toHaveText( 'Reuniones laborales' );

		// .ics con la hora de Colombia, sin la «Z» del legado (RL-03).
		const [ timed ] = await Promise.all( [ page.waitForEvent( 'download' ), modal.getByRole( 'link', { name: 'Añadir a mi calendario' } ).click() ] );
		const timedIcs = readFileSync( await timed.path(), 'utf8' );
		expect( timedIcs ).toContain( `DTSTART;TZID=America/Bogota:${ compact }T150000\r\n` );
		expect( timedIcs ).not.toContain( `${ compact }T150000Z` );

		// El evento de todo el día va antes (los eventos del día se ordenan por hora).
		await modal.getByRole( 'button', { name: 'Evento anterior del mismo día' } ).click();
		await expect( modal.locator( '.ep-event-modal__title' ) ).toHaveText( allDay );
		await expect( modal.locator( '.ep-event-modal__time' ) ).toHaveText( 'Todo el día' );
		const [ fullDay ] = await Promise.all( [ page.waitForEvent( 'download' ), modal.getByRole( 'link', { name: 'Añadir a mi calendario' } ).click() ] );
		expect( readFileSync( await fullDay.path(), 'utf8' ) ).toContain( `DTSTART;VALUE=DATE:${ compact }\r\n` );

		// Esc cierra y el foco vuelve al evento que lo abrió.
		await page.keyboard.press( 'Escape' );
		await expect( modal ).toHaveCount( 0 );
		await expect( event ).toBeFocused();
	} );

	test( '[eventos_proximos] lista desde hoy en Colombia y abre el mismo modal (H-304)', async ( { page } ) => {
		await page.setViewportSize( { width: 1280, height: 900 } );
		await page.goto( upcomingUrl );

		const widget = page.locator( '.ep-upcoming' );
		await expect( widget.getByRole( 'heading', { name: 'Lo que viene' } ) ).toBeVisible();
		const item = widget.locator( '.ep-upcoming__event', { hasText: afternoon } );
		await expect( item.locator( '.ep-upcoming__month' ) ).toHaveText( 'Hoy' );
		await expect( item.locator( '.ep-upcoming__time' ) ).toHaveText( '03:00 p. m.' );
		await expect( widget.locator( '.ep-upcoming__event', { hasText: allDay } ).locator( '.ep-upcoming__time' ) ).toHaveText( 'Todo el día' );
		await expect( widget.locator( '.ep-badge', { hasText: 'Reuniones laborales' } ).first() ).toBeVisible();

		await item.click();
		const modal = page.locator( 'dialog.ep-event-modal[open]' );
		await expect( modal.locator( '.ep-event-modal__title' ) ).toHaveText( afternoon );
		await page.keyboard.press( 'Escape' );
		await expect( modal ).toHaveCount( 0 );
		await expect( item ).toBeFocused();

		// No carga FullCalendar: solo el chunk de la lista (R-17).
		const scripts = await page.evaluate( () => globalThis.performance.getEntriesByType( 'resource' ).map( ( entry ) => entry.name ).filter( ( name ) => name.includes( '/assets/dist/js/' ) ) );
		expect( scripts.some( ( name ) => /chunks\/calendar-/.test( name ) ) ).toBe( false );
	} );

	test( 'en móvil empieza en la lista y no hay scroll horizontal (R-11, R-13)', async ( { page } ) => {
		await page.setViewportSize( { width: 360, height: 800 } );
		await page.goto( pageUrl );

		await expect( page.locator( '.fc-list-event', { hasText: afternoon } ) ).toBeVisible();
		const overflow = await page.evaluate( () => globalThis.document.documentElement.scrollWidth - globalThis.document.documentElement.clientWidth );
		expect( overflow ).toBeLessThanOrEqual( 0 );
	} );

	test( 'la semana empieza el día que configura WordPress (RL-06)', async ( { page } ) => {
		await page.setViewportSize( { width: 1280, height: 900 } );
		await updateSettings( page, nonce, { start_of_week: 0 } );
		try {
			await page.goto( pageUrl );
			await expect( page.locator( '.fc-col-header-cell' ).first() ).toHaveText( /dom/i );
			await expect( page.locator( `.fc-daygrid-day[data-date="${ today }"] .fc-event`, { hasText: afternoon } ) ).toBeVisible();
		} finally {
			await updateSettings( page, nonce, { start_of_week: 1 } );
		}
	} );

	test( 'dos calendarios en la página: una petición por rango cada uno y sin IDs repetidos (RL-08)', async ( { page } ) => {
		await page.setViewportSize( { width: 1280, height: 900 } );
		const feeds = [];
		page.on( 'request', ( request ) => {
			if ( request.url().includes( '/eventos/v1/calendar' ) ) {
				feeds.push( request.url() );
			}
		} );

		await page.goto( twoCalendarsUrl );
		await expect( page.locator( '.ep-calendar .fc-daygrid' ) ).toHaveCount( 2 );
		await expect( page.locator( '.ep-calendar[aria-busy="true"]' ) ).toHaveCount( 0 );

		expect( feeds ).toHaveLength( 2 );
		expect( feeds.filter( ( url ) => url.includes( 'types' ) ) ).toHaveLength( 1 );
		const duplicated = await page.evaluate( () => {
			const ids = [ ...globalThis.document.querySelectorAll( '[id]' ) ].map( ( element ) => element.id );
			return ids.filter( ( id, index ) => ids.indexOf( id ) !== index );
		} );
		expect( duplicated ).toEqual( [] );
	} );

	test( 'el filtro de tipos vuelve a pedir el rango visible', async ( { page } ) => {
		await page.setViewportSize( { width: 1280, height: 900 } );
		await page.goto( pageUrl );
		await expect( page.locator( '.ts-control' ) ).toBeVisible();

		const [ request ] = await Promise.all( [
			page.waitForRequest( ( item ) => item.url().includes( '/eventos/v1/calendar' ) && item.url().includes( 'types' ) ),
			( async () => {
				await page.locator( '.ts-control' ).click();
				await page.locator( '.ts-dropdown .option', { hasText: 'Reuniones laborales' } ).click();
			} )(),
		] );

		expect( new URL( request.url() ).searchParams.getAll( 'types[]' ) ).toHaveLength( 1 );
		await expect( page.locator( '.ts-control .ep-badge', { hasText: 'Reuniones laborales' } ) ).toBeVisible();
	} );

	test( 'un visitante sin sesión ve el aviso y la página no carga el calendario (D-1, R-17)', async ( { browser } ) => {
		const context = await browser.newContext( { storageState: { cookies: [], origins: [] }, timezoneId: 'Asia/Tokyo' } );
		const page = await context.newPage();

		await page.goto( pageUrl );

		await expect( page.locator( '.ep-public-notice' ) ).toContainText( 'Inicia sesión para ver el calendario de eventos.' );
		await expect( page.getByRole( 'link', { name: 'Iniciar sesión' } ) ).toHaveAttribute( 'href', /wp-login\.php\?redirect_to=/ );
		await expect( page.locator( 'script[src*="/assets/dist/js/public.js"]' ) ).toHaveCount( 0 );
		await expect( page.locator( '.fc' ) ).toHaveCount( 0 );
		await context.close();
	} );
} );
