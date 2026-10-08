import { describe, expect, it, vi } from 'vitest';
import { ApiError, createApi } from '../../../assets/src/js/core/api.js';

const config = { restUrl: 'https://intranet.test/wp-json/eventos/v1/', restNonce: 'nonce-123' };

/**
 * fetch simulado que responde con el estado y el cuerpo indicados.
 *
 * @param {number} status Estado HTTP.
 * @param {unknown} body Cuerpo JSON.
 * @returns {import('vitest').Mock} fetch.
 */
function fakeFetch( status, body ) {
	return vi.fn( async () => ( { ok: status >= 200 && status < 300, status, json: async () => body } ) );
}

describe( 'createApi', () => {
	it( 'envía el nonce y devuelve el contenido de data', async () => {
		const fetch = fakeFetch( 200, { data: [ { id: 1 } ] } );
		const api = createApi( config, { fetch } );

		await expect( api.get( 'event-types' ) ).resolves.toEqual( [ { id: 1 } ] );

		const [ url, request ] = fetch.mock.calls[ 0 ];
		expect( url ).toBe( 'https://intranet.test/wp-json/eventos/v1/event-types' );
		expect( request.headers[ 'X-WP-Nonce' ] ).toBe( 'nonce-123' );
		expect( request.credentials ).toBe( 'same-origin' );
	} );

	it( 'envía el cuerpo como JSON', async () => {
		const fetch = fakeFetch( 201, { data: { id: 7 } } );
		await createApi( config, { fetch } ).post( '/event-types', { name: 'Cumpleaños' } );

		const [ , request ] = fetch.mock.calls[ 0 ];
		expect( request.method ).toBe( 'POST' );
		expect( request.headers[ 'Content-Type' ] ).toBe( 'application/json' );
		expect( request.body ).toBe( '{"name":"Cumpleaños"}' );
	} );

	it( 'convierte la validación en ApiError con errores por campo y la notifica', async () => {
		const notify = vi.fn();
		const fetch = fakeFetch( 422, {
			code: 'eventos_validation_failed',
			message: 'Revisa los campos marcados.',
			data: { status: 422, errors: { name: [ 'El campo «Nombre» es obligatorio.' ] } },
		} );

		const error = await createApi( config, { fetch, notify } ).post( 'event-types', {} ).catch( ( e ) => e );

		expect( error ).toBeInstanceOf( ApiError );
		expect( error.code ).toBe( 'eventos_validation_failed' );
		expect( error.fieldErrors.name ).toEqual( [ 'El campo «Nombre» es obligatorio.' ] );
		expect( notify ).toHaveBeenCalledWith( 'error', 'Revisa los campos marcados.' );
	} );

	it( 'explica que la sesión expiró cuando el nonce no es válido', async () => {
		const notify = vi.fn();
		const fetch = fakeFetch( 403, { code: 'rest_cookie_invalid_nonce', message: 'Cookie check failed' } );

		await createApi( config, { fetch, notify } ).get( 'event-types' ).catch( () => {} );

		expect( notify ).toHaveBeenCalledWith( 'error', 'Tu sesión expiró. Recarga la página para continuar.' );
	} );

	it( 'traduce la falta de permisos a un mensaje en español', async () => {
		const notify = vi.fn();
		const fetch = fakeFetch( 403, { code: 'rest_forbidden', message: 'Sorry, you are not allowed to do that.' } );

		await createApi( config, { fetch, notify } ).del( 'event-types/1' ).catch( () => {} );

		expect( notify ).toHaveBeenCalledWith( 'error', 'No tienes permisos para realizar esta acción.' );
	} );

	it( 'informa los errores de red', async () => {
		const notify = vi.fn();
		const fetch = vi.fn( async () => {
			throw new TypeError( 'Failed to fetch' );
		} );

		await expect( createApi( config, { fetch, notify } ).get( 'event-types' ) ).rejects.toBeInstanceOf( ApiError );
		expect( notify.mock.calls[ 0 ][ 1 ] ).toMatch( /No se pudo conectar/ );
	} );

	it( 'no notifica si se pide silencio', async () => {
		const notify = vi.fn();
		await createApi( config, { fetch: fakeFetch( 404, { code: 'eventos_not_found', message: 'No existe.' } ), notify } )
			.get( 'event-types/9', { silent: true } )
			.catch( () => {} );

		expect( notify ).not.toHaveBeenCalled();
	} );
} );

