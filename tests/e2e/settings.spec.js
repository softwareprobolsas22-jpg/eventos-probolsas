/**
 * «Ajustes» en WordPress real (H-402, D-16): conservar o borrar los datos al desinstalar, y la guía de
 * los shortcodes (H-406).
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
			// Como una persona: clic en la etiqueta (el input mide 1 px y queda bajo la pista del interruptor).
			await page.locator( '.ep-switch__label' ).click();
			await expect( toggle ).toBeChecked();
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
				await page.locator( '.ep-switch__label' ).click();
				await page.getByRole( 'button', { name: 'Guardar cambios' } ).click();
				await expect( toastWith( page, 'al desinstalar se conservarán los datos' ) ).toBeVisible();
			}
		}
	} );

	test( 'la guía de los shortcodes muestra los dos shortcodes, los slugs de los tipos y copia el ejemplo (H-406)', async ( { page, context } ) => {
		await context.grantPermissions( [ 'clipboard-read', 'clipboard-write' ] );
		await openScreen( page, 'eventos-probolsas-ajustes' );
		const section = page.locator( 'section.ep-shortcodes' );

		await expect( section.getByRole( 'heading', { name: 'Shortcodes' } ) ).toBeVisible();
		await expect( section.locator( '.ep-shortcode__code' ) ).toHaveText( [ '[eventos_calendario]', '[eventos_proximos limite="5"]' ] );
		// Los tipos sembrados al activar el plugin aparecen como valores de `tipos`.
		await expect( section.locator( '.ep-shortcode__values code', { hasText: 'cumpleanos' } ).first() ).toBeVisible();

		await section.getByRole( 'button', { name: 'Copiar el shortcode «Próximos eventos»' } ).click();
		await expect( toastWith( page, 'Shortcode copiado' ) ).toBeVisible();
		expect( await page.evaluate( () => navigator.clipboard.readText() ) ).toBe( '[eventos_proximos limite="5"]' );
	} );
} );
