/**
 * Cliente de la API REST del plugin (contrato en docs/api/README.md).
 */
import { __ } from './i18n.js';

/**
 * Error devuelto por la API, con los errores por campo si es una validación.
 */
export class ApiError extends Error {
	/**
	 * @param {string} message Mensaje para el usuario.
	 * @param {{ code?: string, status?: number, fieldErrors?: Record<string, string[]> }} details Detalles.
	 */
	constructor( message, { code = 'eventos_unknown_error', status = 0, fieldErrors = {} } = {} ) {
		super( message );
		this.name = 'ApiError';
		this.code = code;
		this.status = status;
		this.fieldErrors = fieldErrors;
	}
}

/**
 * Crea el cliente.
 *
 * @param {{ restUrl: string, restNonce: string }} config Configuración (de epConfig).
 * @param {{ notify?: (type: string, message: string) => void, fetch?: typeof fetch }} options
 *   notify: muestra los errores (normalmente toast). fetch: inyectable en pruebas.
 * @returns {{ get: Function, getPage: Function, post: Function, put: Function, del: Function, upload: Function }} Cliente.
 */
export function createApi( { restUrl, restNonce }, { notify, fetch: fetchImpl = globalThis.fetch?.bind( globalThis ) } = {} ) {
	/**
	 * Ejecuta una petición. Los errores se notifican (salvo `silent`) y se lanzan como ApiError.
	 *
	 * @param {string} method Método HTTP.
	 * @param {string} path Ruta relativa al namespace, por ejemplo `event-types/7`.
	 * @param {{ body?: unknown, silent?: boolean }} options Opciones.
	 * @returns {Promise<{ data: unknown, headers: Headers }>} Valor de `data` y cabeceras de la respuesta.
	 */
	async function request( method, path, { body, silent = false } = {} ) {
		try {
			return await send( method, path, body );
		} catch ( error ) {
			const apiError = error instanceof ApiError ? error : new ApiError( __( 'No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.', 'eventos-probolsas' ) );
			if ( ! silent && notify ) {
				notify( 'error', apiError.message );
			}
			throw apiError;
		}
	}

	/**
	 * Envía la petición y valida la respuesta.
	 *
	 * @param {string} method Método HTTP.
	 * @param {string} path Ruta.
	 * @param {unknown} body Cuerpo.
	 * @returns {Promise<{ data: unknown, headers: Headers }>} Datos y cabeceras.
	 */
	async function send( method, path, body ) {
		const response = await fetchImpl( restUrl + path.replace( /^\//, '' ), {
			method,
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'X-WP-Nonce': restNonce,
				...( undefined === body ? {} : { 'Content-Type': 'application/json' } ),
			},
			body: undefined === body ? undefined : JSON.stringify( body ),
		} );

		const payload = await response.json().catch( () => null );

		if ( ! response.ok ) {
			throw toApiError( response.status, payload );
		}

		return { data: payload?.data, headers: response.headers };
	}

	/**
	 * Sube un archivo (campo `file`) informando el progreso. Usa XMLHttpRequest porque fetch no
	 * informa el progreso de subida.
	 *
	 * @param {string} path Ruta relativa al namespace.
	 * @param {File} file Archivo.
	 * @param {{ onProgress?: (percent: number) => void, silent?: boolean }} options Opciones.
	 * @returns {Promise<unknown>} Valor de `data` de la respuesta.
	 */
	function upload( path, file, { onProgress, silent = false } = {} ) {
		return new Promise( ( resolve, reject ) => {
			const xhr = new XMLHttpRequest();
			const fail = ( error ) => {
				if ( ! silent && notify ) {
					notify( 'error', error.message );
				}
				reject( error );
			};

			xhr.open( 'POST', restUrl + path.replace( /^\//, '' ) );
			xhr.setRequestHeader( 'X-WP-Nonce', restNonce );
			xhr.setRequestHeader( 'Accept', 'application/json' );
			xhr.upload.addEventListener( 'progress', ( event ) => {
				if ( event.lengthComputable ) {
					onProgress?.( Math.round( ( event.loaded / event.total ) * 100 ) );
				}
			} );
			xhr.addEventListener( 'load', () => {
				const payload = parseJson( xhr.responseText );
				if ( xhr.status >= 200 && xhr.status < 300 ) {
					resolve( payload?.data );
				} else {
					fail( toApiError( xhr.status, payload ) );
				}
			} );
			xhr.addEventListener( 'error', () => fail( new ApiError( __( 'No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.', 'eventos-probolsas' ) ) ) );

			const body = new FormData();
			body.append( 'file', file );
			xhr.send( body );
		} );
	}

	const data = async ( promise ) => ( await promise ).data;

	return {
		get: ( path, options ) => data( request( 'GET', path, options ) ),
		/**
		 * Listado paginado en el servidor: registros y totales de `X-WP-Total` y `X-WP-TotalPages`.
		 *
		 * @param {string} path Ruta con los filtros en la consulta.
		 * @param {{ silent?: boolean }} [options] Opciones.
		 * @returns {Promise<{ items: Object[], total: number, totalPages: number }>} Página.
		 */
		getPage: async ( path, options ) => {
			const response = await request( 'GET', path, options );
			const header = ( name ) => Number( response.headers?.get?.( name ) ?? 0 ) || 0;
			return { items: Array.isArray( response.data ) ? response.data : [], total: header( 'X-WP-Total' ), totalPages: header( 'X-WP-TotalPages' ) };
		},
		post: ( path, body, options ) => data( request( 'POST', path, { ...options, body } ) ),
		put: ( path, body, options ) => data( request( 'PUT', path, { ...options, body } ) ),
		del: ( path, options ) => data( request( 'DELETE', path, options ) ),
		upload,
	};
}

/**
 * Interpreta un cuerpo JSON; null si no lo es (por ejemplo, una página de error del servidor).
 *
 * @param {string} text Cuerpo de la respuesta.
 * @returns {unknown} Datos o null.
 */
function parseJson( text ) {
	try {
		return JSON.parse( text );
	} catch {
		return null;
	}
}

/**
 * Convierte una respuesta de error de WordPress en ApiError.
 *
 * @param {number} status Estado HTTP.
 * @param {{ code?: string, message?: string, data?: { errors?: Record<string, string[]> } }|null} payload Cuerpo.
 * @returns {ApiError} Error.
 */
function toApiError( status, payload ) {
	const code = payload?.code ?? 'eventos_unknown_error';

	if ( 'rest_cookie_invalid_nonce' === code ) {
		return new ApiError( __( 'Tu sesión expiró. Recarga la página para continuar.', 'eventos-probolsas' ), { code, status } );
	}

	if ( 401 === status || 403 === status ) {
		return new ApiError( __( 'No tienes permisos para realizar esta acción.', 'eventos-probolsas' ), { code, status } );
	}

	return new ApiError( payload?.message || __( 'Ocurrió un error inesperado. Inténtalo de nuevo.', 'eventos-probolsas' ), {
		code,
		status,
		fieldErrors: payload?.data?.errors ?? {},
	} );
}
