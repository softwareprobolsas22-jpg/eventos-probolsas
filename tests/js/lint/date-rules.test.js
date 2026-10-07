/**
 * La regla R-08 de ESLint debe detectar los patrones que en el legado mostraban el día anterior o
 * «hoy» = mañana en Colombia. Si alguien la debilita en eslint.config.js, esta prueba falla.
 */
import { fileURLToPath } from 'node:url';
import { ESLint } from 'eslint';
import { beforeAll, describe, expect, it } from 'vitest';

const eslint = new ESLint( { cwd: fileURLToPath( new URL( '../../../', import.meta.url ) ) } );

// La primera ejecución carga la configuración y los plugins de ESLint (puede tardar varios segundos).
beforeAll( () => eslint.lintText( '', { filePath: 'assets/src/js/core/ejemplo.js' } ), 30000 );

/**
 * Mensajes de ESLint para un fragmento de código de la interfaz.
 *
 * @param {string} code Código.
 * @returns {Promise<string[]>} Identificadores de regla con error.
 */
async function lint( code ) {
	const [ result ] = await eslint.lintText( code, { filePath: 'assets/src/js/core/ejemplo.js' } );
	return result.messages.map( ( message ) => `${ message.ruleId }: ${ message.message }` );
}

describe( 'R-08 y R-14/R-15 en eslint.config.js', () => {
	it.each( [
		[ 'toISOString', "export const hoy = new Date().toISOString().split( 'T' )[ 0 ];", /R-08/ ],
		[ "new Date('YYYY-MM-DD')", "export const dia = new Date( '2026-10-07' ).getDate();", /R-08/ ],
		[ 'Date.parse', "export const t = Date.parse( '2026-10-07' );", /R-08/ ],
		[ 'getTimezoneOffset', 'export const o = new Date().getTimezoneOffset();', /R-08/ ],
		[ 'innerHTML', "export function f( el, t ) { el.innerHTML = t; }", /R-15/ ],
		[ 'alert', "export function f() { alert( 'hola' ); }", /no-alert/ ],
	] )( 'detecta %s', async ( _label, code, expected ) => {
		expect( ( await lint( code ) ).join( '\n' ) ).toMatch( expected );
	} );

	it( 'permite fechas con hora y zona explícitas e Intl con timeZone', async () => {
		const messages = await lint(
			"export const m = new Date( '2026-10-02T03:30:00Z' );\n" +
				"export const f = new Intl.DateTimeFormat( 'es-CO', { timeZone: 'America/Bogota' } ).format( m );\n"
		);

		expect( messages ).toEqual( [] );
	} );
} );
