/**
 * Cálculo de paginación, independiente del DOM.
 */

/**
 * Datos de una página.
 *
 * @param {number} total Total de registros.
 * @param {number} page Página solicitada (1 en adelante). Se ajusta al rango válido.
 * @param {number} pageSize Registros por página.
 * @returns {{ page: number, pageCount: number, startIndex: number, endIndex: number, from: number, to: number, total: number, pages: Array<number|'gap'> }} Página.
 */
export function paginate( total, page, pageSize ) {
	const pageCount = Math.max( 1, Math.ceil( total / pageSize ) );
	const current = Math.min( Math.max( 1, Math.trunc( page ) || 1 ), pageCount );
	const startIndex = ( current - 1 ) * pageSize;
	const endIndex = Math.min( startIndex + pageSize, total );

	return {
		page: current,
		pageCount,
		startIndex,
		endIndex,
		from: 0 === total ? 0 : startIndex + 1,
		to: endIndex,
		total,
		pages: pageWindow( current, pageCount ),
	};
}

/**
 * Números de página visibles: siempre la primera y la última, la actual y sus vecinas,
 * con `'gap'` donde se omiten páginas. Ejemplo: [1, 'gap', 4, 5, 6, 'gap', 10].
 *
 * @param {number} current Página actual.
 * @param {number} count Total de páginas.
 * @param {number} siblings Páginas vecinas a cada lado.
 * @returns {Array<number|'gap'>} Páginas.
 */
export function pageWindow( current, count, siblings = 1 ) {
	const visible = new Set( [ 1, count ] );
	for ( let page = current - siblings; page <= current + siblings; page++ ) {
		if ( page >= 1 && page <= count ) {
			visible.add( page );
		}
	}

	const sorted = [ ...visible ].sort( ( a, b ) => a - b );
	const result = [];
	sorted.forEach( ( page, index ) => {
		const previous = sorted[ index - 1 ];
		if ( undefined !== previous && page - previous > 1 ) {
			result.push( 2 === page - previous ? previous + 1 : 'gap' );
		}
		result.push( page );
	} );

	return result;
}
