/**
 * Pantalla «Eventos» (H-203): tabla paginada en el servidor con búsqueda, filtros por tipo y rango de
 * fechas, exportación CSV con los filtros activos y eliminación con confirmación.
 * El formulario de crear y editar llega con H-204; el detalle, con H-205. Contrato en docs/api/events.md.
 */
import { createApi } from '../core/api.js';
import { createDateFormatter } from '../core/date.js';
import { h, icon } from '../core/dom.js';
import { isActiveFilter } from '../core/filtering.js';
import { __, _n, sprintf } from '../core/i18n.js';
import { eventTypeBadge } from '../ui/badge.js';
import { button } from '../ui/button.js';
import { confirmDialog } from '../ui/confirm-dialog.js';
import { createDataTable } from '../ui/data-table.js';
import { createFilterBar } from '../ui/filter-bar.js';
import { loadError } from '../ui/load-state.js';
import { showToast, toast } from '../ui/toast.js';

/** Ícono y nombre de cada clase de adjunto. */
const ATTACHMENT_KINDS = {
	image: { icon: 'fa-solid fa-image', label: __( 'Imagen', 'eventos-probolsas' ) },
	pdf: { icon: 'fa-solid fa-file-pdf', label: __( 'PDF', 'eventos-probolsas' ) },
};

/**
 * Parámetros de la API a partir de los filtros (vacíos fuera).
 *
 * @param {{ search?: string, type?: string, dates?: { from: string, to: string } }} filters Filtros.
 * @returns {URLSearchParams} Parámetros.
 */
export function filterParams( filters ) {
	const params = new URLSearchParams();
	const add = ( key, value ) => {
		const text = String( value ?? '' ).trim();
		if ( '' !== text ) {
			params.set( key, text );
		}
	};
	add( 'search', filters.search );
	add( 'type', filters.type );
	add( 'date_from', filters.dates?.from );
	add( 'date_to', filters.dates?.to );
	return params;
}

/**
 * Monta la pantalla.
 *
 * @param {HTMLElement} screen Contenedor de la pantalla (data-ep-screen).
 * @param {Object} config Configuración (epConfig).
 * @param {{ api?: ReturnType<typeof createApi>, download?: (url: string) => void }} [dependencies] Dependencias inyectables (pruebas).
 * @returns {Promise<void>} Termina cuando se cargan los tipos y la primera página.
 */
