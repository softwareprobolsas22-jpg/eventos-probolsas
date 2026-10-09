/**
 * Formato de fechas en la zona horaria de Colombia.
 *
 * Es la versión JS de DateFormatter.php: usa los mismos formatos de config/ui.php (sintaxis de PHP) y
 * comparte casos de prueba (tests/fixtures/date-formatting.json). Hay dos clases de fecha:
 *
 * - Momentos de auditoría (creado, actualizado): llegan de la API en ISO 8601 con desplazamiento y se
 *   muestran en la zona configurada.
 * - Fechas y horas de calendario (cuándo ocurre un evento): son la hora de pared de Colombia y se tratan
 *   como texto, sin `Date` ni conversión de zona (R-08). Así un evento del 7 de octubre a las 3:00 p. m.
 *   nunca aparece en otro día ni a otra hora, sin importar la zona del equipo del usuario.
 */

/**
 * Códigos de formato de PHP soportados. Cualquier otro carácter se copia tal cual.
 *
 * @type {Record<string, (parts: Record<string, string>) => string>}
 */
const FORMAT_TOKENS = {
	d: ( parts ) => parts.day,
	m: ( parts ) => parts.month,
	Y: ( parts ) => parts.year,
	H: ( parts ) => parts.hour,
	h: ( parts ) => String( Number( parts.hour ) % 12 || 12 ).padStart( 2, '0' ),
	i: ( parts ) => parts.minute,
};

const CALENDAR_DATE = /^(\d{4})-(\d{2})-(\d{2})$/;
const CALENDAR_TIME = /^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/;

/**
 * Aplica un formato con sintaxis de PHP a las partes de una fecha.
 *
 * @param {string} format Formato, por ejemplo `d/m/Y`.
 * @param {Record<string, string>} parts Partes de la fecha.
 * @returns {string} Fecha formateada.
 */
function applyFormat( format, parts ) {
	return [ ...format ].map( ( char ) => ( FORMAT_TOKENS[ char ] ? FORMAT_TOKENS[ char ]( parts ) : char ) ).join( '' );
}

/**
 * Indica si año, mes y día forman una fecha real (rechaza, por ejemplo, el 30 de febrero).
 *
 * @param {number} year Año.
 * @param {number} month Mes (1-12).
 * @param {number} day Día.
 * @returns {boolean} Si existe.
 */
function isRealDate( year, month, day ) {
	const leap = ( 0 === year % 4 && 0 !== year % 100 ) || 0 === year % 400;
	const days = [ 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];
	return year > 0 && month >= 1 && month <= 12 && day >= 1 && day <= days[ month - 1 ];
}

/**
 * Crea el formateador a partir de `epConfig.ui`.
 *
 * @param {{ timezone: string, date_format: string, time_format: string, meridiem: { am: string, pm: string } }} ui Configuración.
 * @returns {{ formatDate: Function, formatDateTime: Function, formatCalendarDate: Function, formatCalendarTime: Function, today: Function }} Formateador.
 */
