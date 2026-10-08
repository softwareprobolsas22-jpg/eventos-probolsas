/**
 * Entrada de los shortcodes de la intranet (Assets::enqueue_public(), solo en las páginas que los usan).
 *
 * Carga los estilos aislados bajo `.ep-public` y monta cada widget de la página (`[data-ep-widget]`,
 * ver templates/public/widget.php). Los widgets se registran aquí a medida que se construyen
 * (H-302 calendario, H-304 próximos eventos).
 */
import '@fortawesome/fontawesome-free/css/fontawesome.css';
import '@fortawesome/fontawesome-free/css/solid.css';
import 'tippy.js/dist/tippy.css';
import 'tippy.js/animations/shift-away-subtle.css';
import 'notyf/notyf.min.css';
import '../../scss/vendor/bootstrap.scss';
import '../../scss/public.scss';
import { readConfig } from '../core/config.js';
import { createDateFormatter } from '../core/date.js';
import { mountWidgets } from '../public/mount-widgets.js';
import { initTooltips } from '../ui/tooltip.js';

/** Widgets por nombre (`data-ep-widget`). */
export const WIDGETS = {};

const widgets = document.querySelectorAll( '[data-ep-widget]' );

if ( widgets.length > 0 ) {
	const config = readConfig();

	initTooltips( document.body );
	mountWidgets( widgets, WIDGETS, { config, dates: createDateFormatter( config.ui ) } );
}
