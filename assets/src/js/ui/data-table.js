/**
 * Tabla de datos reutilizable para todos los listados del plugin.
 *
 * - La columna de acciones es la primera y queda fija al desplazarse en horizontal.
 * - Acciones como íconos con tooltip y nombre accesible.
 * - Paginación 25 por defecto y selector 25/50/100 (valores de epConfig.ui); en móvil, anterior /
 *   «Página x de y» / siguiente con botones de 44 px (R-23). Dos modos con el mismo componente:
 *   en el cliente (setRows, setFilter) o en el servidor (`server.onChange` pide la página y la pantalla
 *   la entrega con setServerData).
 * - Drag to scroll con mouse; desplazamiento nativo en pantallas táctiles.
 * - Textos largos truncados con «…» y tooltip con el texto completo.
 * - Estados de carga (skeleton), sin registros y sin resultados para los filtros.
 */
import { h, icon, uid } from '../core/dom.js';
import { __, sprintf } from '../core/i18n.js';
import { paginate } from '../core/pagination.js';
import { iconButton } from './button.js';
import { enableDragScroll } from './drag-scroll.js';
import { syncTruncatedFocus } from './tooltip.js';

/**
 * @typedef {Object} Column
 * @property {string} key Clave del valor en el registro.
 * @property {string} label Encabezado.
 * @property {(row: Object) => Node|string|number|null} [render] Contenido personalizado.
 * @property {boolean} [truncate] Cortar textos largos con «…» y tooltip.
 * @property {string} [maxWidth] Ancho máximo del texto truncado (por ejemplo `24rem`).
 * @property {'start'|'end'|'center'} [align] Alineación.
 */

/**
 * @typedef {Object} RowAction
 * @property {string} icon Clases de Font Awesome.
 * @property {string} label Nombre de la acción (tooltip y aria-label).
 * @property {(row: Object, event: MouseEvent) => void} onClick Acción.
 * @property {'default'|'danger'|'primary'} [variant] Variante visual.
 */

/**
 * @typedef {Object} EmptyState
 * @property {string} icon Clases de Font Awesome.
 * @property {string} title Título.
 * @property {string} message Mensaje.
 */

/**
 * @typedef {Object} ServerPaging
 * @property {(request: { page: number, pageSize: number }) => void} onChange Pide una página al servidor;
 *   la pantalla responde con setServerData().
 */

/**
 * Crea la tabla.
 *
 * @param {HTMLElement} container Contenedor.
 * @param {{ caption: string, columns: Column[], actions?: (row: Object) => RowAction[], rowKey?: string, pageSizes: number[], pageSize: number, emptyState?: EmptyState, noResultsState?: EmptyState, skeletonRows?: number, server?: ServerPaging }} options Opciones.
 * @returns {{ element: HTMLElement, setRows: (rows: Object[]) => void, setFilter: (predicate: ((row: Object) => boolean)|null) => void, setServerData: (data: { rows: Object[], total: number, filtered?: boolean }) => void, refresh: (options?: { firstPage?: boolean }) => void, getQuery: () => { page: number, pageSize: number }, setLoading: (loading: boolean) => void, getVisibleRows: () => Object[], destroy: () => void }} API.
 */
