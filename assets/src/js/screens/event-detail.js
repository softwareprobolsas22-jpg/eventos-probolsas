/**
 * Detalle de un evento en panel lateral (H-205): lo que no cabe en la tabla (§6.3) — descripción,
 * vista previa del adjunto y quién lo creó o modificó y cuándo. Usa `GET /events/{id}`, la misma ruta
 * del modal del calendario (QA-014).
 */
import { createDateFormatter } from '../core/date.js';
import { h, icon } from '../core/dom.js';
import { __, sprintf } from '../core/i18n.js';
import { eventTypeBadge } from '../ui/badge.js';
import { button } from '../ui/button.js';
import { openDrawer } from '../ui/drawer.js';
import { toast } from '../ui/toast.js';

/**
 * Tamaño de archivo legible en es-CO. Ejemplo: `178 KB`, `2,4 MB`.
 *
 * @param {number} bytes Bytes.
 * @returns {string} Tamaño.
 */
export function formatFileSize( bytes ) {
	const number = new Intl.NumberFormat( 'es-CO', { maximumFractionDigits: 1 } );
	if ( bytes >= 1024 * 1024 ) {
		return `${ number.format( bytes / ( 1024 * 1024 ) ) } MB`;
	}
	return `${ number.format( Math.max( 1, Math.round( bytes / 1024 ) ) ) } KB`;
}

/**
 * Abre el detalle.
 *
 * @param {{ event: { id: number, title: string }, config: Object, api: Object, onEdit: (event: Object) => void, onMissing?: () => void }} options
 *   event: fila de la tabla (al menos id y título). onEdit: abre el formulario con el evento completo.
 *   onMissing: el evento ya no existe (404).
 * @returns {Promise<void>} Termina cuando el detalle se muestra o falla.
 */
export async function openEventDetail( { event, config, api, onEdit, onMissing = () => {} } ) {
	const dates = createDateFormatter( config.ui );
	const content = h( 'div', { class: 'ep-event-detail', attrs: { 'aria-busy': 'true' } }, h( 'p', { class: 'ep-mount__loading', text: __( 'Cargando…', 'eventos-probolsas' ) } ) );
	const editButton = button( { label: __( 'Editar', 'eventos-probolsas' ), icon: 'fa-solid fa-pen', variant: 'primary', onClick: () => edit() } );
	editButton.disabled = true;

	let detail = null;
	const drawer = openDrawer( {
		title: event.title,
		body: content,
		footer: [ button( { label: __( 'Cerrar', 'eventos-probolsas' ), onClick: () => drawer.close() } ), editButton ],
	} );

	function edit() {
		drawer.close();
		onEdit( detail );
	}

	try {
		detail = await api.get( `events/${ event.id }`, { silent: true } );
	} catch ( error ) {
		toast.error( error.message );
		drawer.close();
		if ( 404 === error.status ) {
			onMissing();
		}
		return;
	}

	content.removeAttribute( 'aria-busy' );
	content.replaceChildren( ...render( detail, dates ) );
	editButton.disabled = false;
}

/**
 * Contenido del detalle.
 *
 * @param {Object} detail Evento completo de la API.
 * @param {ReturnType<typeof createDateFormatter>} dates Fechas.
 * @returns {Node[]} Nodos.
 */
function render( detail, dates ) {
	const row = ( label, ...value ) => [ h( 'dt', { text: label } ), h( 'dd', {}, ...value ) ];
	const audit = ( person, moment ) =>
		sprintf(
			/* translators: 1: nombre del usuario, 2: fecha y hora. */
			__( '%1$s, el %2$s', 'eventos-probolsas' ),
			person?.name ?? __( 'Usuario eliminado', 'eventos-probolsas' ),
			dates.formatDateTime( moment )
		);

	return [
		detail.is_past &&
			h(
				'p',
				{ class: 'ep-notice ep-notice--warning', attrs: { role: 'status' } },
				icon( 'fa-solid fa-clock-rotate-left' ),
				h( 'span', { text: __( 'Este evento ya pasó.', 'eventos-probolsas' ) } )
			),
		h(
			'dl',
			{ class: 'ep-detail-list' },
			...row( __( 'Tipo', 'eventos-probolsas' ), detail.type ? eventTypeBadge( { ...detail.type, maxWidth: '20rem' } ) : '—' ),
			...row( __( 'Fecha', 'eventos-probolsas' ), dates.formatCalendarDate( detail.start_date ) ),
			...row( __( 'Hora', 'eventos-probolsas' ), detail.start_time ? dates.formatCalendarTime( detail.start_time ) : __( 'Todo el día', 'eventos-probolsas' ) ),
			...row(
				__( 'Descripción', 'eventos-probolsas' ),
				detail.description ? h( 'span', { class: 'ep-detail-list__text', text: detail.description } ) : h( 'span', { class: 'ep-detail-list__empty', text: __( 'Sin descripción', 'eventos-probolsas' ) } )
			),
			...row( __( 'Adjunto', 'eventos-probolsas' ), attachmentPreview( detail.attachment ) ),
			...row( __( 'Creado por', 'eventos-probolsas' ), audit( detail.created_by, detail.created_at ) ),
			...row( __( 'Modificado por', 'eventos-probolsas' ), audit( detail.updated_by, detail.updated_at ) )
		),
	].filter( Boolean );
}

/**
 * Vista previa del adjunto: la imagen (o el ícono del PDF) con su nombre, tamaño y enlace.
 *
 * @param {Object|null} attachment Adjunto de la API.
 * @returns {Node} Vista previa.
 */
function attachmentPreview( attachment ) {
	if ( ! attachment ) {
		return h( 'span', { class: 'ep-detail-list__empty', text: __( 'Sin adjunto', 'eventos-probolsas' ) } );
	}

	const isPdf = 'pdf' === attachment.kind;
	const name = attachment.filename || attachment.title;
	return h(
		'figure',
		{ class: 'ep-detail-attachment' },
		isPdf
			? h( 'span', { class: 'ep-media-field__icon', attrs: { 'aria-hidden': 'true' } }, icon( 'fa-solid fa-file-pdf' ) )
			: h( 'img', { class: 'ep-detail-attachment__image', src: attachment.url, alt: attachment.title || name, loading: 'lazy' } ),
		h(
			'figcaption',
			{ class: 'ep-detail-attachment__caption' },
			h( 'span', { class: 'ep-truncate', text: name } ),
			attachment.filesize > 0 && h( 'span', { class: 'ep-detail-list__empty', text: formatFileSize( attachment.filesize ) } ),
			h(
				'a',
				{ class: 'ep-media-field__link', href: attachment.url, target: '_blank', rel: 'noopener noreferrer' },
				h( 'span', { text: isPdf ? __( 'Abrir PDF', 'eventos-probolsas' ) : __( 'Ver imagen completa', 'eventos-probolsas' ) } ),
				icon( 'fa-solid fa-arrow-up-right-from-square' ),
				h( 'span', { class: 'ep-visually-hidden', text: __( '(se abre en otra pestaña)', 'eventos-probolsas' ) } )
			)
		)
	);
}
