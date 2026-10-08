/**
 * Badge de tipo de evento: color e ícono elegidos por el usuario, con texto siempre legible (R-02).
 */
import { readableTextTone } from '../core/color.js';
import { h, icon } from '../core/dom.js';

/**
 * Crea el badge de un tipo de evento.
 *
 * El tono del texto es el `text_tone` que calcula el servidor (SSOT); si no viene (vista previa del
 * formulario mientras se elige el color) se calcula con la misma fórmula.
 *
 * @param {{ name: string, color: string, icon?: string, text_tone?: 'light'|'dark', maxWidth?: string }} type Datos del tipo.
 * @returns {HTMLElement} Badge.
 */
export function eventTypeBadge( { name, color, icon: iconKey, text_tone: textTone, maxWidth } ) {
	const tone = 'light' === textTone || 'dark' === textTone ? textTone : readableTextTone( color );
	return h(
		'span',
		{
			class: [ 'ep-badge', `ep-badge--${ tone }-text` ],
			style: { '--ep-badge-color': color, ...( maxWidth ? { '--ep-truncate-width': maxWidth } : {} ) },
		},
		iconKey && icon( `fa-solid fa-${ iconKey }` ),
		h( 'span', { class: 'ep-badge__label ep-truncate', text: name } )
	);
}
