// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { suspendModal } from '../../../assets/src/js/core/dom.js';
import { createMediaField, fromRestAttachment, fromWpAttachment, openMediaLibrary } from '../../../assets/src/js/ui/media-field.js';

const PHOTO = { id: 315, mime: 'image/jpeg', url: 'https://intranet.test/uploads/ana.jpg', thumbnail_url: 'https://intranet.test/uploads/ana-150x150.jpg', filename: 'ana.jpg', title: 'ana' };
const PDF = { id: 316, mime: 'application/pdf', url: 'https://intranet.test/uploads/acta.pdf', thumbnail_url: null, filename: 'acta.pdf', title: 'acta' };
const MIMES = [ 'image/jpeg', 'image/png', 'application/pdf' ];

beforeEach( () => {
	document.body.innerHTML = '';
} );

afterEach( () => vi.unstubAllGlobals() );

function build( options = {} ) {
	const field = createMediaField( { name: 'attachment_id', label: 'Adjunto', allowedMimes: MIMES, ...options } );
	document.body.append( field.element );
	return field;
}

const buttonByText = ( field, text ) => [ ...field.element.querySelectorAll( 'button' ) ].find( ( button ) => button.textContent === text );
const flush = () => new Promise( ( resolve ) => setTimeout( resolve ) );

describe( 'createMediaField', () => {
	it( 'sin archivo: «Sin archivo», botón «Elegir archivo» y valor vacío; es un grupo con nombre', () => {
		const field = build();

		expect( field.element.getAttribute( 'role' ) ).toBe( 'group' );
		expect( document.getElementById( field.element.getAttribute( 'aria-labelledby' ) ).textContent ).toContain( 'Adjunto' );
		expect( field.element.querySelector( '.ep-media-field__empty' ).textContent ).toBe( 'Sin archivo' );
		expect( buttonByText( field, 'Elegir archivo' ) ).toBeDefined();
		expect( buttonByText( field, 'Quitar' ).hidden ).toBe( true );
		expect( field.getValue() ).toBe( '' );
	} );

	it( 'muestra la miniatura de una imagen y el ícono de un PDF, con enlace a otra pestaña', () => {
		const photo = build( { value: PHOTO } );
		expect( photo.element.querySelector( 'img' ).getAttribute( 'src' ) ).toBe( PHOTO.thumbnail_url );
		expect( photo.element.querySelector( 'img' ).getAttribute( 'alt' ) ).toBe( '' );
		expect( photo.getValue() ).toBe( '315' );

		const pdf = build( { value: PDF } );
		expect( pdf.element.querySelector( '.fa-file-pdf' ) ).not.toBeNull();
		expect( pdf.element.querySelector( '.ep-media-field__name' ).textContent ).toBe( 'acta.pdf' );
		const link = pdf.element.querySelector( 'a' );
		expect( link.getAttribute( 'href' ) ).toBe( PDF.url );
		expect( link.getAttribute( 'target' ) ).toBe( '_blank' );
		expect( link.getAttribute( 'rel' ) ).toBe( 'noopener noreferrer' );
		expect( link.textContent ).toContain( 'Abrir PDF' );
	} );

	it( 'elegir abre la biblioteca filtrada a imágenes y PDF; quitar deja el campo vacío', async () => {
		const openLibrary = vi.fn( async () => PDF );
		const field = build( { openLibrary, libraryTypes: [ 'image', 'application/pdf' ] } );

		buttonByText( field, 'Elegir archivo' ).click();
		await flush();

		expect( openLibrary ).toHaveBeenCalledWith( expect.objectContaining( { libraryTypes: [ 'image', 'application/pdf' ], anchor: field.element } ) );
		expect( field.getValue() ).toBe( '316' );
		expect( buttonByText( field, 'Cambiar archivo' ) ).toBeDefined();

		buttonByText( field, 'Quitar' ).click();
		expect( field.getValue() ).toBe( '' );
		expect( document.activeElement ).toBe( buttonByText( field, 'Elegir archivo' ) );
	} );

	it( 'cerrar la biblioteca sin elegir no cambia nada', async () => {
		const field = build( { value: PHOTO, openLibrary: vi.fn( async () => null ) } );

		buttonByText( field, 'Cambiar archivo' ).click();
		await flush();

		expect( field.getValue() ).toBe( '315' );
	} );

	it( 'si la biblioteca no está disponible, lo dice bajo el campo', async () => {
		const field = build( { openLibrary: vi.fn( async () => undefined ) } );

		buttonByText( field, 'Elegir archivo' ).click();
		await flush();

		expect( field.element.querySelector( '.ep-field__error' ).textContent ).toBe( 'No se pudo abrir la Biblioteca de Medios. Recarga la página e inténtalo de nuevo.' );
	} );

	it( 'obligatorio según el tipo, con el mensaje del servidor; deja de serlo sin error', () => {
		const field = build( { required: true } );
		const error = field.element.querySelector( '.ep-field__error' );

		const optional = field.element.querySelector( '.ep-field__optional' );

		expect( field.element.querySelector( '.ep-field__required' ).hidden ).toBe( false );
		expect( optional.hidden ).toBe( true );
		expect( field.validate() ).toBe( 'Este tipo de evento requiere una imagen o un PDF.' );
		expect( error.hidden ).toBe( false );

		field.setRequired( false );
		expect( field.element.querySelector( '.ep-field__required' ).hidden ).toBe( true );
		expect( optional.hidden ).toBe( false );
		expect( optional.textContent ).toBe( ' (opcional)' );
		expect( error.hidden ).toBe( true );
	} );

	it( 'rechaza un tipo de archivo no permitido (R-09) y el error de la API se mantiene hasta cambiar el archivo', async () => {
		const field = build( { value: { ...PDF, mime: 'application/zip' }, openLibrary: vi.fn( async () => PHOTO ) } );
		expect( field.validate() ).toBe( 'El archivo debe ser una imagen o un PDF.' );

		field.setValue( PDF );
		field.setError( 'El archivo elegido ya no existe en la Biblioteca de Medios.' );
		field.validate();
		expect( field.element.querySelector( '.ep-field__error' ).hidden ).toBe( false );

		buttonByText( field, 'Cambiar archivo' ).click();
		await flush();
		expect( field.element.querySelector( '.ep-field__error' ).hidden ).toBe( true );
		expect( field.getAttachment() ).toEqual( PHOTO );
	} );
} );