describe( 'createApi().upload', () => {
	/**
	 * XMLHttpRequest simulado: guarda la última instancia para responder desde la prueba.
	 */
	class FakeXhr {
		static last = null;

		constructor() {
			this.headers = {};
			this.listeners = {};
			this.upload = { addEventListener: ( type, listener ) => ( this.uploadProgress = listener ) };
			FakeXhr.last = this;
		}

		open( method, url ) {
			Object.assign( this, { method, url } );
		}

		setRequestHeader( name, value ) {
			this.headers[ name ] = value;
		}

		addEventListener( type, listener ) {
			this.listeners[ type ] = listener;
		}

		send( body ) {
			this.body = body;
		}

		respond( status, body ) {
			Object.assign( this, { status, responseText: 'string' === typeof body ? body : JSON.stringify( body ) } );
			this.listeners.load();
		}
	}

	const file = new File( [ 'contenido' ], 'manual.pdf', { type: 'application/pdf' } );

	it( 'envía el archivo con el nonce, informa el progreso y devuelve data', async () => {
		vi.stubGlobal( 'XMLHttpRequest', FakeXhr );
		const onProgress = vi.fn();
		const pending = createApi( config ).upload( 'documents/upload', file, { onProgress } );
		const xhr = FakeXhr.last;

		expect( xhr.method ).toBe( 'POST' );
		expect( xhr.url ).toBe( 'https://intranet.test/wp-json/eventos/v1/documents/upload' );
		expect( xhr.headers[ 'X-WP-Nonce' ] ).toBe( 'nonce-123' );
		expect( xhr.body.get( 'file' ).name ).toBe( 'manual.pdf' );

		xhr.uploadProgress( { lengthComputable: true, loaded: 1, total: 4 } );
		expect( onProgress ).toHaveBeenCalledWith( 25 );

		xhr.respond( 201, { data: { id: 31, filename: 'manual.pdf' } } );
		await expect( pending ).resolves.toEqual( { id: 31, filename: 'manual.pdf' } );
		vi.unstubAllGlobals();
	} );

	it( 'convierte un 422 en ApiError con los errores del campo file', async () => {
		vi.stubGlobal( 'XMLHttpRequest', FakeXhr );
		const notify = vi.fn();
		const pending = createApi( config, { notify } ).upload( 'documents/upload', file, { silent: true } );

		FakeXhr.last.respond( 422, { code: 'eventos_validation_failed', message: 'Revisa los datos.', data: { errors: { file: [ 'Tipo de archivo no permitido.' ] } } } );

		const error = await pending.catch( ( rejection ) => rejection );
		expect( error ).toBeInstanceOf( ApiError );
		expect( error.fieldErrors.file ).toEqual( [ 'Tipo de archivo no permitido.' ] );
		expect( notify ).not.toHaveBeenCalled();
		vi.unstubAllGlobals();
	} );

	it( 'tolera una respuesta que no es JSON', async () => {
		vi.stubGlobal( 'XMLHttpRequest', FakeXhr );
		const notify = vi.fn();
		const pending = createApi( config, { notify } ).upload( 'documents/upload', file );

		FakeXhr.last.respond( 500, '<html>Error</html>' );

		await expect( pending ).rejects.toThrow( 'Ocurrió un error inesperado. Inténtalo de nuevo.' );
		expect( notify ).toHaveBeenCalledWith( 'error', 'Ocurrió un error inesperado. Inténtalo de nuevo.' );
		vi.unstubAllGlobals();
	} );
} );
