import { defineConfig } from 'vite';
import prefixSelector from 'postcss-prefix-selector';

/**
 * Entradas de Vite. Cada una genera `dist/js/<entrada>.js` (módulo ES) y, si importa estilos,
 * `dist/css/<entrada>.css`. Esos nombres no llevan hash: Assets.php versiona con la fecha de modificación.
 *
 * El CSS que comparten varias entradas (librerías) queda en otro archivo, con hash en el nombre:
 * Assets.php lo encola sin `?ver=` para que su dirección coincida con la que usa el cargador de Vite al
 * importar una pantalla, y así no se inserta dos veces.
 */
const ENTRIES = {
	admin: 'assets/src/js/pages/admin.js',
	public: 'assets/src/js/pages/public.js',
};

const ENTRY_STYLES = new Set( Object.keys( ENTRIES ).map( ( entry ) => `${ entry }.css` ) );

const FONT_FILE = /\.(woff2?|ttf|otf|eot)$/;

/**
 * Contenedores del plugin: `.ep-app` en wp-admin y `.ep-public` en los shortcodes de la intranet.
 * `:is()` toma la especificidad de una clase, suficiente para ganar a las reglas genéricas de wp-admin
 * (por ejemplo `.card`) sin `!important`.
 */
const SCOPE = ':is(.ep-app, .ep-public)';

/**
 * Bootstrap se compila en su propia hoja (assets/src/scss/vendor/bootstrap.scss) y todos sus selectores
 * quedan dentro de los contenedores del plugin: no toca wp-admin ni el tema, y el tema no lo pisa.
 * `:root`, `html` y `body` (variables CSS y reboot) pasan a ser el propio contenedor.
 *
 * @param {string} prefix Prefijo calculado por el plugin.
 * @param {string} selector Selector original.
 * @param {string} prefixedSelector Selector con el prefijo aplicado.
 * @returns {string} Selector final.
 */
function scopeBootstrap( prefix, selector, prefixedSelector ) {
	if ( /^(:root|html|body)$/.test( selector ) ) {
		return SCOPE;
	}

	if ( /^(html|body)\s/.test( selector ) ) {
		return selector.replace( /^(html|body)/, SCOPE );
	}

	return prefixedSelector;
}

/** Hoja de Bootstrap (la única a la que se aplican los plugins de encapsulación). */
const BOOTSTRAP_FILE = /vendor[\\/]bootstrap\.scss$/;

/**
 * El reboot de Bootstrap declara `body { margin: 0; background-color: … }`. Al encapsularlo, esa regla
 * cae sobre el propio contenedor: en wp-admin anularía los márgenes de `.wrap` y pintaría una caja blanca
 * sobre el panel; en la intranet anularía el margen y el fondo del tema (QA-009). Este plugin quita las
 * declaraciones de caja (margin, padding, background) de las reglas que apuntan solo al contenedor.
 * La tipografía y el color de texto se conservan.
 *
 * @type {import('postcss').Plugin}
 */
const keepContainerBox = {
	postcssPlugin: 'ep-keep-container-box',
	OnceExit( root ) {
		if ( ! BOOTSTRAP_FILE.test( root.source?.input?.file ?? '' ) ) {
			return;
		}

		root.walkRules( ( rule ) => {
			if ( rule.selectors.every( ( selector ) => SCOPE === selector ) ) {
				rule.walkDecls( /^(margin|padding|background)/, ( declaration ) => declaration.remove() );
			}
		} );
	},
};

/**
 * Carpeta de destino de cada asset según su tipo.
 *
 * @param {{ names?: string[], name?: string }} assetInfo Datos del asset emitido.
 * @returns {string} Patrón de nombre de archivo.
 */
function assetFileName( assetInfo ) {
	const name = assetInfo.names?.[ 0 ] ?? assetInfo.name ?? '';

	if ( name.endsWith( '.css' ) ) {
		return ENTRY_STYLES.has( name ) ? 'css/[name][extname]' : 'css/[name]-[hash][extname]';
	}

	if ( FONT_FILE.test( name ) ) {
		return 'fonts/[name][extname]';
	}

	return 'img/[name][extname]';
}

export default defineConfig( ( { mode } ) => {
	const isDevelopment = 'development' === mode;

	return {
		base: './',
		publicDir: false,
		css: {
			preprocessorOptions: {
				scss: {
					// Bootstrap 5.3 todavía usa @import y funciones globales de Sass: sus avisos de
					// obsolescencia no son accionables desde este proyecto.
					quietDeps: true,
					silenceDeprecations: [ 'import', 'global-builtin', 'color-functions', 'if-function' ],
				},
			},
			postcss: {
				plugins: [
					prefixSelector( {
						prefix: SCOPE,
						includeFiles: [ BOOTSTRAP_FILE ],
						transform: scopeBootstrap,
					} ),
					keepContainerBox,
				],
			},
		},
		build: {
			outDir: 'assets/dist',
			emptyOutDir: true,
			target: 'es2022',
			sourcemap: isDevelopment,
			minify: ! isDevelopment,
			modulePreload: { polyfill: false },
			// Assets.php lee el manifest para encolar, además del CSS de cada entrada, el de los chunks que
			// comparten (por ejemplo, Bootstrap y Font Awesome, que usan tanto admin como public).
			manifest: true,
			rolldownOptions: {
				input: ENTRIES,
				output: {
					entryFileNames: 'js/[name].js',
					chunkFileNames: 'js/chunks/[name]-[hash].js',
					assetFileNames: assetFileName,
				},
			},
		},
		test: {
			include: [ 'tests/js/**/*.test.js' ],
			environment: 'node',
			coverage: {
				provider: 'v8',
				include: [ 'assets/src/js/**/*.js' ],
				exclude: [ 'assets/src/js/pages/**' ],
				reporter: [ 'text-summary', 'html' ],
			},
		},
	};
} );
