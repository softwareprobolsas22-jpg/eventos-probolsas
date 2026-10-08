/**
 * Utilidades de color para los badges de tipo de evento, cuyo color elige el usuario.
 * Usa las fórmulas de luminancia y contraste de WCAG 2.1.
 */

const HEX_COLOR = /^#[0-9A-Fa-f]{6}$/;

/**
 * Indica si un texto es un color hexadecimal #RRGGBB.
 *
 * @param {unknown} value Valor.
 * @returns {boolean} Si es válido.
 */
export function isHexColor( value ) {
	return 'string' === typeof value && HEX_COLOR.test( value );
}

/**
 * Luminancia relativa de un color (0 = negro, 1 = blanco).
 *
 * @param {string} hex Color #RRGGBB.
 * @returns {number} Luminancia.
 */
export function relativeLuminance( hex ) {
	const [ red, green, blue ] = [ 1, 3, 5 ].map( ( start ) => {
		const channel = parseInt( hex.slice( start, start + 2 ), 16 ) / 255;
		return channel <= 0.04045 ? channel / 12.92 : ( ( channel + 0.055 ) / 1.055 ) ** 2.4;
	} );
	return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

/**
 * Relación de contraste entre dos colores (1 a 21).
 *
 * @param {string} first Color #RRGGBB.
 * @param {string} second Color #RRGGBB.
 * @returns {number} Contraste.
 */
export function contrastRatio( first, second ) {
	const [ lighter, darker ] = [ relativeLuminance( first ), relativeLuminance( second ) ].sort( ( a, b ) => b - a );
	return ( lighter + 0.05 ) / ( darker + 0.05 );
}

/**
 * Mejor contraste posible entre el fondo y los dos tonos de texto. Gemelo de ColorContrast::best_ratio().
 *
 * @param {string} background Color de fondo #RRGGBB.
 * @returns {number} Contraste.
 */
export function bestContrastRatio( background ) {
	return Math.max( contrastRatio( background, '#FFFFFF' ), contrastRatio( background, '#000000' ) );
}

/**
 * Tono de texto más legible sobre un fondo: 'light' (#FFFFFF) u 'dark' (#000000). Gemelo de
 * ColorContrast::readable_tone(); la API ya entrega este valor como `text_tone`, aquí solo se usa
 * para la vista previa mientras se elige el color.
 *
 * @param {string} background Color de fondo #RRGGBB.
 * @returns {'light'|'dark'} Tono del texto. Con un color incompleto devuelve 'light'.
 */
export function readableTextTone( background ) {
	if ( ! isHexColor( background ) ) {
		return 'light';
	}
	return contrastRatio( background, '#FFFFFF' ) >= contrastRatio( background, '#000000' ) ? 'light' : 'dark';
}
