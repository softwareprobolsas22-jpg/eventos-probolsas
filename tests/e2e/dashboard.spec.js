/**
 * Resumen de «Eventos» en WordPress real (H-206, D-17): cifras con el día de Colombia y tarjetas que
 * filtran la tabla.
 */
import { expect, test } from '@playwright/test';
import { bogotaToday, createEvent, deleteEvent, openScreen, restNonce, typeId } from './helpers.js';

/** Título único por ejecución (los reintentos del CI no chocan entre sí). */
const unique = ( text ) => `${ text } ${ Date.now().toString( 36 ) }`;

test.describe( 'Resumen de eventos', () => {
	// El navegador del gestor en Tokio: «Hoy» sigue siendo el día de Bogotá (R-08).
	test.use( { timezoneId: 'Asia/Tokyo' } );

	test( 'las tarjetas cuentan los eventos de hoy y filtran la tabla', async ( { page } ) => {
		const today = bogotaToday();
		const title = unique( 'Resumen E2E' );
		const nonce = await restNonce( page );
		const id = await createEvent( page, nonce, { title, type_id: await typeId( page, nonce, 'Reuniones laborales' ), start_date: today, start_time: '10:00' } );

		try {
			await openScreen( page, 'eventos-probolsas' );
			const summary = page.locator( '.ep-summary' );
			await expect( summary ).not.toHaveAttribute( 'aria-busy', 'true' );

			const todayCard = summary.getByRole( 'button', { name: /eventos? hoy\. Ver en la tabla\./ } );
			const count = Number( await todayCard.locator( '.ep-summary-card__value' ).textContent() );
			expect( count ).toBeGreaterThanOrEqual( 1 );

			await todayCard.click();
			const [ from, to ] = await page.locator( '.ep-filter-bar input[type="date"]' ).all();
			await expect( from ).toHaveValue( today );
			await expect( to ).toHaveValue( today );
			await expect( page.locator( 'tbody tr', { hasText: title } ) ).toHaveCount( 1 );
			await expect( page.locator( '.ep-table-toolbar__summary' ) ).toContainText( String( count ) );

			// Un tipo filtra por ese tipo y quita el rango.
			await summary.getByRole( 'button', { name: /^Reuniones laborales: / } ).click();
			await expect( from ).toHaveValue( '' );
			await expect( page.getByLabel( 'Tipo', { exact: true } ) ).toHaveValue( String( await typeId( page, nonce, 'Reuniones laborales' ) ) );
		} finally {
			await deleteEvent( page, nonce, id );
		}
	} );
} );
