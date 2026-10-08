/**
 * Botones reutilizables.
 */
import { h, icon } from '../core/dom.js';

/**
 * Botón de solo ícono, con nombre accesible y tooltip con el mismo texto.
 *
 * @param {{ icon: string, label: string, onClick?: (event: MouseEvent) => void, variant?: 'default'|'danger'|'primary', disabled?: boolean, attrs?: Object }} options Opciones.
 * @returns {HTMLButtonElement} Botón.
 */
export function iconButton( { icon: iconClass, label, onClick, variant = 'default', disabled = false, attrs = {} } ) {
	return h(
		'button',
		{
			type: 'button',
			class: [ 'ep-icon-button', 'default' !== variant && `ep-icon-button--${ variant }` ],
			attrs: { 'aria-label': label, 'data-ep-tooltip': label, ...attrs },
			disabled,
			on: onClick ? { click: onClick } : {},
		},
		icon( iconClass )
	);
}

/**
 * Botón con texto y, opcionalmente, ícono.
 *
 * @param {{ label: string, icon?: string, onClick?: (event: MouseEvent) => void, variant?: 'primary'|'secondary'|'ghost'|'danger', size?: 'md'|'sm', type?: string }} options Opciones.
 * @returns {HTMLButtonElement} Botón.
 */
export function button( { label, icon: iconClass, onClick, variant = 'secondary', size = 'md', type = 'button' } ) {
	return h(
		'button',
		{
			type,
			class: [ 'ep-button', `ep-button--${ variant }`, 'sm' === size && 'ep-button--sm' ],
			on: onClick ? { click: onClick } : {},
		},
		iconClass && icon( iconClass ),
		h( 'span', { text: label } )
	);
}
