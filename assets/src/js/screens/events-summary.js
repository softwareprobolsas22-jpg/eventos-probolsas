/**
 * Resumen de la pantalla «Eventos» (H-206, D-17): tarjetas con los eventos de hoy, de los próximos 30
 * días y el total, y el conteo por tipo. Cada tarjeta es un atajo de filtro de la tabla.
 *
 * Usa `GET /dashboard`. Las fechas del rango las calcula el servidor con el día de Colombia
 * (`date_from`, `date_to`): el navegador no hace cuentas de fechas (R-08). Si la API falla, el resumen
 * se oculta sin bloquear la tabla.
 */
import { h, icon } from '../core/dom.js';
import { __, _n, sprintf } from '../core/i18n.js';
import { eventTypeBadge } from '../ui/badge.js';

const NUMBER = new Intl.NumberFormat( 'es-CO' );

/**
 * @typedef {Object} SummaryFilter Filtros que aplica una tarjeta (los de la barra de filtros).
 * @property {string} [type] ID del tipo o vacío.
 * @property {{ from: string, to: string }} [dates] Rango.
 */

/**
 * Crea el resumen.
 *
 * @param {HTMLElement} container Contenedor.
 * @param {{ api: { get: Function }, onFilter: (filter: SummaryFilter) => void }} options
 *   onFilter: aplica el filtro de la tarjeta a la tabla.
 * @returns {{ element: HTMLElement, refresh: (types?: Promise<Object[]>|Object[]) => Promise<void> }} Resumen.
 */
