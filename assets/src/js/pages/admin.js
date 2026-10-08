/**
 * Entrada única de las pantallas de administración del plugin.
 *
 * Carga los estilos (Font Awesome, Tippy, Notyf, Bootstrap encapsulado y el sistema de diseño), activa
 * los tooltips y monta la pantalla actual. Cada pantalla se descarga bajo demanda según `data-ep-screen` (ver templates/admin/layout.php);
 * las pantallas se registran aquí a medida que se construyen (H-104 tipos de evento, H-203 eventos).
 */
import '@fortawesome/fontawesome-free/css/fontawesome.css';
import '@fortawesome/fontawesome-free/css/solid.css';
import 'tippy.js/dist/tippy.css';
import 'tippy.js/animations/shift-away-subtle.css';
import 'notyf/notyf.min.css';
import '../../scss/vendor/bootstrap.scss';
import '../../scss/admin.scss';
import { readConfig } from '../core/config.js';
import { __ } from '../core/i18n.js';
import { toast } from '../ui/toast.js';
import { initTooltips } from '../ui/tooltip.js';

/** Pantallas con interfaz JS, por slug (`data-ep-screen`). */
export const SCREENS = {
	'eventos-probolsas-tipos': () => import( '../screens/event-types.js' ),
};

initTooltips( document.body );

const screen = document.querySelector( '[data-ep-screen]' );
const loadScreen = SCREENS[ screen?.dataset.epScreen ];

if ( loadScreen ) {
	const config = readConfig();

	loadScreen()
		.then( ( module ) => module.mount( screen, config ) )
		.catch( ( error ) => {
			console.error( error );
			toast.error( __( 'No se pudo cargar la pantalla. Recarga la página e inténtalo de nuevo.', 'eventos-probolsas' ) );
		} );
}
