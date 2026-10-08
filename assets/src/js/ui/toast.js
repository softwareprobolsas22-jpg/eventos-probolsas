/**
 * Notificaciones (toasts) sobre Notyf.
 *
 * - Cuatro tipos: success, error, warning e info, con su ícono y el color de los tokens.
 * - Apilables, con cierre manual y anunciados a lectores de pantalla (región aria-live de Notyf).
 * - El mensaje se escapa siempre: Notyf lo inserta como HTML y puede venir del servidor.
 * - Con un drawer o diálogo abierto, los toasts se muestran dentro de él: si no, quedarían debajo
 *   de la top layer, invisibles e inertes (QA-024).
 */
import { Notyf } from 'notyf';
import { escapeHtml, topLayerHost } from '../core/dom.js';

/** Ícono y duración (ms) de cada tipo. Los errores se muestran más tiempo. */
const TYPES = {
	success: { icon: 'fa-solid fa-circle-check', duration: 4000 },
	info: { icon: 'fa-solid fa-circle-info', duration: 5000 },
	warning: { icon: 'fa-solid fa-triangle-exclamation', duration: 6000 },
	error: { icon: 'fa-solid fa-circle-xmark', duration: 8000 },
};

let notyf = null;

/** Contenedor de los toasts y región aria-live de Notyf. */
let floating = [];

/**
 * Instancia única, creada la primera vez que se muestra un toast. Antes de cada toast lleva sus
 * contenedores a la capa visible (topLayerHost).
 *
 * @returns {Notyf} Instancia.
 */
function instance() {
	if ( ! notyf ) {
		notyf = new Notyf( {
			position: { x: 'right', y: 'bottom' },
			dismissible: true,
			ripple: false,
			types: Object.entries( TYPES ).map( ( [ type, { icon, duration } ] ) => ( {
				type,
				duration,
				className: `ep-toast ep-toast--${ type }`,
				background: `var(--ep-color-${ type })`,
				icon: { className: icon, tagName: 'i' },
			} ) ),
		} );
		// Notyf 3 no expone sus contenedores en la API pública; están en view.container y view.a11yContainer.
		floating = [ notyf.view.container, notyf.view.a11yContainer ];
		floating.forEach( ( element ) => element.setAttribute( 'data-ep-floating', '' ) );
	}

	const host = topLayerHost();
	if ( floating[ 0 ].parentElement !== host ) {
		host.append( ...floating );
	}
	return notyf;
}

/**
 * Muestra un toast.
 *
 * @param {'success'|'error'|'warning'|'info'} type Tipo.
 * @param {string} message Mensaje (texto plano).
 */
export function showToast( type, message ) {
	instance().open( { type: TYPES[ type ] ? type : 'info', message: escapeHtml( message ) } );
}

/** Atajos por tipo. */
export const toast = {
	success: ( message ) => showToast( 'success', message ),
	error: ( message ) => showToast( 'error', message ),
	warning: ( message ) => showToast( 'warning', message ),
	info: ( message ) => showToast( 'info', message ),
};
