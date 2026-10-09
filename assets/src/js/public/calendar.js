/**
 * Widget `calendar` de `[eventos_calendario]` (H-302): FullCalendar 6 con vista de mes y de lista.
 *
 * Sin desfases (R-08), en cualquier zona horaria del equipo:
 * - Los eventos llegan del feed con fecha y hora de pared de Colombia, sin zona, y FullCalendar trabaja
 *   en `timeZone: 'UTC'` para no convertirlas (RL-01).
 * - Con esa opción su «hoy» sería la fecha UTC (después de las 7:00 p. m. ya es mañana): `now` es la
 *   hora de pared de Bogotá, nunca anterior a `epConfig.today` del servidor (RL-02). Si la página queda
 *   abierta, se recalcula al pasar la medianoche de Bogotá y al volver a la pestaña (QA-005).
 * - El rango que se pide a la API se arma con las partes UTC de las fechas de FullCalendar (`YYYY-MM-DD`).
 *
 * Este módulo y FullCalendar se descargan solo cuando la página tiene el shortcode (R-17).
 */
import '../../scss/widgets/calendar.scss';
import { Calendar } from '@fullcalendar/core';
import esLocale from '@fullcalendar/core/locales/es';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';
import { createApi } from '../core/api.js';
import { h, icon } from '../core/dom.js';
import { __ } from '../core/i18n.js';
import { showToast } from '../ui/toast.js';
import { openEventModal } from './event-modal.js';
import { createTypeFilter } from './type-filter.js';

/** Ancho de móvil (breakpoint `sm` del sistema de diseño): la lista es la vista inicial (R-11). */
export const MOBILE_QUERY = '(max-width: 575.98px)';

const DAY_MS = 24 * 60 * 60 * 1000;

/**
 * Dos dígitos.
 *
 * @param {number} value Número.
 * @returns {string} Texto.
 */
const pad = ( value ) => String( value ).padStart( 2, '0' );

/**
 * Fecha de calendario (`YYYY-MM-DD`) de una fecha de FullCalendar en modo UTC: sus partes UTC son la
 * fecha de pared, así que no depende de la zona del equipo.
 *
 * @param {Date} date Fecha de FullCalendar.
 * @returns {string} Fecha.
 */
export function calendarDate( date ) {
	return `${ date.getUTCFullYear() }-${ pad( date.getUTCMonth() + 1 ) }-${ pad( date.getUTCDate() ) }`;
}

/**
 * IDs válidos de una lista.
 *
 * @param {unknown} value Lista recibida.
 * @returns {number[]} IDs positivos.
 */
function ids( value ) {
	return ( Array.isArray( value ) ? value : [] ).map( Number ).filter( ( id ) => Number.isInteger( id ) && id > 0 );
}

/**
 * Monta el widget (lo llama public/mount-widgets.js).
 *
 * @param {HTMLElement} element Contenedor `[data-ep-widget="calendar"]`.
 * @param {{ types?: number[] }} props Opciones del shortcode: tipos permitidos (vacío = todos).
 * @param {{ config: Object, dates: Object }} ctx Configuración y formateador de fechas.
 * @returns {Object} Calendario montado.
 */
export function mount( element, props, ctx ) {
	const api = createApi( ctx.config, { notify: showToast } );

	return createCalendar( element, props, ctx, {
		api,
		onOpen: ( id, opener ) => openEventModal( { id, api, config: ctx.config, dates: ctx.dates, opener } ),
	} );
}

/**
 * Crea el calendario.
 *
 * @param {HTMLElement} element Contenedor.
 * @param {{ types?: number[] }} props Opciones del shortcode.
 * @param {{ config: Object, dates: Object }} ctx Configuración y formateador de fechas.
 * @param {Object} [deps] Dependencias inyectables en pruebas.
 * @param {typeof Calendar} [deps.CalendarClass] Clase de FullCalendar.
 * @param {{ get: Function }} [deps.api] Cliente de la API.
 * @param {(query: string) => { matches: boolean }} [deps.matchMedia] Consulta de medios.
 * @param {() => Date} [deps.clock] Momento actual.
 * @param {(id: number, trigger: HTMLElement|null) => void} [deps.onOpen] Abre el detalle de un evento.
 * @param {Function} [deps.typeFilter] Crea el filtro de tipos.
 * @returns {{ calendar: Object, refreshNow: () => void, destroy: () => void }} Calendario montado.
 */
