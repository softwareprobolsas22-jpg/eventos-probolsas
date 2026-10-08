/**
 * Barra de filtros: título, campos y botón «Limpiar filtros» con el número de filtros activos.
 *
 * No filtra por sí misma: avisa los valores con onChange y la pantalla los aplica (en el cliente o
 * pidiéndolos al servidor). Nunca recarga la página.
 *
 * Tipos de campo:
 * - `search`: texto libre (espera a que el usuario deje de escribir).
 * - `select`: desplegable nativo con la opción «Todos».
 * - `date-range`: dos fechas (desde y hasta); su valor es `{ from, to }`. Cada extremo limita al otro.
 */
import { h, icon, uid } from '../core/dom.js';
import { countActiveFilters } from '../core/filtering.js';
import { __, _n, sprintf } from '../core/i18n.js';
import { debounce } from '../core/timing.js';

/**
 * @typedef {Object} FilterOption
 * @property {string|number} value Valor.
 * @property {string} label Texto visible.
 */

/**
 * @typedef {Object} FilterField
 * @property {string} key Clave del filtro.
 * @property {string} label Etiqueta visible.
 * @property {'search'|'select'|'date-range'} type Tipo de control.
 * @property {string} [placeholder] Texto de ayuda (search).
 * @property {FilterOption[]} [options] Opciones (select). Se pueden cambiar con setOptions().
 * @property {string} [allLabel] Texto de la opción «todos» (select).
 */

/**
 * @typedef {Object} FilterControl
 * @property {HTMLElement} element Control con su etiqueta.
 * @property {(value: unknown) => unknown} set Cambia el valor sin avisar y devuelve el valor efectivo.
 * @property {() => void} focus Lleva el foco al control.
 * @property {(options: FilterOption[]) => void} [setOptions] Reemplaza las opciones.
 */

/**
 * Valor vacío de un campo.
 *
 * @param {FilterField} field Campo.
 * @returns {string|{ from: string, to: string }} Valor vacío.
 */
function emptyValue( field ) {
	return 'date-range' === field.type ? { from: '', to: '' } : '';
}

/**
 * Crea la barra de filtros.
 *
 * @param {HTMLElement} container Contenedor.
 * @param {{ title?: string, fields: FilterField[], onChange: (values: Record<string, unknown>) => void, debounceMs?: number }} options Opciones.
 * @returns {{ element: HTMLElement, getValues: () => Record<string, unknown>, setOptions: (key: string, options: FilterOption[]) => void, reset: () => void, destroy: () => void }} API.
 */
export function createFilterBar( container, { title = __( 'Filtros', 'eventos-probolsas' ), fields, onChange, debounceMs = 300 } ) {
	const values = Object.fromEntries( fields.map( ( field ) => [ field.key, emptyValue( field ) ] ) );
	/** @type {Map<string, FilterControl>} */
	const controls = new Map();

	const emit = () => {
		emitDebounced.cancel();
		updateClearButton();
		onChange( getValues() );
	};
	const emitDebounced = debounce( emit, debounceMs );

	/**
	 * Registra el nuevo valor de un campo y avisa.
	 *
	 * @param {string} key Campo.
	 * @param {unknown} value Valor.
	 * @param {boolean} [later] Esperar a que el usuario deje de escribir.
	 */
	const update = ( key, value, later = false ) => {
		values[ key ] = value;
		if ( later ) {
			emitDebounced();
		} else {
			emit();
		}
	};

	const countBadge = h( 'span', { class: 'ep-filter-bar__count', attrs: { 'aria-hidden': 'true' } } );
	const clearButton = h(
		'button',
		{ type: 'button', class: 'ep-button ep-button--ghost ep-button--sm ep-filter-bar__clear', on: { click: reset } },
		icon( 'fa-solid fa-filter-circle-xmark' ),
		h( 'span', { text: __( 'Limpiar filtros', 'eventos-probolsas' ) } ),
		countBadge
	);

	const titleId = uid( 'ep-filter-title' );
	const element = h(
		'section',
		{ class: 'ep-filter-bar', attrs: { 'aria-labelledby': titleId } },
		h(
			'div',
			{ class: 'ep-filter-bar__header' },
			h( 'h2', { class: 'ep-filter-bar__title', id: titleId }, icon( 'fa-solid fa-filter' ), h( 'span', { text: title } ) ),
			clearButton
		),
		h(
			'div',
			{ class: 'ep-filter-bar__fields' },
			fields.map( ( field ) => {
				const control = CONTROLS[ field.type ]( field, ( value, later ) => update( field.key, value, later ) );
				controls.set( field.key, control );
				return control.element;
			} )
		)
	);

	container.append( element );
	updateClearButton();

	function getValues() {
		return Object.fromEntries( Object.entries( values ).map( ( [ key, value ] ) => [ key, 'object' === typeof value ? { ...value } : value ] ) );
	}

	function updateClearButton() {
		const active = countActiveFilters( values );
		countBadge.textContent = String( active );
		countBadge.hidden = 0 === active;
		clearButton.disabled = 0 === active;
		clearButton.setAttribute(
			'aria-label',
			0 === active
				? __( 'Limpiar filtros', 'eventos-probolsas' )
				: sprintf( /* translators: %d: cantidad de filtros activos. */ _n( 'Limpiar filtros (%d activo)', 'Limpiar filtros (%d activos)', active, 'eventos-probolsas' ), active )
		);
	}

	/** Vacía todos los filtros, avisa de inmediato y lleva el foco al primero. */
	function reset() {
		for ( const field of fields ) {
			values[ field.key ] = controls.get( field.key ).set( emptyValue( field ) );
		}
		emit();
		controls.values().next().value?.focus();
	}

	return {
		element,
		getValues,
		/** Reemplaza las opciones de un campo (por ejemplo, cuando terminan de cargar los tipos). */
		setOptions( key, options ) {
			controls.get( key )?.setOptions?.( options );
		},
		reset,
		destroy() {
			emitDebounced.cancel();
			element.remove();
		},
	};
}

