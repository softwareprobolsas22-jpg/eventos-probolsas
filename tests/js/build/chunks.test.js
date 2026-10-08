/**
 * Estructura de los chunks de JS compilados (QA-038).
 */
import { readFileSync, readdirSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

const dist = new URL( '../../../assets/dist/js/', import.meta.url );
const read = ( file ) => readFileSync( new URL( file, dist ), 'utf8' );
const chunks = readdirSync( new URL( 'chunks/', dist ) ).filter( ( file ) => file.endsWith( '.js' ) );

describe( 'chunks de JS compilados', () => {
	it( 'las entradas no exportan nada: ningún chunk depende de ellas', () => {
		for ( const entry of [ 'admin.js', 'public.js' ] ) {
			expect( read( entry ), entry ).not.toMatch( /\bexport\s*\{/ );
		}
	} );

	it( 'ningún chunk importa una entrada (WordPress la encola con ?ver=… y se cargaría dos veces)', () => {
		const offenders = chunks.filter( ( file ) => /from\s*["']\.\.\/(admin|public)\.js["']/.test( read( `chunks/${ file }` ) ) );

		expect( offenders ).toEqual( [] );
	} );

	it( 'las pantallas se descargan aparte de la entrada (R-17)', () => {
		expect( chunks.some( ( file ) => file.startsWith( 'events-' ) ) ).toBe( true );
		expect( chunks.some( ( file ) => file.startsWith( 'event-types-' ) ) ).toBe( true );
		expect( read( 'admin.js' ) ).toContain( 'import(' );
	} );
} );
