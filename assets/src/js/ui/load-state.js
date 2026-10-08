/**
 * Mensaje de error cuando una pantalla o widget no pudo cargar sus datos. Mientras cargan, cada
 * componente muestra su propio skeleton (por ejemplo, las filas de data-table).
 */
import { h, icon } from '../core/dom.js';
import { __ } from '../core/i18n.js';

/**
 * Mensaje cuando no se pudieron cargar los datos (la API ya mostró el detalle en un toast).
 *
 * @param {string} [title] Título.
 * @returns {HTMLElement} Mensaje.
 */
export function loadError( title = __( 'No se pudo cargar la información', 'eventos-probolsas' ) ) {
	return h(
		'div',
		{ class: 'ep-empty-state', attrs: { role: 'status' } },
		h( 'span', { class: 'ep-empty-state__icon', attrs: { 'aria-hidden': 'true' } }, icon( 'fa-solid fa-triangle-exclamation' ) ),
		h( 'p', { class: 'ep-empty-state__title', text: title } ),
		h( 'p', { class: 'ep-empty-state__message', text: __( 'Recarga la página para intentarlo de nuevo.', 'eventos-probolsas' ) } )
	);
}
