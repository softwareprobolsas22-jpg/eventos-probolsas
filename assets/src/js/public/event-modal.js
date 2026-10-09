/**
 * Modal de detalle de un evento en la intranet (H-303). Lo abren el calendario y los próximos eventos.
 *
 * - Usa `GET /events/{id}` (la misma ruta del detalle de wp-admin, QA-014): tipo, fecha larga y hora
 *   es-CO, descripción y adjunto (imagen ampliable o PDF para abrir o descargar).
 * - Con `same_day` permite pasar a los demás eventos del mismo día sin cerrar el modal.
 * - «Añadir a mi calendario» descarga el `.ics` con la hora de Colombia (RL-03). Sin eventos
 *   relacionados (D-6).
 * - `<dialog>` nativo en modo modal: el foco queda dentro, `Esc` cierra y, al cerrar, el foco vuelve al
 *   evento que lo abrió (R-16).
 */
import '../../scss/widgets/event-modal.scss';
import { h, icon, releaseFloating, uid } from '../core/dom.js';
import { __, sprintf } from '../core/i18n.js';
import { eventTypeBadge } from '../ui/badge.js';
import { iconButton } from '../ui/button.js';
import { toast } from '../ui/toast.js';

/**
 * Dirección del `.ics`, con el nonce en la consulta: un enlace no envía la cabecera `X-WP-Nonce`.
 *
 * @param {{ restUrl: string, restNonce: string }} config Configuración.
 * @param {number} id ID del evento.
 * @returns {string} Dirección.
 */
export function icsUrl( config, id ) {
	return `${ config.restUrl }events/${ id }/ics?_wpnonce=${ encodeURIComponent( config.restNonce ) }`;
}

/**
 * Abre el detalle de un evento.
 *
 * @param {Object} options Opciones.
 * @param {number} options.id ID del evento.
 * @param {{ get: Function }} options.api Cliente de la API.
 * @param {{ restUrl: string, restNonce: string }} options.config Configuración.
 * @param {Object} options.dates Formateador de fechas (core/date.js).
 * @param {HTMLElement|null} [options.opener] Elemento que recibe el foco al cerrar.
 * @returns {{ element: HTMLDialogElement, close: () => void, ready: Promise<void> }} Modal.
 */
