/**
 * Auditoría de accesibilidad y rendimiento de wp-admin en WordPress real (H-403): axe-core (WCAG 2.2 AA)
 * en cada pantalla y en sus diálogos, y las peticiones que hace cada pantalla al abrirse (R-16, R-17).
 * La intranet (calendario, modal y próximos) la audita tests/e2e/calendar.spec.js.
 */
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';
import { drawer, openScreen } from './helpers.js';

/** axe-core (devDependency), que se inyecta en la página para auditar la accesibilidad. */
const AXE = fileURLToPath( new URL( '../../node_modules/axe-core/axe.min.js', import.meta.url ) );

/**
 * Problemas graves o críticos de axe en elementos del plugin (wp-admin y su barra no son parte de la
 * revisión). Espera a que terminen las animaciones: axe mide el contraste con la opacidad del momento.
 *
 * @param {import('@playwright/test').Page} page Página.
 * @returns {Promise<string[]>} Problemas.
 */
async function audit( page ) {
	await page.waitForFunction( () => globalThis.document.getAnimations().every( ( animation ) => 'running' !== animation.playState ) );
	await page.addScriptTag( { path: AXE } );
	const violations = await page.evaluate( async () => {
		const result = await globalThis.axe.run( globalThis.document, { runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa' ] } } );
		return result.violations.filter( ( item ) => [ 'serious', 'critical' ].includes( item.impact ) ).map( ( item ) => `${ item.id }: ${ item.nodes.map( ( node ) => node.target.join( ' ' ) ).join( ', ' ) }` );
	} );
	return violations.filter( ( text ) => /ep-|\.notyf|tippy/.test( text ) );
}

test.describe( 'Auditoría de wp-admin (H-403)', () => {
	test.use( { reducedMotion: 'reduce' } );

	test( 'axe sin problemas graves en «Eventos», sus tarjetas, el formulario, el detalle y la confirmación', async ( { page } ) => {
		await openScreen( page, 'eventos-probolsas' );
		await expect( page.locator( '.ep-summary' ) ).not.toHaveAttribute( 'aria-busy', 'true' );
		expect( await audit( page ), 'pantalla' ).toEqual( [] );

		await page.getByRole( 'button', { name: 'Añadir evento' } ).click();
		await expect( drawer( page ) ).toBeVisible();
		expect( await audit( page ), 'formulario' ).toEqual( [] );
		await page.keyboard.press( 'Escape' );
		await expect( drawer( page ) ).toHaveCount( 0 );

		const row = page.locator( 'tbody tr' ).first();
		if ( await row.getByRole( 'button', { name: 'Ver detalle' } ).count() ) {
			await row.getByRole( 'button', { name: 'Ver detalle' } ).click();
			await expect( drawer( page ).getByRole( 'button', { name: 'Editar' } ) ).toBeEnabled();
			expect( await audit( page ), 'detalle' ).toEqual( [] );
			await page.keyboard.press( 'Escape' );

			await row.getByRole( 'button', { name: 'Eliminar' } ).click();
			await expect( page.locator( 'dialog.ep-dialog' ) ).toBeVisible();
			expect( await audit( page ), 'confirmación' ).toEqual( [] );
			await page.locator( 'dialog.ep-dialog' ).getByRole( 'button', { name: 'Cancelar' } ).click();
		}
	} );

	test( 'axe sin problemas graves en «Tipos de evento» y «Ajustes»', async ( { page } ) => {
		await openScreen( page, 'eventos-probolsas-tipos' );
		await expect( page.locator( 'tbody tr' ).first() ).toBeVisible();
		expect( await audit( page ), 'tipos' ).toEqual( [] );

		await page.getByRole( 'button', { name: 'Añadir tipo' } ).click();
		await expect( drawer( page ) ).toBeVisible();
		expect( await audit( page ), 'formulario de tipo' ).toEqual( [] );
		await page.keyboard.press( 'Escape' );

		await openScreen( page, 'eventos-probolsas-ajustes' );
		await expect( page.getByRole( 'switch', { name: 'Borrar todos los datos al desinstalar' } ) ).toBeVisible();
		expect( await audit( page ), 'ajustes' ).toEqual( [] );
	} );

	test( 'cada pantalla pide a la API solo lo que necesita, una vez (R-17)', async ( { page } ) => {
		const calls = [];
		page.on( 'request', ( request ) => {
			const url = new URL( request.url() );
			const route = url.searchParams.get( 'rest_route' ) ?? url.pathname;
			const match = /\/eventos\/v1\/([a-z-]+)/.exec( route );
			if ( match ) {
				calls.push( match[ 1 ] );
			}
		} );

		await openScreen( page, 'eventos-probolsas' );
		await expect( page.locator( '.ep-summary' ) ).not.toHaveAttribute( 'aria-busy', 'true' );
		expect( calls.sort() ).toEqual( [ 'dashboard', 'event-types', 'events' ] );

		calls.length = 0;
		await openScreen( page, 'eventos-probolsas-tipos' );
		expect( calls ).toEqual( [ 'event-types' ] );

		calls.length = 0;
		await openScreen( page, 'eventos-probolsas-ajustes' );
		await expect( page.getByRole( 'switch' ) ).toBeVisible();
		expect( calls ).toEqual( [ 'settings' ] );
	} );
} );