describe( 'selector de la Biblioteca de Medios', () => {
	it( 'convierte el adjunto de wp.media a la forma de la API', () => {
		expect( fromWpAttachment( { id: '9', type: 'image', mime: 'image/png', url: 'u.png', filename: 'u.png', title: 'u', sizes: { thumbnail: { url: 't.png' } } } ) ).toEqual( {
			id: 9,
			mime: 'image/png',
			url: 'u.png',
			thumbnail_url: 't.png',
			filename: 'u.png',
			title: 'u',
		} );
		expect( fromWpAttachment( { id: 1, type: 'application', mime: 'application/pdf', url: 'a.pdf' } ).thumbnail_url ).toBeNull();
	} );

	it( 'sin wp.media responde undefined', async () => {
		await expect( openMediaLibrary( { title: 'Adjunto', buttonText: 'Usar', libraryTypes: [], anchor: document.body } ) ).resolves.toBeUndefined();
	} );

	it( 'abre wp.media filtrado y resuelve con lo elegido al cerrarse; el panel deja de ser modal mientras tanto', async () => {
		const handlers = {};
		const selected = { id: 316, type: 'application', mime: 'application/pdf', url: 'acta.pdf', filename: 'acta.pdf', title: 'acta' };
		const frame = {
			on: ( event, handler ) => ( handlers[ event ] = handler ),
			state: () => ( { get: () => ( { first: () => ( { toJSON: () => selected } ) } ) } ),
			open: vi.fn(),
		};
		const media = vi.fn( () => frame );
		vi.stubGlobal( 'wp', { media } );

		const dialog = document.createElement( 'dialog' );
		const anchor = document.createElement( 'div' );
		dialog.append( anchor );
		document.body.append( dialog );
		dialog.showModal();
		dialog.setAttribute( 'data-ep-modal', '' );

		const result = openMediaLibrary( { title: 'Adjunto', buttonText: 'Usar este archivo', libraryTypes: [ 'image', 'application/pdf' ], anchor } );
		await flush();

		expect( media ).toHaveBeenCalledWith( { title: 'Adjunto', button: { text: 'Usar este archivo' }, library: { type: [ 'image', 'application/pdf' ] }, multiple: false } );
		expect( frame.open ).toHaveBeenCalled();
		expect( dialog.classList.contains( 'is-suspended' ) ).toBe( true );

		// Orden real de WordPress: primero cierra el selector y después emite «select» (QA-043).
		handlers.close();
		handlers.select();

		await expect( result ).resolves.toEqual( fromWpAttachment( selected ) );
		expect( dialog.classList.contains( 'is-suspended' ) ).toBe( false );
		expect( dialog.open ).toBe( true );
	} );

	it( 'cerrar wp.media sin elegir resuelve null', async () => {
		const handlers = {};
		vi.stubGlobal( 'wp', { media: () => ( { on: ( event, handler ) => ( handlers[ event ] = handler ), open: () => {} } ) } );

		const result = openMediaLibrary( { title: 'Adjunto', buttonText: 'Usar', libraryTypes: [], anchor: document.body } );
		await flush();
		handlers.close();

		await expect( result ).resolves.toBeNull();
	} );
} );