export function createEventsSummary( container, { api, onFilter } ) {
	const element = h( 'section', { class: 'ep-summary', attrs: { 'aria-label': __( 'Resumen de eventos', 'eventos-probolsas' ), 'aria-busy': 'true' } } );
	let types = [];
	let requestId = 0;

	element.append( ...skeleton() );
	container.append( element );

	/**
	 * Vuelve a pedir las cifras (al montar y después de crear, editar o eliminar).
	 *
	 * @param {Promise<Object[]>|Object[]} [typesSource] Tipos de la pantalla (para nombre, color e ícono).
	 * @returns {Promise<void>} Termina al mostrarlas.
	 */
	async function refresh( typesSource ) {
		const current = ++requestId;
		element.setAttribute( 'aria-busy', 'true' );

		let data;
		try {
			[ data ] = await Promise.all( [ api.get( 'dashboard', { silent: true } ), typesSource ? Promise.resolve( typesSource ).then( ( list ) => ( types = Array.isArray( list ) ? list : [] ) ) : null ] );
		} catch {
			if ( current === requestId ) {
				element.hidden = true;
			}
			return;
		}

		if ( current !== requestId ) {
			return;
		}

		element.hidden = false;
		element.removeAttribute( 'aria-busy' );
		element.replaceChildren( cards( data ), byType( data ) );
	}

	/**
	 * Tarjetas principales.
	 *
	 * @param {{ today: number, next_30_days: number, total: number, date_from: string, date_to: string }} data Cifras.
	 * @returns {HTMLElement} Tarjetas.
	 */
	function cards( data ) {
		const today = { from: data.date_from, to: data.date_from };
		const next = { from: data.date_from, to: data.date_to };

		return h(
			'div',
			{ class: 'ep-summary__cards' },
			card( {
				iconClass: 'fa-solid fa-calendar-day',
				value: data.today,
				label: __( 'Hoy', 'eventos-probolsas' ),
				/* translators: %s: cantidad de eventos. */
				action: sprintf( _n( '%s evento hoy. Ver en la tabla.', '%s eventos hoy. Ver en la tabla.', data.today, 'eventos-probolsas' ), NUMBER.format( data.today ) ),
				filter: { type: '', dates: today },
				highlight: data.today > 0,
			} ),
			card( {
				iconClass: 'fa-solid fa-calendar-week',
				value: data.next_30_days,
				label: __( 'Próximos 30 días', 'eventos-probolsas' ),
				/* translators: %s: cantidad de eventos. */
				action: sprintf( _n( '%s evento en los próximos 30 días. Ver en la tabla.', '%s eventos en los próximos 30 días. Ver en la tabla.', data.next_30_days, 'eventos-probolsas' ), NUMBER.format( data.next_30_days ) ),
				filter: { type: '', dates: next },
			} ),
			card( {
				iconClass: 'fa-solid fa-calendar-days',
				value: data.total,
				label: __( 'Total', 'eventos-probolsas' ),
				/* translators: %s: cantidad de eventos. */
				action: sprintf( _n( '%s evento en total. Ver todos.', '%s eventos en total. Ver todos.', data.total, 'eventos-probolsas' ), NUMBER.format( data.total ) ),
				filter: { type: '', dates: { from: '', to: '' } },
			} )
		);
	}

	/**
	 * Una tarjeta.
	 *
	 * @param {{ iconClass: string, value: number, label: string, action: string, filter: SummaryFilter, highlight?: boolean }} options Datos.
	 * @returns {HTMLButtonElement} Tarjeta.
	 */
	function card( { iconClass, value, label, action, filter, highlight = false } ) {
		return h(
			'button',
			{ type: 'button', class: [ 'ep-summary-card', highlight && 'is-highlighted' ], attrs: { 'aria-label': action }, on: { click: () => onFilter( filter ) } },
			h( 'span', { class: 'ep-summary-card__icon', attrs: { 'aria-hidden': 'true' } }, icon( iconClass ) ),
			h( 'span', { class: 'ep-summary-card__value', text: NUMBER.format( value ) } ),
			h( 'span', { class: 'ep-summary-card__label', text: label } )
		);
	}

	/**
	 * Conteo por tipo: cada tipo filtra la tabla. Los tipos sin datos de la pantalla no se muestran.
	 *
	 * @param {{ by_type: { type_id: number, count: number }[] }} data Cifras.
	 * @returns {HTMLElement|null} Lista.
	 */
	function byType( data ) {
		const items = ( data.by_type ?? [] )
			.map( ( item ) => ( { ...item, type: types.find( ( type ) => Number( type.id ) === Number( item.type_id ) ) } ) )
			.filter( ( item ) => item.type );

		if ( 0 === items.length ) {
			return null;
		}

		return h(
			'ul',
			{ class: 'ep-summary__types', attrs: { 'aria-label': __( 'Eventos por tipo', 'eventos-probolsas' ) } },
			items.map( ( { type, count } ) =>
				h(
					'li',
					{},
					h(
						'button',
						{
							type: 'button',
							class: 'ep-summary-type',
							/* translators: 1: nombre del tipo, 2: cantidad de eventos. */
							attrs: { 'aria-label': sprintf( _n( '%1$s: %2$s evento. Filtrar la tabla.', '%1$s: %2$s eventos. Filtrar la tabla.', count, 'eventos-probolsas' ), type.name, NUMBER.format( count ) ) },
							on: { click: () => onFilter( { type: String( type.id ), dates: { from: '', to: '' } } ) },
						},
						eventTypeBadge( { ...type, maxWidth: '12rem' } ),
						h( 'span', { class: 'ep-summary-type__count', attrs: { 'aria-hidden': 'true' }, text: NUMBER.format( count ) } )
					)
				)
			)
		);
	}

	return { element, refresh };
}

/**
 * Tarjetas vacías mientras carga.
 *
 * @returns {HTMLElement[]} Nodos.
 */
function skeleton() {
	return [ h( 'div', { class: 'ep-summary__cards', attrs: { 'aria-hidden': 'true' } }, [ 0, 1, 2 ].map( () => h( 'span', { class: 'ep-skeleton ep-summary-card ep-summary-card--loading' } ) ) ) ];
}
