/**
 * Búsqueda sin distinguir mayúsculas ni tildes (decisión del PO).
 *
 * Es la versión JS de TextNormalizer.php. Ambas comparten casos de prueba
 * (tests/fixtures/text-normalization.json) y deben dar siempre el mismo resultado.
 */

/** Marcador temporal (uso privado de Unicode) que protege la ñ al quitar los diacríticos. */
const N_TILDE_PLACEHOLDER = '';

/**
 * Forma comparable de un texto: minúsculas, sin tildes ni diéresis y con los espacios colapsados.
 * La ñ se conserva porque en español es una letra propia (`ano` ≠ `año`).
 *
 * @param {unknown} value Texto original.
 * @returns {string} Texto normalizado.
 */
export function normalizeText( value ) {
	return String( value ?? '' )
		.normalize( 'NFC' )
		.toLowerCase()
		.replace( /ñ/g, N_TILDE_PLACEHOLDER )
		.normalize( 'NFD' )
		.replace( /\p{Mn}+/gu, '' )
		.replace( new RegExp( N_TILDE_PLACEHOLDER, 'g' ), 'ñ' )
		.replace( /\s+/g, ' ' )
		.trim();
}

/**
 * Palabras normalizadas de una búsqueda.
 *
 * @param {unknown} query Búsqueda escrita por el usuario.
 * @returns {string[]} Palabras, sin vacías.
 */
export function queryTokens( query ) {
	return normalizeText( query ).split( ' ' ).filter( Boolean );
}

/**
 * Texto normalizado donde buscar, a partir de uno o varios valores (por ejemplo código y nombre).
 *
 * @param {unknown|unknown[]} values Valores.
 * @returns {string} Texto normalizado.
 */
export function searchableText( values ) {
	return normalizeText( [ values ].flat().join( ' ' ) );
}

/**
 * Indica si un texto ya normalizado contiene todas las palabras. Permite normalizar una sola vez
 * cuando se filtran muchos registros.
 *
 * @param {string} haystack Texto normalizado (searchableText).
 * @param {string[]} tokens Palabras normalizadas (queryTokens).
 * @returns {boolean} Si hay coincidencia. Sin palabras, coincide con todo.
 */
export function matchesTokens( haystack, tokens ) {
	return tokens.every( ( token ) => haystack.includes( token ) );
}

/**
 * Indica si alguno de los valores contiene todas las palabras de la búsqueda, en cualquier orden.
 *
 * @param {unknown|unknown[]} values Texto o lista de textos donde buscar (por ejemplo código y nombre).
 * @param {string} query Búsqueda escrita por el usuario.
 * @returns {boolean} Si hay coincidencia. Una búsqueda vacía coincide con todo.
 */
export function matchesQuery( values, query ) {
	const tokens = queryTokens( query );
	return 0 === tokens.length || matchesTokens( searchableText( values ), tokens );
}