export function openEventModal( { id, api, config, dates, opener = null } ) {
	const doc = document;
	const returnFocus = opener ?? doc.activeElement;
	const titleId = uid( 'ep-event-modal-title' );
	const cache = new Map();
	let closed = false;

	// El título recibe el foco al abrir: el lector de pantalla lo anuncia y el tooltip de «Cerrar» no
	// aparece solo. Con Tab se llega a los botones.
	const title = h( 'h2', { class: 'ep-event-modal__title', id: titleId, tabIndex: -1, text: __( 'Cargando…', 'eventos-probolsas' ) } );
	const typeSlot = h( 'div', { class: 'ep-event-modal__type' } );
	const body = h( 'div', { class: 'ep-event-modal__body', attrs: { 'aria-busy': 'true' } } );
	const footer = h( 'footer', { class: 'ep-event-modal__footer' } );

	// `ep-public` aplica los estilos del plugin: el diálogo vive en <body>, fuera del widget.
	const dialog = h(
		'dialog',
		{ class: 'ep-public ep-event-modal', attrs: { 'aria-labelledby': titleId } },
		h(
			'header',
			{ class: 'ep-event-modal__header' },
			typeSlot,
			iconButton( { icon: 'fa-solid fa-xmark', label: __( 'Cerrar', 'eventos-probolsas' ), onClick: () => close() } ),
			title
		),
		body,
		footer
	);

	/** Cierra el modal y devuelve el foco. */
	function close() {
		if ( closed ) {
			return;
		}
		closed = true;
		dialog.close();
		releaseFloating( dialog );
		dialog.remove();
		if ( returnFocus instanceof HTMLElement && returnFocus.isConnected ) {
			returnFocus.focus();
		}
	}

	dialog.addEventListener( 'cancel', ( event ) => {
		event.preventDefault();
		close();
	} );
	dialog.addEventListener( 'click', ( event ) => {
		if ( event.target === dialog ) {
			close();
		}
	} );

	doc.body.append( dialog );
	dialog.showModal();
	dialog.setAttribute( 'data-ep-modal', '' );
	title.focus();

	/**
	 * Muestra un evento (el inicial o uno del mismo día).
	 *
	 * @param {number} eventId ID.
	 * @returns {Promise<void>} Termina al mostrarlo.
	 */
	async function show( eventId ) {
		body.setAttribute( 'aria-busy', 'true' );

		let detail = cache.get( eventId );
		if ( ! detail ) {
			try {
				detail = await api.get( `events/${ eventId }`, { silent: true } );
			} catch ( error ) {
				toast.error( error.message );
				if ( ! cache.size ) {
					close();
				}
				body.removeAttribute( 'aria-busy' );
				return;
			}
			cache.set( eventId, detail );
		}

		if ( closed ) {
			return;
		}

		title.textContent = detail.title;
		typeSlot.replaceChildren( detail.type ? eventTypeBadge( { ...detail.type, maxWidth: '16rem' } ) : '' );
		body.replaceChildren( ...renderBody( detail, dates ) );
		body.removeAttribute( 'aria-busy' );
		footer.replaceChildren( ...renderFooter( detail ) );
	}

	/**
	 * Pie: navegación entre los eventos del mismo día y «Añadir a mi calendario».
	 *
	 * @param {Object} detail Evento.
	 * @returns {Node[]} Nodos.
	 */
	function renderFooter( detail ) {
		const sameDay = Array.isArray( detail.same_day ) ? detail.same_day : [];
		const index = sameDay.findIndex( ( other ) => other.id === detail.id );
		const nodes = [];

		if ( sameDay.length > 1 && index >= 0 ) {
			const go = ( offset ) => show( sameDay[ index + offset ].id ).then( () => focusNav( offset ) );
			nodes.push(
				h(
					'nav',
					{ class: 'ep-event-modal__nav', attrs: { 'aria-label': __( 'Eventos del mismo día', 'eventos-probolsas' ) } },
					iconButton( {
						icon: 'fa-solid fa-chevron-left',
						label: __( 'Evento anterior del mismo día', 'eventos-probolsas' ),
						disabled: 0 === index,
						onClick: () => go( -1 ),
						attrs: { 'data-nav': 'prev' },
					} ),
					h( 'span', {
						class: 'ep-event-modal__position',
						attrs: { 'aria-live': 'polite' },
						/* translators: 1: posición del evento, 2: eventos del día. */
						text: sprintf( __( '%1$d de %2$d', 'eventos-probolsas' ), index + 1, sameDay.length ),
					} ),
					iconButton( {
						icon: 'fa-solid fa-chevron-right',
						label: __( 'Evento siguiente del mismo día', 'eventos-probolsas' ),
						disabled: index === sameDay.length - 1,
						onClick: () => go( 1 ),
						attrs: { 'data-nav': 'next' },
					} )
				)
			);
		}

		nodes.push(
			h(
				'a',
				{ class: 'ep-button ep-button--primary ep-event-modal__ics', href: icsUrl( config, detail.id ), attrs: { download: '' } },
				icon( 'fa-solid fa-calendar-plus' ),
				h( 'span', { text: __( 'Añadir a mi calendario', 'eventos-probolsas' ) } )
			)
		);

		return nodes;
	}

	/**
	 * Deja el foco en el botón de navegación usado (el pie se vuelve a dibujar). Si quedó deshabilitado
	 * (primer o último evento), pasa al otro.
	 *
	 * @param {number} offset -1 o 1.
	 */
	function focusNav( offset ) {
		const used = footer.querySelector( `[data-nav="${ offset < 0 ? 'prev' : 'next' }"]` );
		const other = footer.querySelector( `[data-nav="${ offset < 0 ? 'next' : 'prev' }"]` );
		( used && ! used.disabled ? used : other )?.focus();
	}

	const ready = show( id );

	return { element: dialog, close, ready };
}

/**
 * Cuerpo del detalle.
 *
 * @param {Object} detail Evento de la API.
 * @param {Object} dates Formateador de fechas.
 * @returns {Node[]} Nodos.
 */