export function createDataTable( container, options ) {
	const {
		caption,
		columns,
		actions = null,
		rowKey = 'id',
		pageSizes,
		pageSize: initialPageSize,
		emptyState = {
			icon: 'fa-solid fa-inbox',
			title: __( 'Todavía no hay registros', 'eventos-probolsas' ),
			message: __( 'Cuando agregues registros, aparecerán aquí.', 'eventos-probolsas' ),
		},
		noResultsState = {
			icon: 'fa-solid fa-magnifying-glass',
			title: __( 'Ningún registro coincide con los filtros', 'eventos-probolsas' ),
			message: __( 'Prueba con otras palabras o limpia los filtros.', 'eventos-probolsas' ),
		},
		skeletonRows = 5,
		server = null,
	} = options;

	const state = {
		rows: [],
		filtered: [],
		predicate: null,
		page: 1,
		pageSize: initialPageSize,
		loading: false,
		// Modo servidor: total de registros y si la respuesta corresponde a filtros activos.
		total: 0,
		hasFilters: false,
	};

	const columnCount = columns.length + ( actions ? 1 : 0 );

	const tbody = h( 'tbody' );
	const table = h(
		'table',
		{ class: 'ep-data-table__table' },
		h( 'caption', { class: 'ep-visually-hidden', text: caption } ),
		h(
			'thead',
			{},
			h(
				'tr',
				{},
				actions && h( 'th', { scope: 'col', class: 'ep-data-table__actions-col', text: __( 'Acciones', 'eventos-probolsas' ) } ),
				columns.map( ( column ) => h( 'th', { scope: 'col', class: alignClass( column ), text: column.label } ) )
			)
		),
		tbody
	);

	const scroll = h( 'div', { class: 'ep-data-table__scroll', tabIndex: 0, attrs: { role: 'region', 'aria-label': caption } }, table );
	const stateSlot = h( 'div', { class: 'ep-data-table__state' } );

	const pageSizeId = uid( 'ep-page-size' );
	const pageSizeSelect = h(
		'select',
		{ id: pageSizeId, class: 'ep-select ep-select--sm', on: { change: ( event ) => setPageSize( Number( event.target.value ) ) } },
		pageSizes.map( ( size ) => h( 'option', { value: String( size ), text: String( size ), selected: size === state.pageSize } ) )
	);
	const range = h( 'p', { class: 'ep-data-table__range', attrs: { 'aria-live': 'polite' } } );
	const pagination = h( 'nav', { class: 'ep-pagination', attrs: { 'aria-label': __( 'Paginación', 'eventos-probolsas' ) } } );

	const footer = h(
		'div',
		{ class: 'ep-data-table__footer' },
		h(
			'div',
			{ class: 'ep-data-table__page-size' },
			h( 'label', { htmlFor: pageSizeId, text: __( 'Registros por página', 'eventos-probolsas' ) } ),
			pageSizeSelect
		),
		range,
		pagination
	);

	const element = h( 'div', { class: 'ep-data-table' }, scroll, stateSlot, footer );
	container.append( element );

	const disableDragScroll = enableDragScroll( scroll );
	// Al cambiar el ancho, un texto puede pasar a estar cortado o dejar de estarlo (QA-027).
	const resizer = globalThis.ResizeObserver ? new globalThis.ResizeObserver( () => syncTruncatedFocus( tbody ) ) : null;
	resizer?.observe( scroll );
	render();

	function alignClass( column ) {
		return column.align && 'start' !== column.align ? `is-align-${ column.align }` : null;
	}

	function applyFilter() {
		state.filtered = state.predicate ? state.rows.filter( state.predicate ) : [ ...state.rows ];
	}

	function render() {
		table.setAttribute( 'aria-busy', String( state.loading ) );

		if ( state.loading ) {
			renderSkeleton();
			stateSlot.replaceChildren();
			footer.hidden = true;
			return;
		}

		const page = paginate( server ? state.total : state.filtered.length, state.page, state.pageSize );
		state.page = page.page;

		const visible = server ? state.rows : state.filtered.slice( page.startIndex, page.endIndex );
		tbody.replaceChildren( ...visible.map( renderRow ) );
		syncTruncatedFocus( tbody );
		renderState();
		renderFooter( page );
	}

	function renderRow( row ) {
		return h(
			'tr',
			{ dataset: { rowKey: String( row[ rowKey ] ?? '' ) } },
			actions && h( 'td', { class: 'ep-data-table__actions-cell' }, h( 'div', { class: 'ep-data-table__actions' }, actions( row ).map( ( action ) => renderAction( action, row ) ) ) ),
			columns.map( ( column ) => h( 'td', { class: alignClass( column ) }, renderCell( column, row ) ) )
		);
	}

	function renderAction( action, row ) {
		return iconButton( {
			icon: action.icon,
			label: action.label,
			variant: action.variant ?? 'default',
			onClick: ( event ) => action.onClick( row, event ),
		} );
	}

	function renderCell( column, row ) {
		const value = column.render ? column.render( row ) : row[ column.key ];
		if ( null === value || undefined === value || '' === value ) {
			return h( 'span', { class: 'ep-data-table__empty-value', text: '—' } );
		}
		if ( column.truncate && ! ( value instanceof Node ) ) {
			return h( 'span', { class: 'ep-truncate', text: value, style: column.maxWidth ? { '--ep-truncate-width': column.maxWidth } : {} } );
		}
		return value instanceof Node ? value : String( value );
	}

	function renderSkeleton() {
		const rows = Array.from( { length: skeletonRows }, () =>
			h(
				'tr',
				{ class: 'ep-data-table__skeleton-row', attrs: { 'aria-hidden': 'true' } },
				Array.from( { length: columnCount }, () => h( 'td', {}, h( 'span', { class: 'ep-skeleton' } ) ) )
			)
		);
		tbody.replaceChildren( ...rows );
	}

	function renderState() {
		const isEmpty = server ? 0 === state.total && ! state.hasFilters : 0 === state.rows.length;
		const noResults = server ? 0 === state.total && state.hasFilters : ! isEmpty && 0 === state.filtered.length;
		scroll.hidden = isEmpty || noResults;

		if ( ! isEmpty && ! noResults ) {
			stateSlot.replaceChildren();
			return;
		}

		const content = isEmpty ? emptyState : noResultsState;
		stateSlot.replaceChildren(
			h(
				'div',
				{ class: 'ep-empty-state ep-empty-state--compact', attrs: { role: 'status' } },
				h( 'span', { class: 'ep-empty-state__icon', attrs: { 'aria-hidden': 'true' } }, icon( content.icon ) ),
				h( 'p', { class: 'ep-empty-state__title', text: content.title } ),
				h( 'p', { class: 'ep-empty-state__message', text: content.message } )
			)
		);
	}

	function renderFooter( page ) {
		footer.hidden = 0 === page.total;
		/* translators: 1: primer registro visible, 2: último registro visible, 3: total de registros. */
		range.textContent = sprintf( __( 'Mostrando %1$d–%2$d de %3$d', 'eventos-probolsas' ), page.from, page.to, page.total );

		const goTo = ( target ) => () => setPage( target );
		// Primera y última página: en móvil se ocultan y queda anterior / «Página x de y» / siguiente (R-23).
		const EDGE = { 'data-ep-page-edge': '' };
		pagination.replaceChildren(
			iconButton( { icon: 'fa-solid fa-angles-left', label: __( 'Primera página', 'eventos-probolsas' ), onClick: goTo( 1 ), disabled: 1 === page.page, attrs: EDGE } ),
			iconButton( { icon: 'fa-solid fa-angle-left', label: __( 'Página anterior', 'eventos-probolsas' ), onClick: goTo( page.page - 1 ), disabled: 1 === page.page } ),
			h(
				'ul',
				{ class: 'ep-pagination__pages' },
				page.pages.map( ( item ) =>
					h(
						'li',
						{},
						'gap' === item
							? h( 'span', { class: 'ep-pagination__gap', text: '…', attrs: { 'aria-hidden': 'true' } } )
							: h( 'button', {
								type: 'button',
								class: [ 'ep-pagination__page', item === page.page && 'is-current' ],
								text: String( item ),
								attrs: {
									/* translators: %d: número de página. */
									'aria-label': sprintf( __( 'Página %d', 'eventos-probolsas' ), item ),
									'aria-current': item === page.page ? 'page' : null,
								},
								on: { click: goTo( item ) },
							} )
					)
				)
			),
			h( 'span', {
				class: 'ep-pagination__compact',
				/* translators: 1: página actual, 2: total de páginas. */
				text: sprintf( __( 'Página %1$d de %2$d', 'eventos-probolsas' ), page.page, page.pageCount ),
			} ),
			iconButton( { icon: 'fa-solid fa-angle-right', label: __( 'Página siguiente', 'eventos-probolsas' ), onClick: goTo( page.page + 1 ), disabled: page.page === page.pageCount } ),
			iconButton( { icon: 'fa-solid fa-angles-right', label: __( 'Última página', 'eventos-probolsas' ), onClick: goTo( page.pageCount ), disabled: page.page === page.pageCount, attrs: EDGE } )
		);
	}

	function setPage( page ) {
		state.page = page;
		scroll.scrollTop = 0;
		requestOrRender();
	}

	function setPageSize( size ) {
		state.pageSize = size;
		state.page = 1;
		requestOrRender();
	}

	/** En modo servidor pide la página (con el skeleton mientras llega); en el cliente, la muestra. */
	function requestOrRender() {
		if ( ! server ) {
			render();
			return;
		}
		state.loading = true;
		render();
		server.onChange( { page: state.page, pageSize: state.pageSize } );
	}

	return {
		element,
		/** Reemplaza los registros. Conserva la página actual si sigue existiendo. */
		setRows( rows ) {
			state.rows = Array.isArray( rows ) ? rows : [];
			state.loading = false;
			applyFilter();
			render();
		},
		/** Aplica un filtro (o null para quitarlo) y vuelve a la primera página. */
		setFilter( predicate ) {
			state.predicate = predicate;
			state.page = 1;
			applyFilter();
			render();
		},
		setLoading( loading ) {
			state.loading = Boolean( loading );
			render();
		},
		/**
		 * Modo servidor: muestra la página recibida. Si la página pedida ya no existe (por ejemplo, después
		 * de eliminar el último registro de la última página), pide la última que sí existe.
		 */
		setServerData( { rows, total, filtered = false } ) {
			state.rows = Array.isArray( rows ) ? rows : [];
			state.total = Math.max( 0, Number( total ) || 0 );
			state.hasFilters = filtered;
			state.loading = false;

			const pageCount = Math.max( 1, Math.ceil( state.total / state.pageSize ) );
			if ( 0 === state.rows.length && state.total > 0 && state.page > pageCount ) {
				state.page = pageCount;
				requestOrRender();
				return;
			}
			render();
		},
		/** Modo servidor: vuelve a pedir la página actual o, si cambiaron los filtros, la primera. */
		refresh( { firstPage = false } = {} ) {
			if ( firstPage ) {
				state.page = 1;
			}
			requestOrRender();
		},
		getQuery: () => ( { page: state.page, pageSize: state.pageSize } ),
		getVisibleRows: () => ( server ? [ ...state.rows ] : [ ...state.filtered ] ),
		destroy() {
			disableDragScroll();
			resizer?.disconnect();
			element.remove();
		},
	};
}
