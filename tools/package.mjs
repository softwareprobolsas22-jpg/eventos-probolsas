/**
 * Paquete `.zip` de release (H-404): `node tools/package.mjs [--check]`.
 *
 * Lo arma `git archive` desde el commit actual, así el contenido sale de `.gitattributes` (`export-ignore`)
 * y es reproducible: lo que está en git, sin lo de desarrollo. Antes de escribirlo verifica:
 * - que la versión coincida en la cabecera del plugin, `Plugin::VERSION` y `package.json`;
 * - que no haya cambios sin commitear (el `.zip` debe corresponder a un commit);
 * - que estén los archivos que WordPress necesita (incluido `assets/dist`, compilado, ADR-0001) y que no
 *   viaje nada de desarrollo (fuentes de assets, pruebas, documentación, legado, Composer, npm).
 *
 * Con `--check` solo verifica (sin escribir el `.zip`). Salida: `build/eventos-probolsas-<versión>.zip`.
 */
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath( new URL( '..', import.meta.url ) );
const git = ( ...args ) => execFileSync( 'git', args, { cwd: root, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 } );
const read = ( file ) => readFileSync( new URL( `../${ file }`, import.meta.url ), 'utf8' );

/** Rutas que deben estar en el paquete. */
export const REQUIRED = [
	'eventos-probolsas.php',
	'uninstall.php',
	'src/Core/Plugin.php',
	'src/Core/Autoloader.php',
	'config/ui.php',
	'templates/admin/layout.php',
	'templates/public/widget.php',
	'assets/dist/.vite/manifest.json',
	'assets/dist/js/admin.js',
	'assets/dist/js/public.js',
	'assets/dist/fonts/fa-solid-900.woff2',
];

/** Prefijos que nunca deben viajar en el paquete. */
export const FORBIDDEN = [ 'assets/src/', 'tests/', 'docs/', 'legacy/', 'tools/', '.github/', 'node_modules/', 'vendor/', 'build/', 'coverage/', 'composer.', 'package.json', 'package-lock.json', 'vite.config.js', 'eslint.config.js', 'playwright.config.js', 'phpunit', 'phpstan', 'phpcs', '.wp-env', 'wp-cli.yml', '.gitattributes', '.gitignore', '.stylelintrc.json', 'README.md' ];

/**
 * Versión declarada en cada lugar.
 *
 * @returns {{ header: string|undefined, constant: string|undefined, npm: string }} Versiones.
 */
export function versions() {
	return {
		header: /^\s*\*\s*Version:\s*(\S+)/m.exec( read( 'eventos-probolsas.php' ) )?.[ 1 ],
		constant: /const VERSION = '([^']+)'/.exec( read( 'src/Core/Plugin.php' ) )?.[ 1 ],
		npm: JSON.parse( read( 'package.json' ) ).version,
	};
}

/**
 * Archivos que `git archive` pondría en el paquete (respeta `export-ignore`).
 *
 * @returns {string[]} Rutas relativas.
 */
export function packagedFiles() {
	const tar = execFileSync( 'git', [ 'archive', '--format=tar', 'HEAD' ], { cwd: root, maxBuffer: 256 * 1024 * 1024 } );
	const files = [];
	for ( let offset = 0; offset + 512 <= tar.length; ) {
		const header = tar.subarray( offset, offset + 512 );
		const name = header.subarray( 0, 100 ).toString( 'utf8' ).replace( /\0.*$/s, '' );
		if ( '' === name ) {
			break;
		}
		const prefix = header.subarray( 345, 500 ).toString( 'utf8' ).replace( /\0.*$/s, '' );
		const size = parseInt( header.subarray( 124, 136 ).toString( 'utf8' ).replace( /\0.*$/s, '' ).trim() || '0', 8 );
		const type = String.fromCharCode( header[ 156 ] );
		if ( '0' === type || '\0' === type ) {
			files.push( prefix ? `${ prefix }/${ name }` : name );
		}
		offset += 512 + Math.ceil( size / 512 ) * 512;
	}
	return files.filter( ( file ) => 'pax_global_header' !== file );
}

/**
 * Problemas del paquete (vacío si está bien).
 *
 * @param {string[]} files Archivos del paquete.
 * @param {{ header?: string, constant?: string, npm: string }} found Versiones.
 * @returns {string[]} Problemas.
 */
export function problems( files, found ) {
	const list = [];
	if ( ! found.header || found.header !== found.constant || found.header !== found.npm ) {
		list.push( `Versiones distintas: cabecera ${ found.header }, Plugin::VERSION ${ found.constant }, package.json ${ found.npm }` );
	}
	for ( const required of REQUIRED ) {
		if ( ! files.includes( required ) ) {
			list.push( `Falta en el paquete: ${ required }` );
		}
	}
	for ( const file of files ) {
		if ( FORBIDDEN.some( ( prefix ) => file.startsWith( prefix ) ) ) {
			list.push( `No debe ir en el paquete: ${ file }` );
		}
	}
	return list;
}

if ( process.argv[ 1 ] && fileURLToPath( import.meta.url ) === process.argv[ 1 ] ) {
	const checkOnly = process.argv.includes( '--check' );
	const found = versions();
	const files = packagedFiles();
	const issues = problems( files, found );

	if ( ! checkOnly && '' !== git( 'status', '--porcelain', '--untracked-files=no' ).trim() ) {
		issues.push( 'Hay cambios sin commitear: el .zip debe corresponder a un commit.' );
	}

	if ( issues.length > 0 ) {
		console.error( issues.join( '\n' ) );
		process.exit( 1 );
	}

	console.log( `Paquete ${ found.header }: ${ files.length } archivos, sin herramientas de desarrollo.` );

	if ( ! checkOnly ) {
		mkdirSync( new URL( '../build/', import.meta.url ), { recursive: true } );
		const output = `build/eventos-probolsas-${ found.header }.zip`;
		git( 'archive', '--format=zip', '--prefix=eventos-probolsas/', '-o', output, 'HEAD' );
		console.log( `Escrito ${ output } (commit ${ git( 'rev-parse', '--short', 'HEAD' ).trim() }).` );
	}
}
