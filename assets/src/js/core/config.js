/**
 * Lectura de la configuración que PHP expone en `window.epConfig` (ver Assets::client_config()).
 *
 * Es la única puerta de entrada a los valores SSOT en el navegador: el JS nunca los redefine.
 */

/**
 * Devuelve una copia profunda e inmutable de la configuración.
 *
 * @param {unknown} [source=globalThis.epConfig] Configuración de origen (inyectable en pruebas).
 * @returns {Readonly<Record<string, unknown>>} Configuración congelada.
 * @throws {Error} Si la configuración no existe o no es un objeto.
 */
export function readConfig( source = globalThis.epConfig ) {
	if ( null === source || 'object' !== typeof source || Array.isArray( source ) ) {
		throw new Error( 'epConfig no está disponible: la pantalla debe encolar sus assets con Assets.php.' );
	}

	return deepFreeze( structuredClone( source ) );
}

/**
 * Congela un valor y todos sus descendientes.
 *
 * @template T
 * @param {T} value Valor a congelar.
 * @returns {T} El mismo valor, congelado.
 */
function deepFreeze( value ) {
	if ( null !== value && 'object' === typeof value ) {
		Object.values( value ).forEach( deepFreeze );
		Object.freeze( value );
	}

	return value;
}
