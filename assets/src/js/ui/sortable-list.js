/**
 * Lista que el usuario ordena:
 *
 * - Con el mouse, arrastrando cada fila (Drag & Drop API).
 * - Con el teclado o en pantallas táctiles, con los botones «Subir» y «Bajar» de cada fila (el arrastre
 *   nativo no funciona con el dedo). El foco se queda en el botón de la fila movida.
 * - Cada movimiento se anuncia a los lectores de pantalla («… ahora está en la posición 2 de 8»).
 */
import { h, icon, replaceContent } from '../core/dom.js';
import { __, sprintf } from '../core/i18n.js';
import { iconButton } from './button.js';

/**
 * @typedef {Object} SortableItem
 * @property {number|string} id Identificador.
 * @property {string} name Nombre (para los botones y los anuncios).
 * @property {() => Node} [render] Contenido visible; por defecto, el nombre.
 */

/**
 * Crea la lista.
 *
 * @param {HTMLElement} container Contenedor.
 * @param {{ label: string, items: SortableItem[], onChange?: (ids: Array<number|string>) => void }} options Opciones.
 * @returns {{ element: HTMLElement, getOrder: () => Array<number|string>, isDirty: () => boolean }} API.
 */
export function createSortableList( container, { label, items, onChange } ) {
	const initial = items.map( ( item ) => item.id );
	const order = [ ...items ];
	let dragged = null;

	const list = h( 'ol', { class: 'ep-sortable', attrs: { 'aria-label': label } } );
	const status = h( 'p', { class: 'ep-visually-hidden', attrs: { role: 'status', 'aria-live': 'polite' } } );
	const element = h( 'div', { class: 'ep-sortable-list' }, list, status );
	container.append( element );

	const rows = new Map( items.map( ( item ) => [ item.id, renderRow( item ) ] ) );
	render();

	/**
	 * Fila de un elemento.
	 *
	 * @param {SortableItem} item Elemento.
	 * @returns {HTMLLIElement} Fila.
	 */
	function renderRow( item ) {
		const row = h(
			'li',
			{
				class: 'ep-sortable__item',
				draggable: true,
				dataset: { id: String( item.id ) },
				on: {
					dragstart: ( event ) => {
						dragged = item;
						row.classList.add( 'is-dragging' );
						event.dataTransfer?.setData( 'text/plain', String( item.id ) );
						if ( event.dataTransfer ) {
							event.dataTransfer.effectAllowed = 'move';
						}
					},
					dragover: ( event ) => {
						if ( ! dragged || dragged === item ) {
							return;
						}
						event.preventDefault();
						const box = row.getBoundingClientRect();
						const after = event.clientY > box.top + box.height / 2;
						move( dragged, order.indexOf( item ) + ( after ? 1 : 0 ), { fromDrag: true } );
					},
					drop: ( event ) => event.preventDefault(),
					dragend: () => {
						row.classList.remove( 'is-dragging' );
						if ( dragged ) {
							announce( dragged );
							onChange?.( getOrder() );
						}
						dragged = null;
					},
				},
			},
			h( 'span', { class: 'ep-sortable__handle', attrs: { 'aria-hidden': 'true' } }, icon( 'fa-solid fa-grip-vertical' ) ),
			h( 'span', { class: 'ep-sortable__position', attrs: { 'aria-hidden': 'true' } } ),
			h( 'span', { class: 'ep-sortable__content' }, item.render ? item.render() : item.name ),
			h(
				'span',
				{ class: 'ep-sortable__actions' },
				/* translators: %s: nombre del elemento. */
				iconButton( { icon: 'fa-solid fa-arrow-up', label: sprintf( __( 'Subir «%s»', 'eventos-probolsas' ), item.name ), attrs: { 'data-direction': 'up' }, onClick: () => step( item, -1 ) } ),
				/* translators: %s: nombre del elemento. */
				iconButton( { icon: 'fa-solid fa-arrow-down', label: sprintf( __( 'Bajar «%s»', 'eventos-probolsas' ), item.name ), attrs: { 'data-direction': 'down' }, onClick: () => step( item, 1 ) } )
			)
		);
		return row;
	}

	/** Pinta las filas en el orden actual, con su posición y los botones de los extremos desactivados. */
	function render() {
		replaceContent( list, order.map( ( item ) => rows.get( item.id ) ) );
		order.forEach( ( item, index ) => {
			const row = rows.get( item.id );
			row.querySelector( '.ep-sortable__position' ).textContent = String( index + 1 );
			row.querySelector( '[data-direction="up"]' ).disabled = 0 === index;
			row.querySelector( '[data-direction="down"]' ).disabled = order.length - 1 === index;
		} );
	}

	/**
	 * Mueve un elemento a una posición.
	 *
	 * @param {SortableItem} item Elemento.
	 * @param {number} target Posición de destino (índice antes de quitarlo de su lugar).
	 * @param {{ fromDrag?: boolean }} [options] Durante el arrastre se avisa al soltar, no en cada paso.
	 */
	function move( item, target, { fromDrag = false } = {} ) {
		const from = order.indexOf( item );
		const to = Math.max( 0, Math.min( order.length - 1, target > from ? target - 1 : target ) );
		if ( from === to ) {
			return;
		}
		order.splice( from, 1 );
		order.splice( to, 0, item );
		render();
		if ( ! fromDrag ) {
			announce( item );
			onChange?.( getOrder() );
		}
	}

	/**
	 * Sube (-1) o baja (+1) un elemento una posición, con el foco en el mismo botón.
	 *
	 * @param {SortableItem} item Elemento.
	 * @param {-1|1} delta Dirección.
	 */
	function step( item, delta ) {
		const index = order.indexOf( item );
		move( item, delta < 0 ? index - 1 : index + 2 );

		const direction = delta < 0 ? 'up' : 'down';
		const button = rows.get( item.id ).querySelector( `[data-direction="${ direction }"]` );
		( button.disabled ? rows.get( item.id ).querySelector( `[data-direction="${ 'up' === direction ? 'down' : 'up' }"]` ) : button ).focus();
	}

	function announce( item ) {
		/* translators: 1: nombre del elemento, 2: posición, 3: total. */
		status.textContent = sprintf( __( '«%1$s» ahora está en la posición %2$d de %3$d.', 'eventos-probolsas' ), item.name, order.indexOf( item ) + 1, order.length );
	}

	function getOrder() {
		return order.map( ( item ) => item.id );
	}

	return {
		element,
		getOrder,
		isDirty: () => getOrder().some( ( id, index ) => id !== initial[ index ] ),
	};
}
