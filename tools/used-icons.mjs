/**
 * Íconos de Font Awesome que usa el plugin (QA-033).
 *
 * Font Awesome trae una regla por ícono (unos 2.000, ≈ 15 KB con gzip) y el plugin usa alrededor de cien.
 * vite.config.js conserva en el CSS compilado solo los que devuelve esta función, y
 * tests/js/build/icons.test.js verifica que no falte ninguno.
 *
 * Fuentes:
 * - `fa-<nombre>` escrito en el código (JS, SCSS, plantillas y clases PHP).
 * - Las claves de config/icons.php: el selector de íconos y los badges las usan como `fa-${ clave }`.
 */
import { readdirSync, readFileSync } from 'node:fs';
import { extname, join } from 'node:path';

/** Carpetas con código del plugin, relativas a la raíz. */
const SOURCE_DIRS = [ 'assets/src', 'templates', 'src', 'config' ];

const SOURCE_EXTENSIONS = new Set( [ '.js', '.scss', '.php' ] );

/** `fa-nombre` en el código; el nombre no puede terminar en guion (`fa-${ … }` no cuenta). */
const ICON_CLASS = /\bfa-([a-z0-9]+(?:-[a-z0-9]+)*)(?![\w-])/g;

/** Claves del catálogo: `'cake-candles' => [ … ]`. */
const CATALOG_KEY = /^\s*'([a-z0-9-]+)'\s*=>\s*\[/gm;

/**
 * Archivos de código dentro de una carpeta, recursivamente.
 *
 * @param {string} dir Carpeta.
 * @returns {string[]} Rutas.
 */
function sourceFiles( dir ) {
	return readdirSync( dir, { withFileTypes: true } ).flatMap( ( entry ) => {
		const path = join( dir, entry.name );
		if ( entry.isDirectory() ) {
			return sourceFiles( path );
		}
		return SOURCE_EXTENSIONS.has( extname( entry.name ) ) ? [ path ] : [];
	} );
}

/**
 * Nombres de los íconos usados (sin el prefijo `fa-`). Incluye también las clases de utilidad
 * (`solid`, `spin`, `fw`…), que no son íconos y no afectan el resultado.
 *
 * @param {string} root Raíz del plugin.
 * @returns {Set<string>} Nombres.
 */
export function usedIcons( root ) {
	const names = new Set();

	for ( const file of SOURCE_DIRS.flatMap( ( dir ) => sourceFiles( join( root, dir ) ) ) ) {
		for ( const [ , name ] of readFileSync( file, 'utf8' ).matchAll( ICON_CLASS ) ) {
			names.add( name );
		}
	}

	for ( const [ , key ] of readFileSync( join( root, 'config/icons.php' ), 'utf8' ).matchAll( CATALOG_KEY ) ) {
		names.add( key );
	}

	return names;
}
