import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import prefixSelector from 'postcss-prefix-selector';
import { usedIcons } from './tools/used-icons.mjs';

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

/**
 * Módulos que importan las entradas (pages/*.js) y también las pantallas: forman el chunk `runtime`
 * (ver codeSplitting). tests/js/build/chunks.test.js verifica que ningún chunk importe una entrada.
 */
const ENTRY_RUNTIME = /[\\/](assets[\\/]src[\\/]js[\\/](core[\\/](config|dom|i18n|timing)|ui[\\/](toast|tooltip))\.js$|node_modules[\\/](notyf|tippy\.js|@popperjs)[\\/])/;

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

/** Hoja de Font Awesome con la tabla de íconos. */
const FONT_AWESOME_FILE = /@fortawesome[\\/]fontawesome-free[\\/]css[\\/]fontawesome\.css$/;

/**
 * Regla de un ícono: `.fa-cake-candles { --fa: "\f1fd"; }`. Solo la propiedad `--fa` exacta: las utilidades
 * que declaran otras variables (`.fa-fw { --fa-width: … }`, `.fa-spin-reverse { --fa-animation-direction: … }`)
 * se conservan.
 *
 * @param {import('postcss').Rule} rule Regla.
 * @returns {boolean} Si declara un ícono.
 */
const isIconRule = ( rule ) =>
	rule.nodes.length > 0 &&
	rule.nodes.every( ( node ) => 'decl' === node.type && '--fa' === node.prop ) &&
	rule.selectors.every( ( selector ) => /^\.fa-[a-z0-9-]+$/.test( selector ) );

/**
 * Font Awesome declara una regla por cada uno de sus ~2.000 íconos (≈ 15 KB con gzip) y el plugin usa
 * alrededor de cien: el CSS inicial de wp-admin agotaba el presupuesto de 45 KB (QA-033). Este plugin
 * conserva solo los íconos que aparecen en el código o en config/icons.php (tools/used-icons.mjs); las
 * clases de estilo y utilidad (`fa-solid`, `fa-spin`, tamaños…) no se tocan. Un ícono nuevo exige volver
 * a compilar, y el CI lo detecta porque compara assets/dist con un build limpio.
 *
 * @type {import('postcss').Plugin}
 */
const keepUsedIcons = {
	postcssPlugin: 'ep-keep-used-icons',
	OnceExit( root ) {
		if ( ! FONT_AWESOME_FILE.test( root.source?.input?.file ?? '' ) ) {
			return;
		}

		const used = usedIcons( fileURLToPath( new URL( '.', import.meta.url ) ) );
		root.walkRules( ( rule ) => {
			if ( ! isIconRule( rule ) ) {
				return;
			}
			const kept = rule.selectors.filter( ( selector ) => used.has( selector.replace( /^\.fa-/, '' ) ) );
			if ( 0 === kept.length ) {
				rule.remove();
			} else {
				rule.selectors = kept;
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
					keepUsedIcons,
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
					// Lo que usan a la vez las entradas y las pantallas (toasts, tooltips, i18n, DOM y sus librerías)
					// va a su propio chunk. Sin esto, Rolldown lo deja dentro de la entrada y las pantallas importan
					// `../admin.js`: WordPress encola la entrada con `?ver=…`, así que el navegador la cargaría dos
					// veces (tooltips y pantalla montados dos veces). El resto del kit se reparte entre los chunks de
					// cada pantalla y solo se descarga al abrirla (R-17).
					codeSplitting: {
						groups: [ { name: 'runtime', test: ENTRY_RUNTIME } ],
					},
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
				// DoD 3 (QA-018): el CI falla si la cobertura del JS baja del 80 %.
				thresholds: { statements: 80, branches: 80, functions: 80, lines: 80 },
			},
		},
	};
} );