export function createCalendar( element, props, { config, dates }, deps = {} ) {
	const {
		CalendarClass = Calendar,
		api = createApi( config, { notify: showToast } ),
		matchMedia = ( query ) => window.matchMedia( query ),
		clock = () => new Date(),
		onOpen = null,
		typeFilter = createTypeFilter,
		ResizeObserverClass = globalThis.ResizeObserver,
	} = deps;
	const allowed = ids( props.types );
	const doc = element.ownerDocument;
	let selected = [];
	let timer = null;

	/**
	 * «Ahora» en Bogotá, nunca antes del «hoy» del servidor (si el reloj del equipo está atrasado).
	 *
	 * @returns {string} `YYYY-MM-DDTHH:MM:SS`.
	 */
	const currentNow = () => {
		const wall = dates.wallTime( clock() );
		return wall.slice( 0, 10 ) < config.today ? `${ config.today }T00:00:00` : wall;
	};

	let now = currentNow();

	const filterHost = h( 'div', { class: 'ep-calendar__filters' } );
	const calendarHost = h( 'div', { class: 'ep-calendar__body' } );
	element.classList.add( 'ep-calendar' );
	element.replaceChildren( filterHost, calendarHost );

	const calendar = new CalendarClass( calendarHost, {
		plugins: [ dayGridPlugin, listPlugin ],
		locale: esLocale,
		timeZone: 'UTC',
		now,
		firstDay: Number( config.firstDay ) || 0,
		initialView: matchMedia( MOBILE_QUERY ).matches ? 'listMonth' : 'dayGridMonth',
		headerToolbar: { start: 'prev,next today', center: 'title', end: 'dayGridMonth,listMonth' },
		views: { listMonth: { buttonText: __( 'Lista', 'eventos-probolsas' ) } },
		height: 'auto',
		dayMaxEvents: 3,
		eventDisplay: 'block',
		// Sin hora de fin (v1), FullCalendar le da una hora de duración: un evento a las 11:30 p. m. se
		// dibujaría también en el día siguiente. Solo pasa al otro día si termina después de las 6:00 a. m.
		nextDayThreshold: '06:00:00',
		eventInteractive: null !== onOpen,
		navLinks: true,
		// El número del día abre la lista de ese día (RL-01: el 7 muestra los eventos del 7).
		navLinkDayClick: ( date ) => calendar.changeView( 'listDay', date ),
		eventTimeFormat: ( arg ) => dates.formatCalendarTime( `${ pad( arg.date.hour ) }:${ pad( arg.date.minute ) }` ),
		events: ( info, success, failure ) => {
			const params = new URLSearchParams( { start: calendarDate( info.start ), end: calendarDate( info.end ) } );
			( selected.length > 0 ? selected : allowed ).forEach( ( id ) => params.append( 'types[]', String( id ) ) );
			api.get( `calendar?${ params }` ).then( ( events ) => success( Array.isArray( events ) ? events : [] ), failure );
		},
		eventContent: ( arg ) => ( { domNodes: [ eventContent( arg ) ] } ),
		eventClick: ( info ) => {
			info.jsEvent.preventDefault();
			onOpen?.( Number( info.event.id ), info.el );
		},
		loading: ( isLoading ) => element.setAttribute( 'aria-busy', String( isLoading ) ),
	} );

	calendar.render();
	loadTypes();
	scheduleMidnight();
	doc.addEventListener( 'visibilitychange', onVisibilityChange );

	// FullCalendar mide el ancho al dibujar y solo se reajusta cuando cambia el tamaño de la ventana. El
	// contenedor también cambia sin eso (aparece la barra de desplazamiento de la página al cargar los
	// eventos, se abre un menú lateral del tema): la grilla quedaría más ancha o más angosta (R-13).
	let width = calendarHost.clientWidth;
	const resizer = ResizeObserverClass
		? new ResizeObserverClass( () => {
			if ( calendarHost.clientWidth !== width ) {
				width = calendarHost.clientWidth;
				calendar.updateSize();
			}
		} )
		: null;
	resizer?.observe( calendarHost );

	/**
	 * Contenido de un evento: ícono del tipo y título. En el mes, además, la hora y el título truncado
	 * con tooltip (R-21); en la lista la hora tiene su columna y el título se lee completo.
	 *
	 * @param {Object} arg Datos de FullCalendar.
	 * @returns {HTMLElement} Contenido.
	 */
	function eventContent( arg ) {
		const iconKey = arg.event.extendedProps.icon;
		const isList = arg.view.type.startsWith( 'list' );

		return h(
			'span',
			{ class: 'ep-calendar__event' },
			iconKey && h( 'span', { class: 'ep-calendar__event-icon', attrs: { 'aria-hidden': 'true' } }, icon( `fa-solid fa-${ iconKey } fa-fw` ) ),
			! isList && '' !== arg.timeText && h( 'span', { class: 'ep-calendar__event-time', text: arg.timeText } ),
			h( 'span', { class: [ 'ep-calendar__event-title', ! isList && 'ep-truncate' ], text: arg.event.title } )
		);
	}

	/**
	 * Carga los tipos para el filtro. Si el shortcode limita los tipos, solo se ofrecen esos.
	 */
	function loadTypes() {
		api.get( 'event-types', { silent: true } )
			.then( ( types ) => {
				const options = ( Array.isArray( types ) ? types : [] ).filter( ( type ) => 0 === allowed.length || allowed.includes( Number( type.id ) ) );
				if ( options.length > 1 ) {
					typeFilter( filterHost, options, ( chosen ) => {
						selected = chosen;
						calendar.refetchEvents();
					} );
				}
			} )
			// Sin filtro el calendario sigue funcionando con todos los tipos permitidos.
			.catch( () => filterHost.remove() );
	}

	/**
	 * Actualiza «hoy» si cambió el día en Bogotá (QA-005).
	 */
	function refreshNow() {
		const next = currentNow();
		if ( next.slice( 0, 10 ) !== now.slice( 0, 10 ) ) {
			calendar.setOption( 'now', next );
		}
		now = next;
		scheduleMidnight();
	}

	/**
	 * Programa la actualización para el primer segundo del día siguiente en Bogotá.
	 */
	function scheduleMidnight() {
		clearTimeout( timer );
		const [ hours, minutes, seconds ] = now.slice( 11 ).split( ':' ).map( Number );
		const elapsed = ( ( hours * 60 + minutes ) * 60 + seconds ) * 1000;
		timer = setTimeout( refreshNow, DAY_MS - elapsed + 1000 );
	}

	/**
	 * Los navegadores pausan los temporizadores de las pestañas ocultas: al volver, se revisa el día.
	 */
	function onVisibilityChange() {
		if ( 'visible' === doc.visibilityState ) {
			refreshNow();
		}
	}

	return {
		calendar,
		refreshNow,
		destroy() {
			clearTimeout( timer );
			doc.removeEventListener( 'visibilitychange', onVisibilityChange );
			resizer?.disconnect();
			calendar.destroy();
		},
	};
}
