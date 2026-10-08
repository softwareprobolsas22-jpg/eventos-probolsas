/**
 * «Tipos de evento» en WordPress real (H-207).
 */
import { expect, test } from '@playwright/test';
import { drawer, openScreen, toastWith } from './helpers.js';

test.describe( 'Tipos de evento', () => {
	test( 'carga la pantalla con los tipos iniciales y descarga admin.js una sola vez (QA-038)', async ( { page } ) => {
		await openScreen( page, 'eventos-probolsas-tipos' );

		await expect( page.locator( 'tbody .ep-badge' ).first() ).toBeVisible();
		await expect( page.locator( 'tbody .ep-badge', { hasText: 'Cumpleaños' } ) ).toBeVisible();

		const loads = await page.evaluate( () => performance.getEntriesByType( 'resource' ).filter( ( entry ) => /\/assets\/dist\/js\/admin\.js/.test( entry.name ) ).length );
		expect( loads ).toBe( 1 );
		await expect( page.locator( '.ep-data-table' ) ).toHaveCount( 1 );
	} );

	test( 'dentro del panel se ven los tooltips y los toasts (QA-024), y el nombre repetido se rechaza', async ( { page } ) => {
		await openScreen( page, 'eventos-probolsas-tipos' );
		await page.getByRole( 'button', { name: 'Añadir tipo' } ).click();
		const panel = drawer( page );
		await expect( panel ).toBeVisible();

		await panel.getByRole( 'button', { name: 'Cerrar' } ).hover();
		await expect( panel.locator( '.tippy-box', { hasText: 'Cerrar' } ) ).toBeVisible();

		await panel.getByLabel( 'Nombre' ).fill( 'CUMPLEAÑOS' );
		await panel.getByRole( 'radio', { name: 'Pastel' } ).click();
		await panel.getByRole( 'button', { name: 'Crear tipo' } ).click();

		await expect( panel.getByText( 'Ya existe un tipo de evento llamado «CUMPLEAÑOS».' ) ).toBeVisible();
		const toast = toastWith( page, 'Revisa los campos marcados.' );
		await expect( toast ).toBeVisible();
		// El toast vive dentro del panel abierto (top layer): si no, quedaría tapado e inerte.
		await expect( panel.locator( '.notyf__toast' ) ).toHaveCount( 1 );
	} );
} );
