/**
 * «Eventos» en WordPress real (H-207): flujo completo de gestión y el selector de la Biblioteca de
 * Medios dentro del panel (QA-041).
 */
import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { PNG, drawer, openScreen, toastWith } from './helpers.js';

/** Título único por ejecución (los reintentos del CI no chocan entre sí). */
const unique = ( text ) => `${ text } ${ Date.now().toString( 36 ) }`;

test.describe( 'Eventos', () => {
	test( 'crea, busca, edita, exporta y elimina un evento sin adjunto', async ( { page } ) => {
		const title = unique( 'Reunión E2E' );
		await openScreen( page, 'eventos-probolsas' );

		// Crear.
		await page.getByRole( 'button', { name: 'Añadir evento' } ).click();
		const panel = drawer( page );
		// Formulario progresivo (H-410): sin tipo solo se ve ese campo.
		await expect( panel.getByLabel( 'Título' ) ).toBeHidden();
		await panel.getByLabel( 'Tipo' ).selectOption( { label: 'Reuniones laborales' } );
		await expect( panel.getByRole( 'group', { name: 'Adjunto (opcional)' } ) ).toBeVisible();
		await panel.getByLabel( 'Título' ).fill( title );
		await panel.getByLabel( 'Fecha' ).fill( '2026-10-07' );
		await panel.getByLabel( 'Hora' ).fill( '09:30' );
		await panel.getByRole( 'button', { name: 'Crear evento' } ).click();

		await expect( toastWith( page, `Evento «${ title }» creado.` ) ).toBeVisible();
		await expect( panel ).toHaveCount( 0 );

		// Buscar: la fila muestra la fecha y la hora de Colombia sin desfase (R-07, R-08).
		await page.getByLabel( 'Buscar' ).fill( title );
		const row = page.locator( 'tbody tr', { hasText: title } );
		await expect( row ).toHaveCount( 1 );
		await expect( row ).toContainText( '07/10/2026' );
		await expect( row ).toContainText( '09:30 a. m.' );

		// Editar.
		await row.getByRole( 'button', { name: 'Editar' } ).click();
		await drawer( page ).getByLabel( 'Título' ).fill( `${ title } editada` );
		await drawer( page ).getByRole( 'button', { name: 'Guardar cambios' } ).click();
		await expect( toastWith( page, `Cambios guardados en «${ title } editada».` ) ).toBeVisible();

		// Exportar con el filtro activo: el archivo llega como descarga, con BOM y separador «;».
		const [ download ] = await Promise.all( [ page.waitForEvent( 'download' ), page.getByRole( 'button', { name: 'Exportar CSV' } ).click() ] );
		expect( download.suggestedFilename() ).toMatch( /^eventos-\d{4}-\d{2}-\d{2}\.csv$/ );
		const csv = readFileSync( await download.path(), 'utf8' );
		expect( csv.startsWith( '﻿Evento;Tipo;Fecha;Hora;' ) ).toBe( true );
		expect( csv ).toContain( `${ title } editada` );

		// Eliminar.
		const edited = page.locator( 'tbody tr', { hasText: `${ title } editada` } );
		await edited.getByRole( 'button', { name: 'Eliminar' } ).click();
		await page.locator( 'dialog.ep-dialog' ).getByRole( 'button', { name: 'Eliminar' } ).click();
		await expect( toastWith( page, 'eliminado' ) ).toBeVisible();
		await expect( edited ).toHaveCount( 0 );
	} );

	test( 'el detalle muestra lo que no cabe en la tabla y lleva a editar (H-205)', async ( { page } ) => {
		const title = unique( 'Comité E2E' );
		await openScreen( page, 'eventos-probolsas' );

		await page.getByRole( 'button', { name: 'Añadir evento' } ).click();
		const form = drawer( page );
		await form.getByLabel( 'Tipo' ).selectOption( { label: 'Reuniones laborales' } );
		await form.getByLabel( 'Título' ).fill( title );
		await form.getByLabel( 'Fecha' ).fill( '2026-10-08' );
		await form.getByLabel( 'Descripción' ).fill( 'Revisión de indicadores.\nTraer el informe.' );
		await form.getByRole( 'button', { name: 'Crear evento' } ).click();
		await expect( toastWith( page, `Evento «${ title }» creado.` ) ).toBeVisible();

		await page.getByLabel( 'Buscar' ).fill( title );
		await page.locator( 'tbody tr', { hasText: title } ).getByRole( 'button', { name: 'Ver detalle' } ).click();

		const detail = drawer( page );
		await expect( detail.locator( '.ep-drawer__title' ) ).toHaveText( title );
		const list = detail.locator( 'dl' );
		await expect( list ).toContainText( '08/10/2026' );
		await expect( list ).toContainText( 'Todo el día' );
		await expect( list ).toContainText( 'Sin adjunto' );
		await expect( list ).toContainText( 'Traer el informe.' );
		await expect( list.locator( 'dd' ).nth( 5 ) ).toContainText( /, el \d{2}\/\d{2}\/\d{4} \d{2}:\d{2} [ap]\. m\./ );

		await detail.getByRole( 'button', { name: 'Editar' } ).click();
		await expect( drawer( page ).locator( '.ep-drawer__title' ) ).toHaveText( 'Editar evento' );
		await expect( drawer( page ).getByLabel( 'Descripción' ) ).toHaveValue( 'Revisión de indicadores.\nTraer el informe.' );
	} );

	test( 'el adjunto se elige en la Biblioteca de Medios por encima del panel (QA-041)', async ( { page } ) => {
		const title = unique( 'Cumpleaños E2E' );
		await openScreen( page, 'eventos-probolsas' );

		await page.getByRole( 'button', { name: 'Añadir evento' } ).click();
		const panel = drawer( page );
		await panel.getByLabel( 'Tipo' ).selectOption( { label: 'Cumpleaños' } );
		await panel.getByLabel( 'Título' ).fill( title );

		// Cumpleaños exige adjunto: sin él no se envía.
		await panel.getByRole( 'button', { name: 'Crear evento' } ).click();
		await expect( panel.getByText( 'Este tipo de evento requiere una imagen o un PDF.' ) ).toBeVisible();

		// El selector de WordPress queda encima del panel y responde.
		await panel.getByRole( 'button', { name: 'Elegir archivo' } ).click();
		const library = page.locator( '.media-modal' ).last();
		await expect( library ).toBeVisible();
		await library.locator( 'input[type="file"]' ).first().setInputFiles( { name: 'foto-e2e.png', mimeType: 'image/png', buffer: PNG } );
		const choose = library.locator( '.media-button-select' );
		await expect( choose ).toBeEnabled( { timeout: 30_000 } );
		await choose.click();
		await expect( library ).toBeHidden();

		// De vuelta en el panel (otra vez modal): vista previa del archivo elegido.
		await expect( panel.locator( '.ep-media-field__name' ) ).toContainText( 'foto-e2e' );
		await expect( panel.getByText( 'Este tipo de evento requiere una imagen o un PDF.' ) ).toBeHidden();
		await panel.getByRole( 'button', { name: 'Crear evento' } ).click();
		await expect( toastWith( page, `Evento «${ title }» creado.` ) ).toBeVisible();

		await page.getByLabel( 'Buscar' ).fill( title );
		const row = page.locator( 'tbody tr', { hasText: title } );
		await expect( row.locator( '.ep-attachment-kind[data-ep-tooltip="Imagen"]' ) ).toHaveCount( 1 );
	} );

	test( 'soltar un archivo en el adjunto lo sube a la Biblioteca de Medios; lo que no es imagen ni PDF no se guarda (H-411)', async ( { page } ) => {
		const title = unique( 'Cumpleaños soltado E2E' );
		await openScreen( page, 'eventos-probolsas' );
		await page.getByRole( 'button', { name: 'Añadir evento' } ).click();
		const panel = drawer( page );
		await panel.getByLabel( 'Tipo' ).selectOption( { label: 'Cumpleaños' } );
		await panel.getByLabel( 'Título' ).fill( title );
		const zone = panel.locator( '.ep-media-field__dropzone' );

		/**
		 * Suelta un archivo en la zona del adjunto, como al arrastrarlo desde el equipo.
		 *
		 * @param {string} name Nombre.
		 * @param {Buffer} contents Contenido.
		 * @param {string} mime Tipo que informa el navegador.
		 */
		const dropFile = async ( name, contents, mime ) => {
			const dataTransfer = await page.evaluateHandle(
				( file ) => {
					const transfer = new globalThis.DataTransfer();
					const bytes = Uint8Array.from( atob( file.base64 ), ( char ) => char.charCodeAt( 0 ) );
					transfer.items.add( new File( [ bytes ], file.name, { type: file.mime } ) );
					return transfer;
				},
				{ name, base64: contents.toString( 'base64' ), mime }
			);
			await zone.dispatchEvent( 'drop', { dataTransfer } );
		};

		// Un ejecutable que se hace pasar por PDF: el servidor lo rechaza antes de guardarlo (D-4).
		const libraryCount = () =>
			page.evaluate( async () => {
				const { epConfig } = globalThis;
				const url = new URL( epConfig.media.upload_url, globalThis.location.href );
				url.searchParams.set( 'per_page', '1' );
				return ( await fetch( url, { headers: { 'X-WP-Nonce': epConfig.restNonce } } ) ).headers.get( 'X-WP-Total' );
			} );
		const before = await libraryCount();
		await dropFile( 'manual.pdf', Buffer.from( `MZ${ '\0'.repeat( 62 ) }This program cannot be run in DOS mode.` ), 'application/pdf' );
		await expect( panel.getByText( 'El archivo debe ser una imagen o un PDF.' ) ).toBeVisible();
		expect( await libraryCount() ).toBe( before );

		// Una imagen: se sube, se ve en la vista previa y queda como adjunto del evento.
		await dropFile( 'foto-soltada-e2e.png', PNG, 'image/png' );
		await expect( panel.locator( '.ep-media-field__name' ) ).toContainText( 'foto-soltada-e2e' );
		await expect( panel.getByText( 'El archivo debe ser una imagen o un PDF.' ) ).toBeHidden();
		await panel.getByRole( 'button', { name: 'Crear evento' } ).click();
		await expect( toastWith( page, `Evento «${ title }» creado.` ) ).toBeVisible();
	} );
} );