export function createDateFormatter( ui ) {
	const { timezone, date_format: dateFormat, time_format: timeFormat, meridiem } = ui;

	const intl = new Intl.DateTimeFormat( 'en-US', {
		timeZone: timezone,
		year: 'numeric',
		month: '2-digit',
		day: '2-digit',
		hour: '2-digit',
		minute: '2-digit',
		second: '2-digit',
		hourCycle: 'h23',
	} );

	// Fechas de calendario largas: se formatean en UTC porque se construyen con Date.UTC (sin zona del equipo).
	const longDate = new Intl.DateTimeFormat( ui.locale || 'es-CO', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' } );

	/**
	 * Partes de un momento en la zona configurada.
	 *
	 * @param {string|Date} value Fecha ISO 8601 (con desplazamiento) o Date.
	 * @returns {Record<string, string>} Partes.
	 */
	function partsOf( value ) {
		if ( 'string' === typeof value && ! /[zZ]|[+-]\d{2}:\d{2}$/.test( value ) ) {
			throw new TypeError( `La fecha debe incluir zona horaria: ${ value }` );
		}
		const date = value instanceof Date ? value : new Date( value );
		if ( Number.isNaN( date.getTime() ) ) {
			throw new TypeError( `Fecha no válida: ${ value }` );
		}
		return Object.fromEntries( intl.formatToParts( date ).map( ( { type, value: part } ) => [ type, part ] ) );
	}

	/**
	 * Hora en 12 h con su indicador, por ejemplo `03:00 p. m.`.
	 *
	 * @param {Record<string, string>} parts Partes con `hour` (24 h) y `minute`.
	 * @returns {string} Hora formateada.
	 */
	function clock( parts ) {
		const label = Number( parts.hour ) < 12 ? meridiem.am : meridiem.pm;
		return `${ applyFormat( timeFormat, parts ) } ${ label }`;
	}

	return {
		/**
		 * Fecha local de un momento de auditoría. Ejemplo: `01/10/2026`.
		 *
		 * @param {string|Date} value Fecha ISO 8601 con desplazamiento, como la entrega la API.
		 * @returns {string} Fecha formateada.
		 */
		formatDate( value ) {
			return applyFormat( dateFormat, partsOf( value ) );
		},

		/**
		 * Fecha y hora local de un momento de auditoría. Ejemplo: `01/10/2026 11:30 p. m.`.
		 *
		 * @param {string|Date} value Fecha ISO 8601 con desplazamiento, como la entrega la API.
		 * @returns {string} Fecha y hora formateadas.
		 */
		formatDateTime( value ) {
			const parts = partsOf( value );
			return `${ applyFormat( dateFormat, parts ) } ${ clock( parts ) }`;
		},

		/**
		 * Fecha de calendario (el día de un evento). No aplica conversión de zona.
		 *
		 * @param {string} value Fecha `YYYY-MM-DD`.
		 * @returns {string} Fecha formateada, por ejemplo `07/10/2026`.
		 */
		formatCalendarDate( value ) {
			const match = CALENDAR_DATE.exec( value );
			if ( ! match || ! isRealDate( Number( match[ 1 ] ), Number( match[ 2 ] ), Number( match[ 3 ] ) ) ) {
				throw new TypeError( `Fecha no válida: ${ value }` );
			}
			return applyFormat( dateFormat, { year: match[ 1 ], month: match[ 2 ], day: match[ 3 ] } );
		},

		/**
		 * Fecha de calendario larga, con el día de la semana. Ejemplo: `2026-10-07` → `miércoles, 7 de
		 * octubre de 2026`. Arma la fecha con `Date.UTC` y la formatea en UTC: nunca cambia de día (R-08).
		 *
		 * @param {string} value Fecha `YYYY-MM-DD`.
		 * @returns {string} Fecha larga.
		 */
		formatLongCalendarDate( value ) {
			const match = CALENDAR_DATE.exec( value );
			if ( ! match || ! isRealDate( Number( match[ 1 ] ), Number( match[ 2 ] ), Number( match[ 3 ] ) ) ) {
				throw new TypeError( `Fecha no válida: ${ value }` );
			}
			return longDate.format( Date.UTC( Number( match[ 1 ] ), Number( match[ 2 ] ) - 1, Number( match[ 3 ] ) ) );
		},

		/**
		 * Hora de calendario en 12 h. Ejemplo: `15:00` → `03:00 p. m.`. No aplica conversión de zona.
		 *
		 * @param {string} value Hora `HH:MM` (campo del formulario) o `HH:MM:SS` (API).
		 * @returns {string} Hora formateada.
		 */
		formatCalendarTime( value ) {
			const match = CALENDAR_TIME.exec( value );
			if ( ! match ) {
				throw new TypeError( `Hora no válida: ${ value }` );
			}
			return clock( { hour: match[ 1 ], minute: match[ 2 ] } );
		},

		/**
		 * Fecha de hoy en la zona configurada (`YYYY-MM-DD`). Al cargar la página, `epConfig.today` es la
		 * referencia; esta función sirve para actualizarla si la página queda abierta después de la
		 * medianoche (QA-005). Evita `new Date().toISOString()`, que usa UTC: a las 8 p. m. en Colombia ya
		 * sería el día siguiente.
		 *
		 * @param {Date} [now] Momento de referencia (inyectable en pruebas).
		 * @returns {string} Fecha.
		 */
		today( now = new Date() ) {
			const parts = partsOf( now );
			return `${ parts.year }-${ parts.month }-${ parts.day }`;
		},

		/**
		 * Fecha y hora de pared actuales en la zona configurada, sin zona (`YYYY-MM-DDTHH:MM:SS`). Es el
		 * `now` de FullCalendar, que trabaja en `timeZone: 'UTC'` para no convertir las horas de los
		 * eventos: sin esto, su «hoy» sería la fecha UTC (RL-02).
		 *
		 * @param {Date} [now] Momento de referencia (inyectable en pruebas).
		 * @returns {string} Fecha y hora.
		 */
		wallTime( now = new Date() ) {
			const parts = partsOf( now );
			return `${ parts.year }-${ parts.month }-${ parts.day }T${ parts.hour }:${ parts.minute }:${ parts.second }`;
		},
	};
}
