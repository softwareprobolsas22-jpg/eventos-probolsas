/**
 * Tooltips con Tippy.js, por delegación: funcionan también en elementos creados después (filas de tablas).
 *
 * - `data-ep-tooltip="Texto"`: muestra el texto al pasar el mouse o al enfocar con el teclado.
 *   El elemento debe tener su propio nombre accesible (aria-label o texto): el tooltip es visual.
 * - `.ep-truncate`: muestra el texto completo solo si está cortado con «…».
 */
import { delegate } from 'tippy.js';
import { prefersReducedMotion } from '../core/timing.js';

/**
 * Opciones comunes de todos los tooltips.
 *
 * @returns {Object} Opciones de Tippy.
 */
function baseOptions() {
	const reduced = prefersReducedMotion();
	return {
		theme: 'ep',
		animation: reduced ? false : 'shift-away-subtle',
		duration: reduced ? 0 : [ 150, 100 ],
		delay: [ 250, 0 ],
		maxWidth: 320,
		appendTo: () => document.body,
		// El elemento ya tiene nombre accesible; evita que el lector de pantalla lea el texto dos veces.
		aria: { content: null, expanded: false },
	};
}

/**
 * Activa los tooltips dentro de un contenedor.
 *
 * @param {HTMLElement} root Contenedor (normalmente document.body).
 * @returns {() => void} Función para desactivarlos.
 */
export function initTooltips( root ) {
	const instances = [
		delegate( root, {
			...baseOptions(),
			target: '[data-ep-tooltip]',
			trigger: 'mouseenter focus',
			content: ( reference ) => reference.getAttribute( 'data-ep-tooltip' ) ?? '',
		} ),
		delegate( root, {
			...baseOptions(),
			target: '.ep-truncate',
			trigger: 'mouseenter focus',
			content: ( reference ) => reference.textContent.trim(),
			onShow: ( instance ) => isTruncated( instance.reference ),
		} ),
	].flat();

	return () => instances.forEach( ( instance ) => instance.destroy() );
}

/**
 * Indica si el texto de un elemento está cortado.
 *
 * @param {Element} element Elemento con text-overflow.
 * @returns {boolean} Si el contenido no cabe.
 */
export function isTruncated( element ) {
	return element.scrollWidth > element.clientWidth;
}
