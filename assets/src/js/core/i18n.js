/**
 * Traducción de textos de la interfaz con wp.i18n (cargado por Assets.php como dependencia).
 *
 * Las llamadas deben incluir el dominio literal `'eventos-probolsas'` para que `wp i18n make-pot` las extraiga:
 * `__( 'Limpiar filtros', 'eventos-probolsas' )`. Fuera de WordPress (pruebas) devuelven el texto original.
 */

/**
 * Traduce un texto.
 *
 * @param {string} text Texto en español.
 * @param {string} domain Dominio de traducción.
 * @returns {string} Texto traducido.
 */
export function __( text, domain = 'eventos-probolsas' ) {
	const i18n = globalThis.wp?.i18n;
	return i18n ? i18n.__( text, domain ) : text;
}

/**
 * Traduce un texto con forma singular y plural.
 *
 * @param {string} single Forma singular.
 * @param {string} plural Forma plural.
 * @param {number} number Cantidad.
 * @param {string} domain Dominio de traducción.
 * @returns {string} Texto traducido.
 */
export function _n( single, plural, number, domain = 'eventos-probolsas' ) {
	const i18n = globalThis.wp?.i18n;
	if ( i18n ) {
		return i18n._n( single, plural, number, domain );
	}
	return 1 === number ? single : plural;
}

/**
 * Reemplaza los marcadores %s, %d y %1$s de un texto.
 *
 * @param {string} format Texto con marcadores.
 * @param {...unknown} args Valores.
 * @returns {string} Texto con los valores.
 */
export function sprintf( format, ...args ) {
	const i18n = globalThis.wp?.i18n;
	if ( i18n ) {
		return i18n.sprintf( format, ...args );
	}

	let index = 0;
	return format.replace( /%(?:(\d+)\$)?[sd]/g, ( match, position ) => {
		const value = position ? args[ Number( position ) - 1 ] : args[ index++ ];
		return String( value );
	} );
}
