/**
 * Monta los widgets de la intranet (`[data-ep-widget]`, ver templates/public/widget.php).
 *
 * El código de cada widget se descarga bajo demanda. Si el widget no existe o no se puede cargar, el
 * contenedor deja de anunciarse como «cargando» y muestra un aviso, en lugar de quedarse en «Cargando…»
 * para siempre (QA-011).
 */
import { __ } from '../core/i18n.js';

/**
 * @typedef {Object} WidgetModule
 * @property {(element: HTMLElement, props: Object, ctx: Object) => void} mount Monta el widget.
 */

/**
 * Opciones del widget, escritas por PHP como JSON.
 *
 * @param {HTMLElement} element Widget.
 * @returns {Object} Opciones.
 */
export function propsOf( element ) {
	try {
		const props = JSON.parse( element.dataset.epProps || '{}' );
		return null !== props && 'object' === typeof props && ! Array.isArray( props ) ? props : {};
	} catch {
		return {};
	}
}

/**
 * Reemplaza el contenido del widget por un aviso de error.
 *
 * @param {HTMLElement} element Widget.
 */
function showUnavailable( element ) {
	const message = element.ownerDocument.createElement( 'p' );
	message.className = 'ep-public__error';
	message.setAttribute( 'role', 'alert' );
	message.textContent = __( 'No se pudo cargar este contenido. Recarga la página e inténtalo de nuevo.', 'eventos-probolsas' );

	element.removeAttribute( 'aria-busy' );
	element.replaceChildren( message );
}

/**
 * Monta todos los widgets de un contenedor.
 *
 * @param {Iterable<HTMLElement>} elements Contenedores `[data-ep-widget]`.
 * @param {Record<string, () => Promise<WidgetModule>>} registry Cargadores por nombre de widget.
 * @param {Object} ctx Contexto compartido (configuración, formateador de fechas…).
 * @returns {Promise<void>} Termina cuando todos los widgets se montaron o fallaron.
 */
export function mountWidgets( elements, registry, ctx ) {
	const tasks = [ ...elements ].map( ( element ) => {
		const name = element.dataset.epWidget;
		const load = Object.hasOwn( registry, name ) ? registry[ name ] : null;

		if ( null === load ) {
			console.warn( `Eventos: widget desconocido «${ name }».` );
			showUnavailable( element );
			return Promise.resolve();
		}

		return load()
			.then( ( module ) => module.mount( element, propsOf( element ), ctx ) )
			.catch( ( error ) => {
				console.error( error );
				showUnavailable( element );
			} );
	} );

	return Promise.all( tasks ).then( () => undefined );
}
