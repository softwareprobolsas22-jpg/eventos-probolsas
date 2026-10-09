/**
 * Widget `upcoming` de `[eventos_proximos]` (H-304): lista de los próximos eventos desde hoy en Colombia
 * (`GET /upcoming`). Cada evento abre el mismo modal de detalle del calendario (H-303).
 *
 * Las fechas se muestran sin desfases (R-08): el chip y la fecha larga salen de `start_date` como texto,
 * y «Hoy» se compara con el día de Bogotá, nunca anterior al `epConfig.today` del servidor.
 */
import '../../scss/widgets/upcoming.scss';
import { createApi } from '../core/api.js';
import { h, icon, uid } from '../core/dom.js';
import { __ } from '../core/i18n.js';
import { eventTypeBadge } from '../ui/badge.js';
import { loadError } from '../ui/load-state.js';
import { showToast } from '../ui/toast.js';
import { openEventModal } from './event-modal.js';

const SKELETON_ROWS = 3;

/**
 * Monta el widget (lo llama public/mount-widgets.js).
 *
 * @param {HTMLElement} element Contenedor `[data-ep-widget="upcoming"]`.
 * @param {{ limit?: number, types?: number[], title?: string }} props Opciones del shortcode.
 * @param {{ config: Object, dates: Object }} ctx Configuración y formateador de fechas.
 * @returns {Promise<void>} Termina cuando la lista se muestra o falla.
 */
export function mount( element, props, ctx ) {
	const api = createApi( ctx.config, { notify: showToast } );

	return createUpcoming( element, props, ctx, {
		api,
		onOpen: ( id, opener ) => openEventModal( { id, api, config: ctx.config, dates: ctx.dates, opener } ),
	} );
}

/**
 * Crea la lista.
 *
 * @param {HTMLElement} element Contenedor.
 * @param {{ limit?: number, types?: number[], title?: string }} props Opciones del shortcode.
 * @param {{ config: Object, dates: Object }} ctx Configuración y formateador de fechas.
 * @param {{ api: { get: Function }, onOpen: (id: number, opener: HTMLElement) => void, clock?: () => Date }} deps Dependencias.
 * @returns {Promise<void>} Termina cuando la lista se muestra o falla.
 */
export async function createUpcoming( element, props, { config, dates }, { api, onOpen, clock = () => new Date() } ) {
	const titleId = uid( 'ep-upcoming-title' );
	const title = 'string' === typeof props.title ? props.title.trim() : '';
	const list = h(
		'ul',
		{ class: 'ep-upcoming__list', attrs: { 'aria-busy': 'true' } },
		Array.from( { length: SKELETON_ROWS }, () => h( 'li', { class: 'ep-upcoming__item', attrs: { 'aria-hidden': 'true' } }, h( 'span', { class: 'ep-skeleton ep-upcoming__skeleton' } ) ) )
	);

	element.classList.add( 'ep-upcoming' );
	element.removeAttribute( 'aria-busy' );
	element.replaceChildren(
		h(
			'section',
			{ class: 'ep-upcoming__section', attrs: { 'aria-labelledby': title ? titleId : null, 'aria-label': title ? null : __( 'Próximos eventos', 'eventos-probolsas' ) } },
			title && h( 'h2', { class: 'ep-upcoming__title', id: titleId, text: title } ),
			list
		)
	);

	const params = new URLSearchParams();
	if ( Number.isInteger( props.limit ) ) {
		params.set( 'limit', String( props.limit ) );
	}
	( Array.isArray( props.types ) ? props.types : [] ).forEach( ( id ) => params.append( 'types[]', String( id ) ) );

	let events;
	try {
		const query = params.toString();
		events = await api.get( query ? `upcoming?${ query }` : 'upcoming' );
	} catch {
		list.replaceWith( loadError( __( 'No se pudieron cargar los próximos eventos', 'eventos-probolsas' ) ) );
		return;
	}

	if ( ! Array.isArray( events ) || 0 === events.length ) {
		list.replaceWith(
			h(
				'div',
				{ class: 'ep-empty-state ep-empty-state--compact', attrs: { role: 'status' } },
				h( 'span', { class: 'ep-empty-state__icon', attrs: { 'aria-hidden': 'true' } }, icon( 'fa-solid fa-calendar-check' ) ),
				h( 'p', { class: 'ep-empty-state__title', text: __( 'No hay eventos próximos', 'eventos-probolsas' ) } )
			)
		);
		return;
	}

	// «Hoy» de Bogotá, nunca anterior al del servidor (reloj del equipo atrasado).
	const today = [ config.today, dates.today( clock() ) ].sort().pop();
	list.removeAttribute( 'aria-busy' );
	list.replaceChildren( ...events.map( ( event ) => item( event ) ) );

	/**
	 * Un evento de la lista.
	 *
	 * @param {Object} event Evento de la API.
	 * @returns {HTMLElement} Elemento.
	 */
	function item( event ) {
		const parts = dates.calendarDateParts( event.start_date );
		const isToday = event.start_date === today;
		const when = event.start_time ? dates.formatCalendarTime( event.start_time ) : __( 'Todo el día', 'eventos-probolsas' );
		const longDate = isToday ? __( 'Hoy', 'eventos-probolsas' ) : dates.formatLongCalendarDate( event.start_date );

		const trigger = h(
			'button',
			{ type: 'button', class: 'ep-upcoming__event', on: { click: () => onOpen( event.id, trigger ) } },
			h(
				'span',
				{ class: [ 'ep-upcoming__date', isToday && 'is-today' ], attrs: { 'aria-hidden': 'true' } },
				h( 'span', { class: 'ep-upcoming__day', text: parts.day } ),
				h( 'span', { class: 'ep-upcoming__month', text: isToday ? __( 'Hoy', 'eventos-probolsas' ) : parts.month } )
			),
			h(
				'span',
				{ class: 'ep-upcoming__info' },
				h( 'span', { class: 'ep-upcoming__name ep-truncate', text: event.title } ),
				h(
					'span',
					{ class: 'ep-upcoming__meta' },
					h( 'span', { class: 'ep-visually-hidden', text: `${ longDate }, ` } ),
					h( 'span', { class: 'ep-upcoming__time' }, icon( 'fa-solid fa-clock' ), h( 'span', { text: when } ) ),
					event.type && eventTypeBadge( { ...event.type, maxWidth: '12rem' } )
				)
			)
		);

		return h( 'li', { class: 'ep-upcoming__item' }, trigger );
	}
}
