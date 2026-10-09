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

/**
 * Fecha de hoy en Bogotá (`YYYY-MM-DD`), la que usa el servidor para «hoy».
 *
 * @returns {string} Fecha.
 */
export function bogotaToday() {
	const parts = Object.fromEntries( new Intl.DateTimeFormat( 'en-US', { timeZone: 'America/Bogota', year: 'numeric', month: '2-digit', day: '2-digit' } ).formatToParts( new Date() ).map( ( { type, value } ) => [ type, value ] ) );
	return `${ parts.year }-${ parts.month }-${ parts.day }`;
}

/**
 * Nonce de la API REST para la sesión de la página (acción `rest-nonce` de WordPress).
 *
 * @param {import('@playwright/test').Page} page Página con sesión.
 * @returns {Promise<string>} Nonce.
 */
export async function restNonce( page ) {
	const response = await page.request.get( '/wp-admin/admin-ajax.php?action=rest-nonce' );
	expect( response.ok() ).toBe( true );
	return ( await response.text() ).trim();
}

/**
 * Petición a la API REST de WordPress con el nonce.
 *
 * @param {import('@playwright/test').Page} page Página con sesión.
 * @param {string} nonce Nonce.
 * @param {string} method Método.
 * @param {string} path Ruta bajo /wp-json/.
 * @param {Object} [data] Cuerpo JSON.
 * @returns {Promise<Object>} Respuesta JSON.
 */
async function rest( page, nonce, method, path, data ) {
	const response = await page.request.fetch( `/wp-json/${ path }`, { method, headers: { 'X-WP-Nonce': nonce }, data } );
	expect( response.ok(), `${ method } ${ path }: ${ await response.text() }` ).toBe( true );
	return response.json();
}

/**
 * ID de un tipo de evento por su nombre.
 *
 * @param {import('@playwright/test').Page} page Página con sesión.
 * @param {string} nonce Nonce.
 * @param {string} name Nombre.
 * @returns {Promise<number>} ID.
 */
export async function typeId( page, nonce, name ) {
	const { data } = await rest( page, nonce, 'GET', 'eventos/v1/event-types' );
	return data.find( ( type ) => type.name === name ).id;
}

/**
 * Crea un evento por la API y devuelve su ID.
 *
 * @param {import('@playwright/test').Page} page Página con sesión.
 * @param {string} nonce Nonce.
 * @param {Object} event Cuerpo de POST /events.
 * @returns {Promise<number>} ID.
 */
export async function createEvent( page, nonce, event ) {
	return ( await rest( page, nonce, 'POST', 'eventos/v1/events', event ) ).data.id;
}

/**
 * Elimina un evento.
 *
 * @param {import('@playwright/test').Page} page Página con sesión.
 * @param {string} nonce Nonce.
 * @param {number} id ID.
 */
export async function deleteEvent( page, nonce, id ) {
	await rest( page, nonce, 'DELETE', `eventos/v1/events/${ id }` );
}

/**
 * Publica una página con un contenido (los shortcodes del plugin).
 *
 * @param {import('@playwright/test').Page} page Página con sesión.
 * @param {string} nonce Nonce.
 * @param {string} title Título.
 * @param {string} content Contenido.
 * @returns {Promise<{ id: number, link: string }>} Página publicada.
 */
export async function createPage( page, nonce, title, content ) {
	const { id, link } = await rest( page, nonce, 'POST', 'wp/v2/pages', { title, content, status: 'publish' } );
	return { id, link };
}

/**
 * Elimina una página definitivamente.
 *
 * @param {import('@playwright/test').Page} page Página con sesión.
 * @param {string} nonce Nonce.
 * @param {number} id ID.
 */
export async function deletePage( page, nonce, id ) {
	await rest( page, nonce, 'DELETE', `wp/v2/pages/${ id }?force=true` );
}

/**
 * Cambia ajustes de WordPress (por ejemplo, `start_of_week`).
 *
 * @param {import('@playwright/test').Page} page Página con sesión.
 * @param {string} nonce Nonce.
 * @param {Object} settings Ajustes.
 */
export async function updateSettings( page, nonce, settings ) {
	await rest( page, nonce, 'POST', 'wp/v2/settings', settings );
}
