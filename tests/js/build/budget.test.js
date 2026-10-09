/**
 * Presupuesto de peso de los assets compilados (R-17). assets/dist se versiona (ADR-0001), así que se
 * mide lo que se publica: lo que cada entrada descarga al cargar la página (ella y los chunks que importa
 * de forma estática, según el manifest de Vite), comprimido con gzip como lo sirve el servidor.
 *
 * Medición de H-004 (2026-10-07): CSS inicial ≈ 41 KB (Bootstrap encapsulado y Font Awesome, que incluye
 * la tabla de todos sus íconos); JS inicial < 2 KB. FullCalendar se carga bajo demanda con el widget del
 * calendario (H-302), fuera de la carga inicial.
 */
import { readFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';
import { describe, expect, it } from 'vitest';

const dist = new URL( '../../../assets/dist/', import.meta.url );
const manifest = JSON.parse( readFileSync( new URL( '.vite/manifest.json', dist ), 'utf8' ) );

/**
 * Límites en KB (gzip), con margen sobre la medición de H-004. El calendario se mide aparte: lo que
 * descarga al montarse, además de lo que ya cargó la página (H-302: JS 76 KB, CSS 1,5 KB; FullCalendar
 * inyecta su propio CSS desde el JS).
 */
const BUDGET = { initialJs: 30, initialCss: 45, calendarJs: 85, calendarCss: 5, upcomingJs: 10, upcomingCss: 3 };

const ENTRIES = [ 'assets/src/js/pages/admin.js', 'assets/src/js/pages/public.js' ];

const CALENDAR = 'assets/src/js/public/calendar.js';

const UPCOMING = 'assets/src/js/public/upcoming.js';

/**
 * Peso de un archivo con gzip, en KB.
 *
 * @param {string} file Ruta relativa a assets/dist.
 * @returns {number} KB.
 */
const gzipKb = ( file ) => gzipSync( readFileSync( new URL( file, dist ) ) ).length / 1024;

/**
 * Archivos que carga un chunk de inmediato: el suyo, su CSS y los de sus importaciones estáticas.
 *
 * @param {string} key Chunk del manifest.
 * @param {Set<string>} [seen] Chunks recorridos.
 * @returns {{ js: string[], css: string[] }} Archivos.
 */
function initialLoad( key, seen = new Set() ) {
	if ( seen.has( key ) ) {
		return { js: [], css: [] };
	}
	seen.add( key );
	const chunk = manifest[ key ];
	const result = { js: [ chunk.file ], css: [ ...( chunk.css ?? [] ) ] };
	for ( const imported of chunk.imports ?? [] ) {
		const child = initialLoad( imported, seen );
		result.js.push( ...child.js );
		result.css.push( ...child.css );
	}
	return result;
}

const total = ( files ) => [ ...new Set( files ) ].reduce( ( sum, file ) => sum + gzipKb( file ), 0 );

describe( 'presupuesto de peso de los assets (R-17)', () => {
	it.each( ENTRIES )( '%s: JS y CSS iniciales dentro del presupuesto', ( entry ) => {
		const load = initialLoad( entry );

		expect( total( load.js ) ).toBeLessThanOrEqual( BUDGET.initialJs );
		expect( total( load.css ) ).toBeLessThanOrEqual( BUDGET.initialCss );
	} );

	it( 'el calendario (FullCalendar y Tom Select) se descarga aparte, dentro de su presupuesto', () => {
		const page = initialLoad( 'assets/src/js/pages/public.js' );
		const widget = initialLoad( CALENDAR );
		const extra = ( files, loaded ) => files.filter( ( file ) => ! loaded.includes( file ) );

		expect( manifest[ 'assets/src/js/pages/public.js' ].dynamicImports ).toContain( CALENDAR );
		expect( page.js.some( ( file ) => file.includes( 'calendar' ) ) ).toBe( false );
		expect( total( extra( widget.js, page.js ) ) ).toBeLessThanOrEqual( BUDGET.calendarJs );
		expect( total( extra( widget.css, page.css ) ) ).toBeLessThanOrEqual( BUDGET.calendarCss );
	} );

	it( 'los próximos eventos se descargan aparte y sin FullCalendar', () => {
		const page = initialLoad( 'assets/src/js/pages/public.js' );
		const widget = initialLoad( UPCOMING );
		const extra = ( files, loaded ) => files.filter( ( file ) => ! loaded.includes( file ) );

		expect( manifest[ 'assets/src/js/pages/public.js' ].dynamicImports ).toContain( UPCOMING );
		expect( widget.js.some( ( file ) => file.includes( 'calendar' ) ) ).toBe( false );
		expect( total( extra( widget.js, page.js ) ) ).toBeLessThanOrEqual( BUDGET.upcomingJs );
		expect( total( extra( widget.css, page.css ) ) ).toBeLessThanOrEqual( BUDGET.upcomingCss );
	} );

	it( 'solo se publica la fuente sólida de Font Awesome (la única que usa el plugin)', () => {
		const fonts = [ ...new Set( Object.values( manifest ).flatMap( ( chunk ) => chunk.assets ?? [] ) ) ].filter( ( file ) => file.startsWith( 'fonts/' ) );

		expect( fonts ).toEqual( [ 'fonts/fa-solid-900.woff2' ] );
	} );
} );
