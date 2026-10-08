/**
 * Pruebas de extremo a extremo en un navegador real sobre WordPress (H-207).
 *
 * Corren solo en el CI (decisión del PO, 2026-10-07: el equipo local no puede con Docker), contra el
 * sitio de desarrollo de wp-env (WordPress 7.1.3, http://localhost:8888, usuario admin/password).
 * Cubren lo que las pruebas con happy-dom no pueden: la carga real de los módulos encolados por
 * WordPress (QA-038), los diálogos modales con tooltips y toasts (QA-024) y el selector de la
 * Biblioteca de Medios (QA-041).
 */
import { defineConfig, devices } from '@playwright/test';

export default defineConfig( {
	testDir: 'tests/e2e',
	globalSetup: './tests/e2e/global-setup.js',
	timeout: 60_000,
	expect: { timeout: 10_000 },
	fullyParallel: false,
	workers: 1,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [ [ 'github' ], [ 'list' ], [ 'html', { open: 'never', outputFolder: 'build/playwright-report' } ] ] : 'list',
	outputDir: 'build/playwright-results',
	use: {
		baseURL: process.env.WP_BASE_URL ?? 'http://localhost:8888',
		storageState: 'build/e2e-auth.json',
		locale: 'es-CO',
		timezoneId: 'America/Bogota',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [ { name: 'chromium', use: { ...devices[ 'Desktop Chrome' ] } } ],
} );