describe( 'suspendModal', () => {
	it( 'sin diálogo abierto solo ejecuta la tarea', async () => {
		await expect( suspendModal( document.body, async () => 'listo' ) ).resolves.toBe( 'listo' );
	} );

	it( 'si el diálogo se elimina durante la tarea, no lo vuelve a abrir', async () => {
		const dialog = document.createElement( 'dialog' );
		const anchor = document.createElement( 'span' );
		dialog.append( anchor );
		document.body.append( dialog );
		dialog.showModal();
		dialog.setAttribute( 'data-ep-modal', '' );

		await suspendModal( anchor, async () => dialog.remove() );

		expect( dialog.isConnected ).toBe( false );
	} );
} );

describe( 'createMediaField: arrastrar y soltar (H-411)', () => {
	const pdfFile = ( size = 2048 ) => new File( [ 'x'.repeat( size ) ], 'acta.pdf', { type: 'application/pdf' } );

	/**
	 * Evento de arrastre con archivos.
	 *
	 * @param {string} type Tipo de evento.
	 * @param {File[]} files Archivos.
	 * @returns {Event} Evento.
	 */
	function dragEvent( type, files = [] ) {
		const event = new Event( type, { bubbles: true, cancelable: true } );
		Object.defineProperty( event, 'dataTransfer', { value: { types: [ 'Files' ], files, dropEffect: 'none' } } );
		return event;
	}

	/**
	 * Subida que se resuelve cuando la prueba lo indica.
	 *
	 * @returns {{ uploadFile: Function, finish: (value: Object) => void, fail: (error: Error) => void, progress: (percent: number) => void }} Control.
	 */
	function controlledUpload() {
		const control = {};
		control.uploadFile = vi.fn(
			( file, onProgress ) =>
				new Promise( ( resolve, reject ) => {
					Object.assign( control, { finish: resolve, fail: reject, progress: onProgress } );
				} )
		);
		return control;
	}

	it( 'sin uploadFile no acepta archivos soltados y conserva la ayuda de la biblioteca', () => {
		const field = build();

		expect( field.element.querySelector( '.ep-media-field__dropzone' ) ).toBeNull();
		expect( field.element.querySelector( '.ep-field__hint' ).textContent ).toContain( 'de la Biblioteca de Medios' );
	} );

	it( 'soltar un PDF lo sube con progreso, muestra la vista previa y confirma con un toast', async () => {
		const upload = controlledUpload();
		const field = build( { uploadFile: upload.uploadFile, maxUploadBytes: 10 * 1024 * 1024 } );
		const zone = field.element.querySelector( '.ep-media-field__dropzone' );
		const progress = field.element.querySelector( '.ep-media-field__progress' );

		expect( zone.textContent ).toContain( 'Suelta aquí una imagen o un PDF' );
		expect( field.element.querySelector( '.ep-field__hint' ).textContent ).toContain( 'Arrastra aquí' );

		const over = dragEvent( 'dragover' );
		zone.dispatchEvent( over );
		expect( over.defaultPrevented ).toBe( true );
		expect( zone.classList.contains( 'is-dragover' ) ).toBe( true );

		const file = pdfFile();
		zone.dispatchEvent( dragEvent( 'drop', [ file ] ) );
		expect( zone.classList.contains( 'is-dragover' ) ).toBe( false );
		expect( upload.uploadFile ).toHaveBeenCalledWith( file, expect.any( Function ) );
		expect( progress.hidden ).toBe( false );
		expect( buttonByText( field, 'Elegir archivo' ).disabled ).toBe( true );
		expect( field.validate() ).toBe( 'Espera a que termine de subir el archivo.' );

		upload.progress( 40 );
		expect( progress.textContent ).toContain( 'Subiendo «acta.pdf»… 40 %' );
		expect( progress.querySelector( '[role="progressbar"]' ).getAttribute( 'aria-valuenow' ) ).toBe( '40' );

		upload.finish( PDF );
		await flush();

		expect( progress.hidden ).toBe( true );
		expect( field.getValue() ).toBe( '316' );
		expect( field.element.querySelector( '.ep-media-field__name' ).textContent ).toBe( 'acta.pdf' );
		expect( field.validate() ).toBeNull();
		expect( document.querySelector( '.notyf' ).textContent ).toContain( 'Archivo «acta.pdf» subido a la Biblioteca de Medios.' );
	} );

	it( 'valida antes de subir: tipo, tamaño y un solo archivo (D-4: no se sube lo que se rechazaría)', async () => {
		const upload = controlledUpload();
		const field = build( { uploadFile: upload.uploadFile, maxUploadBytes: 1024 * 1024 } );
		const error = () => field.element.querySelector( '.ep-field__error' );

		await field.uploadDropped( [ new File( [ 'texto' ], 'notas.docx', { type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' } ) ] );
		expect( error().textContent ).toBe( 'El archivo debe ser una imagen o un PDF.' );

		await field.uploadDropped( [ pdfFile( 2 * 1024 * 1024 ) ] );
		expect( error().textContent ).toBe( 'El archivo supera el tamaño máximo permitido (1 MB).' );

		await field.uploadDropped( [ pdfFile(), pdfFile() ] );
		expect( error().textContent ).toBe( 'Suelta un solo archivo.' );

		expect( upload.uploadFile ).not.toHaveBeenCalled();
		expect( field.getValue() ).toBe( '' );
	} );

	it( 'si el servidor rechaza el archivo, muestra su mensaje y conserva el adjunto anterior', async () => {
		const upload = controlledUpload();
		const field = build( { value: PHOTO, uploadFile: upload.uploadFile } );

		const pending = field.uploadDropped( [ pdfFile() ] );
		upload.fail( Object.assign( new Error( 'Revisa el archivo.' ), { fieldErrors: { file: [ 'La extensión del archivo no corresponde a su contenido.' ] } } ) );
		await pending;

		expect( field.element.querySelector( '.ep-field__error' ).textContent ).toBe( 'La extensión del archivo no corresponde a su contenido.' );
		expect( field.getValue() ).toBe( '315' );
		expect( buttonByText( field, 'Cambiar archivo' ).disabled ).toBe( false );
		expect( field.element.getAttribute( 'aria-busy' ) ).toBe( 'false' );
	} );

	it( 'ignora lo que no son archivos (texto o enlaces arrastrados)', () => {
		const upload = controlledUpload();
		const field = build( { uploadFile: upload.uploadFile } );
		const zone = field.element.querySelector( '.ep-media-field__dropzone' );
		const text = new Event( 'drop', { bubbles: true, cancelable: true } );
		Object.defineProperty( text, 'dataTransfer', { value: { types: [ 'text/plain' ], files: [] } } );

		zone.dispatchEvent( text );

		expect( text.defaultPrevented ).toBe( false );
		expect( upload.uploadFile ).not.toHaveBeenCalled();
	} );
} );

describe( 'fromRestAttachment', () => {
	it( 'convierte la respuesta de wp/v2/media en el adjunto del plugin', () => {
		expect(
			fromRestAttachment( {
				id: 88,
				mime_type: 'image/png',
				source_url: 'https://intranet.test/uploads/foto%20ana.png',
				title: { raw: 'foto ana', rendered: 'foto ana' },
				media_details: { sizes: { thumbnail: { source_url: 'https://intranet.test/uploads/foto-ana-150x150.png' } } },
			} )
		).toEqual( { id: 88, mime: 'image/png', url: 'https://intranet.test/uploads/foto%20ana.png', thumbnail_url: 'https://intranet.test/uploads/foto-ana-150x150.png', filename: 'foto ana.png', title: 'foto ana' } );

		expect( fromRestAttachment( { id: 89, mime_type: 'application/pdf', source_url: 'https://intranet.test/uploads/acta.pdf', media_details: {} } ) ).toMatchObject( { thumbnail_url: null, filename: 'acta.pdf', title: '' } );
	} );
} );
