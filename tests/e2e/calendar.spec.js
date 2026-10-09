/**
 * `[eventos_calendario]` en WordPress real (H-302, H-305): FullCalendar cargado bajo demanda por la
 * página pública, sin desfases aunque el navegador esté en otra zona horaria.
 */
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
		created.pages.push( single.id, double.id );
		pageUrl = single.link;
		twoCalendarsUrl = double.link;
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
