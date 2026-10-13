/**
 * Copiar texto al portapapeles.
 *
 * `navigator.clipboard` solo existe en contextos seguros (HTTPS o localhost). Si no está o falla, se usa
 * un <textarea> temporal con `document.execCommand( 'copy' )`, que sigue funcionando en los navegadores
 * que usan los colaboradores.
 */

/**
 * Copia un texto.
 *
 * @param {string} text Texto.
 * @param {{ clipboard?: { writeText: (text: string) => Promise<void> }|null }} [options] Portapapeles (inyectable en pruebas; null = no disponible).
 * @returns {Promise<boolean>} Si se copió.
 */
export async function copyText( text, { clipboard = globalThis.navigator?.clipboard } = {} ) {
	if ( clipboard?.writeText ) {
		try {
			await clipboard.writeText( text );
			return true;
		} catch {
			// Sin permiso (por ejemplo, dentro de un iframe): se intenta a la antigua.
		}
	}

	return copyWithSelection( text );
}

/**
 * Copia seleccionando el texto en un <textarea> fuera de la vista.
 *
 * @param {string} text Texto.
 * @returns {boolean} Si se copió.
 */
function copyWithSelection( text ) {
	const field = document.createElement( 'textarea' );
	field.value = text;
	field.setAttribute( 'readonly', '' );
	field.setAttribute( 'aria-hidden', 'true' );
	Object.assign( field.style, { position: 'fixed', top: '0', left: '-9999px', opacity: '0' } );

	const previousFocus = document.activeElement;
	document.body.append( field );
	field.select();

	let copied;
	try {
		copied = 'function' === typeof document.execCommand && document.execCommand( 'copy' );
	} catch {
		copied = false;
	}

	field.remove();
	previousFocus?.focus?.();

	return copied;
}