/**
 * Campo con etiqueta para un control de un solo elemento.
 *
 * @param {FilterField} field Campo.
 * @param {string} id ID del control.
 * @param {Node} control Control.
 * @param {string} [modifier] Modificador BEM.
 * @returns {HTMLElement} Campo.
 */
function labelled( field, id, control, modifier ) {
	return h(
		'div',
		{ class: [ 'ep-field', 'ep-filter-bar__field', modifier && `ep-filter-bar__field--${ modifier }` ] },
		h( 'label', { class: 'ep-field__label', htmlFor: id, text: field.label } ),
		control
	);
}

/** Fábricas de controles por tipo de campo. Cada una devuelve un FilterControl. */
const CONTROLS = {
	search( field, update ) {
		const id = uid( `ep-filter-${ field.key }` );
		const input = h( 'input', {
			type: 'search',
			id,
			class: 'ep-input',
			placeholder: field.placeholder ?? '',
			autocomplete: 'off',
			on: { input: ( event ) => update( event.target.value, true ) },
		} );

		return {
			element: labelled( field, id, h( 'div', { class: 'ep-input-group' }, icon( 'fa-solid fa-magnifying-glass ep-input-group__icon' ), input ), 'search' ),
			set( value ) {
				input.value = String( value ?? '' );
				return input.value;
			},
			focus: () => input.focus(),
		};
	},

	select( field, update ) {
		const id = uid( `ep-filter-${ field.key }` );
		const allOption = () => h( 'option', { value: '', text: field.allLabel ?? __( 'Todos', 'eventos-probolsas' ) } );
		const toOption = ( option ) => h( 'option', { value: String( option.value ), text: option.label } );
		const select = h( 'select', { id, class: 'ep-select', on: { change: ( event ) => update( event.target.value ) } }, allOption(), ( field.options ?? [] ).map( toOption ) );

		return {
			element: labelled( field, id, select ),
			set( value ) {
				select.value = String( value ?? '' );
				return select.value;
			},
			focus: () => select.focus(),
			setOptions( options ) {
				const current = select.value;
				select.replaceChildren( allOption(), ...options.map( toOption ) );
				select.value = current;
				// Si la opción elegida ya no existe, el filtro queda en «Todos» y se avisa.
				if ( select.value !== current ) {
					update( select.value );
				}
			},
		};
	},

	'date-range'( field, update ) {
		const fromId = uid( `ep-filter-${ field.key }-from` );
		const toId = uid( `ep-filter-${ field.key }-to` );
		const from = h( 'input', { type: 'date', id: fromId, class: 'ep-input', on: { change: sync } } );
		const to = h( 'input', { type: 'date', id: toId, class: 'ep-input', on: { change: sync } } );

		/** Cada extremo limita al otro para que el rango no quede invertido. */
		function constrain() {
			from.max = to.value;
			to.min = from.value;
		}

		function sync() {
			constrain();
			update( { from: from.value, to: to.value } );
		}

		const element = h(
			'fieldset',
			{ class: 'ep-field ep-filter-bar__field ep-filter-bar__field--range' },
			h( 'legend', { class: 'ep-field__label', text: field.label } ),
			h(
				'div',
				{ class: 'ep-filter-bar__range' },
				h( 'label', { class: 'ep-filter-bar__range-label', htmlFor: fromId, text: __( 'Desde', 'eventos-probolsas' ) } ),
				from,
				h( 'label', { class: 'ep-filter-bar__range-label', htmlFor: toId, text: __( 'Hasta', 'eventos-probolsas' ) } ),
				to
			)
		);

		return {
			element,
			set( value ) {
				from.value = value?.from ?? '';
				to.value = value?.to ?? '';
				constrain();
				return { from: from.value, to: to.value };
			},
			focus: () => from.focus(),
		};
	},
};
