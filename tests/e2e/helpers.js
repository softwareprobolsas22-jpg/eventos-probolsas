/**
 * Utilidades comunes de las pruebas de extremo a extremo.
 */
import { expect } from '@playwright/test';

/** Imagen PNG de 1×1 píxeles para subir a la Biblioteca de Medios. */
export const PNG = Buffer.from( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64' );

/**
 * Abre una pantalla del plugin y espera a que termine de cargar.
 *
 * @param {import('@playwright/test').Page} page Página.
 * @param {string} slug Slug de la pantalla (`eventos-probolsas`, `eventos-probolsas-tipos`).
 */
export async function openScreen( page, slug ) {
	await page.goto( `/wp-admin/admin.php?page=${ slug }` );
	await expect( page.locator( '.ep-mount[aria-busy]' ) ).toHaveCount( 0 );
}

/**
 * Panel lateral abierto.
 *
 * @param {import('@playwright/test').Page} page Página.
 * @returns {import('@playwright/test').Locator} Panel.
 */
export const drawer = ( page ) => page.locator( 'dialog.ep-drawer[open]' );

/**
 * Toast con un texto.
 *
 * @param {import('@playwright/test').Page} page Página.
 * @param {string|RegExp} text Texto.
 * @returns {import('@playwright/test').Locator} Toast.
 */
export const toastWith = ( page, text ) => page.locator( '.notyf__toast' ).filter( { hasText: text } );