export async function mount( screen, config, { api = createApi( config, { notify: showToast } ), download = ( url ) => globalThis.location.assign( url ) } = {} ) {
	const root = screen.querySelector( '#ep-events' );
	const dates = createDateFormatter( config.ui );
	let filters = {};
	let total = 0;
	let loadedOnce = false;
	// Solo se muestra la respuesta de la última petición: al escribir rápido, una anterior puede llegar después.
	let requestId = 0;

	const filtersSlot = h( 'div' );
	const toolbarSummary = h( 'p', { class: 'ep-table-toolbar__summary', attrs: { 'aria-live': 'polite' } } );
	const exportButton = button( { label: __( 'Exportar CSV', 'eventos-probolsas' ), icon: 'fa-solid fa-file-csv', size: 'sm', onClick: () => exportCsv() } );
	const toolbar = h( 'div', { class: 'ep-table-toolbar' }, toolbarSummary, h( 'div', { class: 'ep-table-toolbar__actions' }, exportButton ) );
	const tableSlot = h( 'div' );

	const table = createDataTable( tableSlot, {
		caption: __( 'Eventos', 'eventos-probolsas' ),
		pageSizes: config.ui.page_sizes,
		pageSize: config.ui.default_page_size,
		server: { onChange: load },
		columns: [
			{ key: 'title', label: __( 'Evento', 'eventos-probolsas' ), truncate: true, maxWidth: '22rem' },
			{ key: 'type', label: __( 'Tipo', 'eventos-probolsas' ), render: ( row ) => ( row.type ? eventTypeBadge( { ...row.type, maxWidth: '14rem' } ) : null ) },
			{ key: 'start_date', label: __( 'Fecha', 'eventos-probolsas' ), align: 'center', render: ( row ) => dates.formatCalendarDate( row.start_date ) },
			{
				key: 'start_time',
				label: __( 'Hora', 'eventos-probolsas' ),
				align: 'center',
				render: ( row ) => ( row.start_time ? dates.formatCalendarTime( row.start_time ) : __( 'Todo el día', 'eventos-probolsas' ) ),
			},
			{ key: 'attachment', label: __( 'Adjunto', 'eventos-probolsas' ), align: 'center', render: ( row ) => attachmentKind( row.attachment ) },
		],
		actions: () => [ { icon: 'fa-solid fa-trash', label: __( 'Eliminar', 'eventos-probolsas' ), variant: 'danger', onClick: ( row ) => remove( row ) } ],
		emptyState: {
			icon: 'fa-solid fa-calendar-days',
			title: __( 'Todavía no hay eventos', 'eventos-probolsas' ),
			message: __( 'Los eventos que se creen aparecerán aquí.', 'eventos-probolsas' ),
		},
		noResultsState: {
			icon: 'fa-solid fa-magnifying-glass',
			title: __( 'Ningún evento coincide con los filtros', 'eventos-probolsas' ),
			message: __( 'Prueba con otras palabras, otro tipo u otras fechas, o limpia los filtros.', 'eventos-probolsas' ),
		},
	} );

	const filterBar = createFilterBar( filtersSlot, {
		title: __( 'Filtrar eventos', 'eventos-probolsas' ),
		fields: [
			{ key: 'search', type: 'search', label: __( 'Buscar', 'eventos-probolsas' ), placeholder: __( 'Título o descripción', 'eventos-probolsas' ) },
			{ key: 'type', type: 'select', label: __( 'Tipo', 'eventos-probolsas' ), allLabel: __( 'Todos los tipos', 'eventos-probolsas' ) },
			{ key: 'dates', type: 'date-range', label: __( 'Fechas', 'eventos-probolsas' ) },
		],
		onChange: ( values ) => {
			filters = values;
			table.refresh( { firstPage: true } );
		},
	} );

	root.removeAttribute( 'aria-busy' );
	root.replaceChildren( filtersSlot, toolbar, tableSlot );

	/**
	 * Pide una página con los filtros activos.
	 *
	 * @param {{ page: number, pageSize: number }} request Página pedida por la tabla.
	 */
	async function load( { page, pageSize } ) {
		const current = ++requestId;
		const params = filterParams( filters );
		params.set( 'page', String( page ) );
		params.set( 'per_page', String( pageSize ) );

		try {
			const result = await api.getPage( `events?${ params }` );
			if ( current !== requestId ) {
				return;
			}
			loadedOnce = true;
			total = result.total;
			updateSummary();
			table.setServerData( { rows: result.items, total: result.total, filtered: Object.values( filters ).some( isActiveFilter ) } );
		} catch {
			// El cliente de la API ya mostró el error en un toast.
			if ( current !== requestId ) {
				return;
			}
			if ( ! loadedOnce ) {
				root.replaceChildren( loadError() );
				return;
			}
			total = 0;
			updateSummary();
			table.setServerData( { rows: [], total: 0, filtered: true } );
		}
	}

	function updateSummary() {
		replaceSummary( sprintf( /* translators: %d: cantidad de eventos. */ _n( '%d evento', '%d eventos', total, 'eventos-probolsas' ), total ) );
		exportButton.disabled = 0 === total;
	}

	/**
	 * Resumen «N eventos» con el número destacado.
	 *
	 * @param {string} text Texto completo.
	 */
	function replaceSummary( text ) {
		const [ count, ...rest ] = text.split( ' ' );
		toolbarSummary.replaceChildren( h( 'strong', { text: count } ), ` ${ rest.join( ' ' ) }` );
	}

	/** Descarga el CSV con los filtros activos (el enlace lleva el nonce: no puede enviar cabeceras). */
	function exportCsv() {
		const params = filterParams( filters );
		params.set( '_wpnonce', config.restNonce );
		download( `${ config.restUrl }events/export.csv?${ params }` );
		toast.success( sprintf( /* translators: %d: cantidad de eventos exportados. */ _n( 'Exportando %d evento a CSV.', 'Exportando %d eventos a CSV.', total, 'eventos-probolsas' ), total ) );
	}

	/**
	 * Elimina un evento tras confirmar. Su adjunto se queda en la Biblioteca de Medios (D-4).
	 *
	 * @param {Object} event Evento.
	 */
	async function remove( event ) {
		const confirmed = await confirmDialog( {
			/* translators: %s: título del evento. */
			title: sprintf( __( '¿Eliminar el evento «%s»?', 'eventos-probolsas' ), event.title ),
			message: __( 'Esta acción no se puede deshacer. Si tiene un adjunto, el archivo se conserva en la Biblioteca de Medios.', 'eventos-probolsas' ),
			confirmLabel: __( 'Eliminar', 'eventos-probolsas' ),
		} );
		if ( ! confirmed ) {
			return;
		}

		try {
			await api.del( `events/${ event.id }`, { silent: true } );
			/* translators: %s: título del evento. */
			toast.success( sprintf( __( 'Evento «%s» eliminado.', 'eventos-probolsas' ), event.title ) );
		} catch ( error ) {
			toast.error( error.message );
		}
		// También si falló (por ejemplo, otra persona ya lo había eliminado): la tabla queda al día.
		table.refresh();
	}

	/** Tipos para el filtro: si fallan, el filtro queda solo con «Todos los tipos» (la API ya avisó). */
	async function loadTypes() {
		try {
			const types = await api.get( 'event-types' );
			filterBar.setOptions(
				'type',
				types.map( ( type ) => ( { value: type.id, label: type.name } ) )
			);
		} catch {
			// Sin tipos el resto de la pantalla sigue funcionando.
		}
	}

	table.setLoading( true );
	await Promise.all( [ loadTypes(), load( table.getQuery() ) ] );
}

/**
 * Ícono del tipo de adjunto, con su nombre para lectores de pantalla.
 *
 * @param {{ kind: string }|null} attachment Adjunto.
 * @returns {HTMLElement|null} Ícono o null (la tabla muestra «—»).
 */
function attachmentKind( attachment ) {
	const kind = attachment ? ATTACHMENT_KINDS[ attachment.kind ] : null;
	if ( ! kind ) {
		return null;
	}
	return h(
		'span',
		{ class: 'ep-attachment-kind', attrs: { 'data-ep-tooltip': kind.label } },
		icon( kind.icon ),
		h( 'span', { class: 'ep-visually-hidden', text: kind.label } )
	);
}
