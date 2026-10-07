/**
 * Utilidades de tiempo.
 */

/**
 * Retrasa la ejecución hasta que pasen `wait` ms sin nuevas llamadas.
 *
 * @template {(...args: any[]) => void} T
 * @param {T} fn Función a ejecutar.
 * @param {number} wait Milisegundos de espera.
 * @returns {T & { cancel: () => void, flush: () => void }} Función con retraso.
 */
export function debounce( fn, wait ) {
	let timer = null;
	let pendingArgs = null;

	const debounced = ( ...args ) => {
		pendingArgs = args;
		clearTimeout( timer );
		timer = setTimeout( debounced.flush, wait );
	};

	debounced.cancel = () => {
		clearTimeout( timer );
		pendingArgs = null;
	};

	debounced.flush = () => {
		clearTimeout( timer );
		if ( pendingArgs ) {
			const args = pendingArgs;
			pendingArgs = null;
			fn( ...args );
		}
	};

	return debounced;
}

/**
 * Indica si el usuario pidió reducir el movimiento en su sistema operativo.
 *
 * @returns {boolean} Si se deben evitar animaciones.
 */
export function prefersReducedMotion() {
	return Boolean( globalThis.matchMedia?.( '(prefers-reduced-motion: reduce)' ).matches );
}
