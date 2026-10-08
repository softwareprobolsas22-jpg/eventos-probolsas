/**
 * Estado de los filtros de una pantalla, independiente del DOM.
 */

/**
 * Indica si un valor de filtro está activo.
 *
 * @param {unknown} value Valor del filtro (texto u objeto de textos, como un rango de fechas).
 * @returns {boolean} Si está activo.
 */
export function isActiveFilter( value ) {
	if ( null !== value && 'object' === typeof value ) {
		return Object.values( value ).some( isActiveFilter );
	}
	return '' !== String( value ?? '' ).trim();
}

/**
 * Cantidad de filtros activos.
 *
 * @param {Record<string, unknown>} filters Valores de los filtros.
 * @returns {number} Filtros con valor.
 */
export function countActiveFilters( filters ) {
	return Object.values( filters ).filter( isActiveFilter ).length;
}
