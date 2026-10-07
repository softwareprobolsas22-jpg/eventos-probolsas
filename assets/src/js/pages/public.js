/**
 * Entrada de los shortcodes de la intranet (Assets::enqueue_public(), solo en las páginas que los usan).
 *
 * Carga los estilos aislados bajo `.ep-public` y monta cada widget de la página (`[data-ep-widget]`,
 * ver templates/public/widget.php). El código de cada widget se descarga bajo demanda; los widgets se
 * registran aquí a medida que se construyen (H-302 calendario, H-304 próximos eventos).
 */
import '@fortawesome/fontawesome-free/css/fontawesome.css';
import '@fortawesome/fontawesome-free/css/solid.css';
import '../../scss/vendor/bootstrap.scss';
import '../../scss/public.scss';
import { readConfig } from '../core/config.js';
import { createDateFormatter } from '../core/date.js';

/** Widgets por nombre (`data-ep-widget`). */
export const WIDGETS = {};

/**
 * Opciones del widget, escritas por PHP como JSON.
 *
 * @param {HTMLElement} element Widget.
 * @returns {Object} Opciones.
 */
function propsOf( element ) {
	try {
		return JSON.parse( element.dataset.epProps || '{}' );
	} catch {
		return {};
	}
}

const widgets = document.querySelectorAll( '[data-ep-widget]' );

if ( widgets.length > 0 ) {
	const config = readConfig();
	const ctx = { config, dates: createDateFormatter( config.ui ) };

	for ( const element of widgets ) {
		WIDGETS[ element.dataset.epWidget ]?.()
			.then( ( module ) => module.mount( element, propsOf( element ), ctx ) )
			.catch( ( error ) => {
				console.error( error );
				element.removeAttribute( 'aria-busy' );
			} );
	}
}