function renderBody( detail, dates ) {
	const when = detail.start_time ? dates.formatCalendarTime( detail.start_time ) : __( 'Todo el día', 'eventos-probolsas' );

	return [
		h(
			'p',
			{ class: 'ep-event-modal__when' },
			h( 'span', { class: 'ep-event-modal__date' }, icon( 'fa-solid fa-calendar-day fa-fw' ), h( 'span', { text: dates.formatLongCalendarDate( detail.start_date ) } ) ),
			h( 'span', { class: 'ep-event-modal__time' }, icon( 'fa-solid fa-clock fa-fw' ), h( 'span', { text: when } ) )
		),
		detail.description
			? h( 'p', { class: 'ep-event-modal__description', text: detail.description } )
			: h( 'p', { class: 'ep-event-modal__empty', text: __( 'Este evento no tiene descripción.', 'eventos-probolsas' ) } ),
		detail.attachment && attachment( detail.attachment ),
	].filter( Boolean );
}

/**
 * Adjunto: la imagen, que se amplía dentro del modal, o el PDF para abrir o descargar.
 *
 * @param {{ kind: string, url: string, title?: string, filename?: string }} file Adjunto.
 * @returns {HTMLElement} Adjunto.
 */
function attachment( file ) {
	const name = file.filename || file.title || __( 'Adjunto', 'eventos-probolsas' );
	const newTab = h( 'span', { class: 'ep-visually-hidden', text: __( '(se abre en otra pestaña)', 'eventos-probolsas' ) } );

	if ( 'pdf' === file.kind ) {
		return h(
			'div',
			{ class: 'ep-event-modal__pdf' },
			h( 'span', { class: 'ep-event-modal__pdf-icon', attrs: { 'aria-hidden': 'true' } }, icon( 'fa-solid fa-file-pdf' ) ),
			h( 'span', { class: 'ep-event-modal__pdf-name ep-truncate', text: name } ),
			h(
				'span',
				{ class: 'ep-event-modal__pdf-actions' },
				h( 'a', { class: 'ep-button ep-button--secondary ep-button--sm', href: file.url, target: '_blank', rel: 'noopener noreferrer' }, icon( 'fa-solid fa-arrow-up-right-from-square' ), h( 'span', { text: __( 'Abrir PDF', 'eventos-probolsas' ) } ), newTab ),
				h( 'a', { class: 'ep-button ep-button--secondary ep-button--sm', href: file.url, attrs: { download: name } }, icon( 'fa-solid fa-download' ), h( 'span', { text: __( 'Descargar', 'eventos-probolsas' ) } ) )
			)
		);
	}

	const figure = h( 'figure', { class: 'ep-event-modal__figure' } );
	const zoom = h(
		'button',
		{
			type: 'button',
			class: 'ep-event-modal__zoom',
			attrs: { 'aria-expanded': 'false', 'aria-label': __( 'Ampliar imagen', 'eventos-probolsas' ) },
			on: {
				click: () => {
					const expanded = figure.classList.toggle( 'is-expanded' );
					zoom.setAttribute( 'aria-expanded', String( expanded ) );
					zoom.setAttribute( 'aria-label', expanded ? __( 'Reducir imagen', 'eventos-probolsas' ) : __( 'Ampliar imagen', 'eventos-probolsas' ) );
				},
			},
		},
		h( 'img', { class: 'ep-event-modal__image', src: file.url, alt: file.title || name } ),
		h( 'span', { class: 'ep-event-modal__zoom-hint', attrs: { 'aria-hidden': 'true' } }, icon( 'fa-solid fa-magnifying-glass-plus' ) )
	);

	figure.append(
		zoom,
		h(
			'figcaption',
			{ class: 'ep-event-modal__caption' },
			h( 'a', { href: file.url, target: '_blank', rel: 'noopener noreferrer' }, h( 'span', { text: __( 'Ver imagen original', 'eventos-probolsas' ) } ), icon( 'fa-solid fa-arrow-up-right-from-square' ), newTab )
		)
	);

	return figure;
}
