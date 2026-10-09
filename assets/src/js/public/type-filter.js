/**
 * Filtro múltiple de tipos de evento del calendario, con Tom Select (H-302).
 *
 * Cada opción y cada tipo elegido se ve como el badge del tipo (color, ícono y texto legible, R-02).
 * Sin tipos elegidos se muestran todos. Se maneja con teclado: Tom Select usa un combobox accesible y
 * Retroceso quita el último tipo elegido.
 */
import TomSelect from 'tom-select/base';
import removeButton from 'tom-select/plugins/remove_button/plugin.js';
import { h, uid } from '../core/dom.js';
import { __ } from '../core/i18n.js';
import { eventTypeBadge } from '../ui/badge.js';

TomSelect.define( 'remove_button', removeButton );

/**
 * @typedef {Object} EventTypeOption
 * @property {number} id ID.
 * @property {string} name Nombre.
 * @property {string} color Color `#RRGGBB`.
 * @property {string} icon Clave del ícono.
 * @property {'light'|'dark'} text_tone Tono de texto legible.
 */

/**
 * Crea el filtro.
 *
 * @param {HTMLElement} host Contenedor.
 * @param {EventTypeOption[]} types Tipos que se pueden elegir (ya ordenados por el servidor).
 * @param {(ids: number[]) => void} onChange Recibe los IDs elegidos (vacío = todos).
 * @param {{ TomSelectClass?: typeof TomSelect }} [deps] Dependencias (inyectables en pruebas).
 * @returns {{ select: HTMLSelectElement, destroy: () => void }} Filtro.
 */
export function createTypeFilter( host, types, onChange, { TomSelectClass = TomSelect } = {} ) {
	const id = uid( 'ep-type-filter' );
	const byId = new Map( types.map( ( type ) => [ String( type.id ), type ] ) );
	const select = h(
		'select',
		{ id, multiple: true, class: 'ep-type-filter__select', attrs: { 'aria-label': __( 'Filtrar por tipo de evento', 'eventos-probolsas' ) } },
		types.map( ( type ) => h( 'option', { value: String( type.id ), text: type.name } ) )
	);

	host.replaceChildren(
		h( 'label', { class: 'ep-type-filter__label', attrs: { for: id }, text: __( 'Tipos de evento', 'eventos-probolsas' ) } ),
		select
	);

	const badge = ( data ) => {
		const type = byId.get( String( data.value ) );
		return type ? eventTypeBadge( { ...type, maxWidth: '14rem' } ) : h( 'span', { text: data.text } );
	};

	const control = new TomSelectClass( select, {
		plugins: { remove_button: { title: __( 'Quitar este tipo', 'eventos-probolsas' ) } },
		placeholder: __( 'Todos los tipos', 'eventos-probolsas' ),
		hidePlaceholder: true,
		closeAfterSelect: true,
		maxOptions: null,
		render: {
			option: ( data ) => h( 'div', { class: 'ep-type-filter__option' }, badge( data ) ),
			item: ( data ) => h( 'div', { class: 'ep-type-filter__item' }, badge( data ) ),
			no_results: () => h( 'div', { class: 'no-results', text: __( 'No hay tipos con ese nombre.', 'eventos-probolsas' ) } ),
		},
		onChange: ( values ) => onChange( ( Array.isArray( values ) ? values : [ values ] ).filter( Boolean ).map( Number ) ),
	} );

	return { select, destroy: () => control.destroy() };
}
