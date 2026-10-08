/**
 * Selector de íconos: buscador y cuadrícula con los íconos permitidos (epConfig.icons).
 *
 * Accesible como grupo de opciones (radiogroup): Tab entra al grupo en el ícono elegido; las flechas,
 * Inicio y Fin se mueven entre íconos; Enter o Espacio eligen. La búsqueda ignora mayúsculas y tildes.
 * Cumple la interfaz FormField (ui/form.js), así funciona dentro de createForm.
 */
import { h, icon, uid } from '../core/dom.js';
import { __, sprintf } from '../core/i18n.js';
import { matchesQuery } from '../core/search.js';
import { createFieldError } from './form.js';

/**
 * Crea el selector.
 *
 * @param {{ name: string, label: string, icons: Array<{ key: string, label: string, keywords: string }>, value?: string, required?: boolean, onChange?: (key: string) => void }} options Opciones.
 * @returns {import('./form.js').FormField} Campo.
 */
export function createIconPicker( { name, label, icons, value = '', required = false, onChange } ) {
	const labelId = uid( `ep-field-${ name }-label` );
	const errorId = uid( `ep-field-${ name }-error` );
	// Una clave fuera del catálogo (por ejemplo, un ícono retirado) cuenta como «sin elegir» (QA-026).
	const inCatalog = ( key ) => ( icons.some( ( item ) => item.key === key ) ? key : '' );
	let selected = inCatalog( value );

	const options = icons.map( ( item ) =>
		h(
			'button',
			{
				type: 'button',
				class: 'ep-icon-picker__option',
				attrs: { role: 'radio', 'aria-label': item.label, 'data-ep-tooltip': item.label },
				dataset: { key: item.key },
				on: { click: () => select( item.key ), keydown: onKeydown },
			},
			icon( `fa-solid fa-${ item.key }` )
		)
	);

	const search = h( 'input', {
		type: 'search',
		class: 'ep-input',
		placeholder: __( 'Buscar ícono (ej.: cumpleaños, reunión)', 'eventos-probolsas' ),
		autocomplete: 'off',
		attrs: { 'aria-label': sprintf( /* translators: %s: nombre del campo, por ejemplo «ícono». */ __( 'Buscar %s', 'eventos-probolsas' ), label.toLowerCase() ) },
		on: { input: () => filter( search.value ) },
	} );

	const grid = h( 'div', { class: 'ep-icon-picker__grid', attrs: { role: 'radiogroup', 'aria-labelledby': labelId, 'aria-describedby': errorId } }, options );
	const empty = h( 'p', { class: 'ep-icon-picker__empty', hidden: true, text: __( 'Ningún ícono coincide con la búsqueda.', 'eventos-probolsas' ) } );
	const errorText = h( 'span' );
	const error = h( 'p', { class: 'ep-field__error', id: errorId, hidden: true }, icon( 'fa-solid fa-circle-exclamation' ), errorText );

	const element = h(
		'div',
		{ class: 'ep-field ep-icon-picker' },
		h( 'span', { class: 'ep-field__label', id: labelId }, label, required && h( 'span', { class: 'ep-field__required', text: ' *', attrs: { 'aria-hidden': 'true' } } ) ),
		h( 'div', { class: 'ep-input-group' }, icon( 'fa-solid fa-magnifying-glass ep-input-group__icon' ), search ),
		grid,
		empty,
		error
	);

	const fieldError = createFieldError( () => selected, ( message ) => {
		errorText.textContent = message ?? '';
		error.hidden = ! message;
		element.classList.toggle( 'is-invalid', Boolean( message ) );
	} );

	refresh();

	function visibleOptions() {
		return options.filter( ( option ) => ! option.hidden );
	}

	/** Marca la opción elegida y deja una sola opción alcanzable con Tab (roving tabindex). */
	function refresh() {
		const visible = visibleOptions();
		const focusable = visible.find( ( option ) => option.dataset.key === selected ) ?? visible[ 0 ];
		options.forEach( ( option ) => {
			const isSelected = option.dataset.key === selected;
			option.setAttribute( 'aria-checked', String( isSelected ) );
			option.classList.toggle( 'is-selected', isSelected );
			option.tabIndex = option === focusable ? 0 : -1;
		} );
	}

	function select( key, { silent = false } = {} ) {
		selected = key;
		refresh();
		fieldError.fromValidation( null );
		if ( ! silent ) {
			onChange?.( key );
		}
	}

	function filter( query ) {
		icons.forEach( ( item, index ) => {
			options[ index ].hidden = ! matchesQuery( [ item.label, item.keywords, item.key.replace( /-/g, ' ' ) ], query );
		} );
		empty.hidden = visibleOptions().length > 0;
		refresh();
	}

	function onKeydown( event ) {
		const visible = visibleOptions();
		const index = visible.indexOf( event.currentTarget );
		const targets = {
			ArrowRight: index + 1,
			ArrowDown: index + 1,
			ArrowLeft: index - 1,
			ArrowUp: index - 1,
			Home: 0,
			End: visible.length - 1,
		};
		if ( ! ( event.key in targets ) ) {
			return;
		}
		event.preventDefault();
		const next = visible[ Math.min( Math.max( targets[ event.key ], 0 ), visible.length - 1 ) ];
		next?.focus();
	}

	return {
		name,
		element,
		getValue: () => selected,
		setValue: ( key ) => select( inCatalog( String( key ) ), { silent: true } ),
		validate() {
			/* translators: %s: nombre del campo. */
			const message = required && '' === selected ? sprintf( __( 'El campo «%s» es obligatorio.', 'eventos-probolsas' ), label ) : null;
			fieldError.fromValidation( message );
			return message;
		},
		setError: fieldError.fromServer,
		focus: () => ( options.find( ( option ) => 0 === option.tabIndex ) ?? search ).focus(),
	};
}
