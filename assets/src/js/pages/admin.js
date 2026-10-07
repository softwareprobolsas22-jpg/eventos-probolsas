/**
 * Entrada única de las pantallas de administración del plugin.
 *
 * Carga los estilos (Font Awesome, Bootstrap encapsulado y el sistema de diseño) y monta la pantalla
 * actual. Cada pantalla se descarga bajo demanda según `data-ep-screen` (ver templates/admin/layout.php);
 * las pantallas se registran aquí a medida que se construyen (H-104 tipos de evento, H-203 eventos).
 */
import '@fortawesome/fontawesome-free/css/fontawesome.css';
import '@fortawesome/fontawesome-free/css/solid.css';
import '../../scss/vendor/bootstrap.scss';
import '../../scss/admin.scss';
import { readConfig } from '../core/config.js';

/** Pantallas con interfaz JS, por slug (`data-ep-screen`). */
export const SCREENS = {};

const screen = document.querySelector( '[data-ep-screen]' );
const loadScreen = SCREENS[ screen?.dataset.epScreen ];

if ( loadScreen ) {
	const config = readConfig();

	loadScreen()
		.then( ( module ) => module.mount( screen, config ) )
		.catch( ( error ) => console.error( error ) );
}
