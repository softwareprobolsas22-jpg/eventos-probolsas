/**
 * Inicia sesión una vez como administrador y guarda la sesión para todas las pruebas.
 */
import { chromium } from '@playwright/test';

/**
 * @param {import('@playwright/test').FullConfig} config Configuración de Playwright.
 */
export default async function globalSetup( config ) {
	const { baseURL, storageState } = config.projects[ 0 ].use;
	const browser = await chromium.launch();
	const page = await browser.newPage( { baseURL } );

	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', process.env.WP_USERNAME ?? 'admin' );
	await page.fill( '#user_pass', process.env.WP_PASSWORD ?? 'password' );
	await Promise.all( [ page.waitForURL( /wp-admin/ ), page.click( '#wp-submit' ) ] );

	await page.context().storageState( { path: storageState } );
	await browser.close();
}
