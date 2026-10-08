/**
 * Campo de color: selector nativo, valor hexadecimal editable y colores sugeridos.
 * Cumple la interfaz FormField (ui/form.js), así funciona dentro de createForm.
 */
import { isHexColor } from '../core/color.js';
import { h, icon, uid } from '../core/dom.js';
import { __, sprintf } from '../core/i18n.js';
import { createFieldError, revalidateIfInvalid } from './form.js';

/**
 * Crea el campo de color.
 *
 * @param {{ name: string, label: string, value?: string, presets?: string[], required?: boolean, onChange?: (value: string) => void }} options Opciones.
 * @returns {import('./form.js').FormField} Campo.
 */
export function createColorField( { name, label, value = '', presets = [], required = false, onChange } ) {
	const id = uid( `ep-field-${ name }` );
	const errorId = `${ id }-error`;
	const errorText = h( 'span' );
	const error = h( 'p', { class: 'ep-field__error', id: errorId, hidden: true }, icon( 'fa-solid fa-circle-exclamation' ), errorText );

	const picker = h( 'input', {
		type: 'color',
		class: 'ep-color-field__picker',
		attrs: { 'aria-label': sprintf( /* translators: %s: nombre del campo, por ejemplo «color». */ __( 'Elegir %s', 'eventos-probolsas' ), label.toLowerCase() ) },
		on: { input: () => update( picker.value.toUpperCase() ) },
	} );

	const hex = h( 'input', {
		type: 'text',
		id,
		name,
		class: 'ep-input ep-color-field__hex',
		maxLength: 7,
		placeholder: '#155728',
		autocomplete: 'off',
		spellcheck: false,
		attrs: { 'aria-describedby': errorId, 'aria-required': required ? 'true' : null, 'aria-invalid': 'false' },
		on: { input: () => update( hex.value.trim().toUpperCase(), { fromText: true } ), blur: () => validate() },
	} );

	const swatches = presets.map( ( color ) =>
		h( 'button', {
			type: 'button',
			class: 'ep-color-field__swatch',
			style: { '--ep-swatch-color': color },
			attrs: { 'aria-label': color, 'data-ep-tooltip': color, 'aria-pressed': 'false' },
			dataset: { color },
			on: { click: () => update( color ) },
		} )
	);

	const element = h(
		'div',
		{ class: 'ep-field ep-color-field' },
		h( 'label', { class: 'ep-field__label', htmlFor: id }, label, required && h( 'span', { class: 'ep-field__required', text: ' *', attrs: { 'aria-hidden': 'true' } } ) ),
		h( 'div', { class: 'ep-color-field__controls' }, picker, hex ),
		presets.length > 0 && h( 'div', { class: 'ep-color-field__presets', attrs: { role: 'group', 'aria-label': __( 'Colores sugeridos', 'eventos-probolsas' ) } }, swatches ),
		error
	);

	const fieldError = createFieldError( () => hex.value.trim().toUpperCase(), ( message ) => {
		errorText.textContent = message ?? '';
		error.hidden = ! message;
		element.classList.toggle( 'is-invalid', Boolean( message ) );
		hex.setAttribute( 'aria-invalid', String( Boolean( message ) ) );
	} );

	update( value.toUpperCase(), { silent: true } );

	/**
	 * Sincroniza los controles con un valor.
	 *
	 * @param {string} newValue Valor.
	 * @param {{ fromText?: boolean, silent?: boolean }} options fromText: no reescribir el campo que el usuario está editando.
	 */
	function update( newValue, { fromText = false, silent = false } = {} ) {
		if ( ! fromText ) {
			hex.value = newValue;
		}
		if ( isHexColor( newValue ) ) {
			picker.value = newValue.toLowerCase();
		}
		swatches.forEach( ( swatch ) => swatch.setAttribute( 'aria-pressed', String( swatch.dataset.color === newValue ) ) );
		revalidateIfInvalid( element, validate );
		if ( ! silent ) {
			onChange?.( newValue );
		}
	}

	/**
	 * Valida con las mismas reglas y mensajes que el servidor (FieldRules::hex_color).
	 *
	 * @returns {string|null} Mensaje de error o null si es válido.
	 */
	function validate() {
		const current = hex.value.trim();
		let message = null;
		if ( required && '' === current ) {
			/* translators: %s: nombre del campo. */
			message = sprintf( __( 'El campo «%s» es obligatorio.', 'eventos-probolsas' ), label );
		} else if ( '' !== current && ! isHexColor( current ) ) {
			/* translators: %s: nombre del campo. */
			message = sprintf( __( 'El campo «%s» debe ser un color hexadecimal, por ejemplo #155728.', 'eventos-probolsas' ), label );
		}
		fieldError.fromValidation( message );
		return message;
	}

	return {
		name,
		element,
		getValue: () => hex.value.trim().toUpperCase(),
		setValue: ( newValue ) => update( String( newValue ).toUpperCase(), { silent: true } ),
		validate,
		setError: fieldError.fromServer,
		focus: () => hex.focus(),
	};
}
