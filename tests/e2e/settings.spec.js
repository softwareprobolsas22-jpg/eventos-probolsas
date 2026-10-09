/**
 * «Ajustes» en WordPress real (H-402, D-16): conservar o borrar los datos al desinstalar.
 */
import { expect, test } from '@playwright/test';
import { openScreen, toastWith } from './helpers.js';

test.describe( 'Ajustes', () => {
	test( 'por defecto se conservan los datos; activar el borrado pide confirmación y se guarda', async ( { page } ) => {
		await openScreen( page, 'eventos-probolsas-ajustes' );
		const toggle = page.getByRole( 'switch', { name: 'Borrar todos los datos al desinstalar' } );
		const save = page.getByRole( 'button', { name: 'Guardar cambios' } );

		await expect( toggle ).not.toBeChecked();
		await expect( save ).toBeDisabled();

		try {
			await toggle.check();
			await save.click();
			const dialog = page.locator( 'dialog.ep-dialog' );
			await expect( dialog ).toContainText( '¿Borrar los datos al desinstalar?' );
			await dialog.getByRole( 'button', { name: 'Sí, borrar al desinstalar' } ).click();
			await expect( toastWith( page, 'al desinstalar se borrarán los datos' ) ).toBeVisible();

			await page.reload();
			await expect( page.getByRole( 'switch', { name: 'Borrar todos los datos al desinstalar' } ) ).toBeChecked();
		} finally {
			// Deja el sitio de pruebas como estaba: conservar los datos.
			await openScreen( page, 'eventos-probolsas-ajustes' );
			const current = page.getByRole( 'switch', { name: 'Borrar todos los datos al desinstalar' } );
			if ( await current.isChecked() ) {
				await current.uncheck();
				await page.getByRole( 'button', { name: 'Guardar cambios' } ).click();
				await expect( toastWith( page, 'al desinstalar se conservarán los datos' ) ).toBeVisible();
			}
		}
	} );
} );
