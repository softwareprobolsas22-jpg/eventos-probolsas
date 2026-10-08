/**
 * Formularios con validación en tiempo real (R-24).
 *
 * - Cada campo se valida al salir de él y, una vez marcado con error, en cada cambio: el error
 *   desaparece apenas se corrige, sin regañar mientras se escribe por primera vez.
 * - Las reglas vienen de epConfig.rules (ruleOptions), las mismas constantes que aplica el servidor.
 * - Cada campo muestra su error debajo, con aria-invalid y aria-describedby para lectores de pantalla.
 * - Los mensajes son los mismos textos que usa el servidor (Validator.php / FieldRules.php), así el
 *   usuario ve lo mismo si el error lo detecta el navegador o la API.
 * - Contador de caracteres en los campos con límite (cuenta caracteres, igual que mb_strlen en PHP).
 * - Los errores por campo que devuelve la API (ApiError.fieldErrors) se muestran con setErrors() y
 *   tienen prioridad: se mantienen hasta que el usuario cambia el campo.
 */
import { h, icon, uid } from '../core/dom.js';
import { __, sprintf } from '../core/i18n.js';

const SUBMIT_ICON = 'fa-solid fa-floppy-disk';
const BUSY_ICON = 'fa-solid fa-spinner';

/**
 * Cantidad de caracteres (puntos de código), igual que mb_strlen en PHP.
 *
 * @param {string} value Texto.
 * @returns {number} Caracteres.
 */
export function characterCount( value ) {
	return [ ...value ].length;
}

/**
 * Opciones de campo a partir de las reglas que publica el backend (epConfig.rules.<formulario>.<campo>).
 * Así el navegador y la API validan con las mismas constantes (SSOT).
 *
 * @param {{ required?: boolean, minLength?: number, maxLength?: number, min?: number, max?: number }|undefined} rules Reglas del campo.
 * @returns {{ required: boolean, minLength?: number, maxLength?: number, min?: number, max?: number }} Opciones para createField.
 */
export function ruleOptions( rules = {} ) {
	const options = { required: true === rules.required };
	for ( const key of [ 'minLength', 'maxLength', 'min', 'max' ] ) {
		if ( Number.isInteger( rules[ key ] ) ) {
			options[ key ] = rules[ key ];
		}
	}
	return options;
}

/**
 * Valida en vivo un campo que ya muestra un error (R-24): al corregirlo, el error desaparece.
 *
 * @param {HTMLElement} element Contenedor del campo (.ep-field).
 * @param {() => string|null} validate Validación del campo.
 */
export function revalidateIfInvalid( element, validate ) {
	if ( element.classList.contains( 'is-invalid' ) ) {
		validate();
	}
}

/**
 * Error visible de un campo. El de la API (422) tiene prioridad y se mantiene mientras el valor no
 * cambie: salir del campo sin editarlo no lo borra (§6.2, R-24, QA-025). Cuando el valor cambia,
 * vuelve a mandar la validación local. Al enviar, el servidor vuelve a decidir.
 *
 * @param {() => string|boolean} readValue Valor actual del campo.
 * @param {(message: string|null) => void} show Muestra u oculta el mensaje.
 * @returns {{ fromServer: (message: string|null) => void, fromValidation: (message: string|null) => void }} Estado.
 */
export function createFieldError( readValue, show ) {
	let server = null;
	return {
		fromServer( message ) {
			server = message ? { message, value: readValue() } : null;
			show( message || null );
		},
		fromValidation( message ) {
			if ( server && server.value !== readValue() ) {
				server = null;
			}
			show( message ?? server?.message ?? null );
		},
	};
}

/**
 * @typedef {Object} FieldOptions
 * @property {string} name Nombre del campo (clave en los datos y en los errores de la API).
 * @property {string} label Etiqueta visible.
 * @property {'text'|'textarea'|'number'|'date'|'time'|'url'|'select'} [type] Tipo de control.
 * @property {boolean} [required] Obligatorio.
 * @property {number} [minLength] Mínimo de caracteres, sin contar los espacios de los extremos.
 * @property {number} [maxLength] Máximo de caracteres (muestra contador).
 * @property {Array<{ value: string|number, label: string }>} [options] Opciones (select). Se cambian con setOptions().
 * @property {number} [min] Mínimo (número).
 * @property {number} [max] Máximo (número).
 * @property {string} [hint] Texto de ayuda.
 * @property {string} [placeholder] Ejemplo dentro del campo.
 * @property {string|number} [value] Valor inicial.
 * @property {boolean} [disabled] Campo de solo lectura (se envía su valor igual).
 * @property {(value: string) => string} [normalize] Transforma lo que se escribe (p. ej. a mayúsculas).
 * @property {(value: string) => string|null} [rule] Regla adicional: devuelve el mensaje de error o null.
 * @property {(value: string) => void} [onInput] Se llama al escribir, con el valor ya normalizado.
 */

/**
 * Interfaz común de los campos (texto, color, ícono…): createForm trabaja con cualquiera que la cumpla.
 *
 * @typedef {Object} FormField
 * @property {string} name Nombre del campo.
 * @property {HTMLElement} element Elemento a insertar en el formulario.
 * @property {() => string|boolean} getValue Valor actual (booleano en los interruptores).
 * @property {(value: string|number) => void} setValue Cambia el valor.
 * @property {() => string|null} validate Valida y muestra el error; devuelve el mensaje o null.
 * @property {(message: string|null) => void} setError Muestra (o quita, con null) el error de la API; se mantiene hasta que cambie el valor.
 * @property {() => void} focus Lleva el foco al campo.
 */

/**
 * Crea un campo de formulario.
 *
 * @param {FieldOptions} options Opciones.
 * @returns {FormField & { control: HTMLInputElement|HTMLTextAreaElement|HTMLSelectElement, setHint: (text: string) => void, setOptions: (options: Array<{ value: string|number, label: string }>) => void }} Campo.
 */
export function createField( { name, label, type = 'text', required = false, minLength, maxLength, min, max, hint, placeholder = '', value = '', options = [], disabled = false, normalize, rule, onInput: onInputCallback } ) {
	const id = uid( `ep-field-${ name }` );
	const hintId = `${ id }-hint`;
	const counterId = `${ id }-counter`;
	const errorId = `${ id }-error`;

	const describedBy = [ hint !== undefined && hintId, maxLength && counterId, errorId ].filter( Boolean ).join( ' ' );
	const common = {
		id,
		name,
		disabled,
		attrs: { 'aria-describedby': describedBy, 'aria-required': required ? 'true' : null, 'aria-invalid': 'false' },
		on: { input: onInput, change: onInput, blur: () => validate() },
	};

	// La opción vacía de una lista: el campo queda sin elegir.
	const emptyOption = () => h( 'option', { value: '', text: placeholder || __( 'Selecciona una opción', 'eventos-probolsas' ) } );
	const toOption = ( option ) => h( 'option', { value: String( option.value ), text: option.label } );

	let control;
	if ( 'textarea' === type ) {
		control = h( 'textarea', { ...common, placeholder, class: 'ep-input ep-textarea', rows: 4, ...( maxLength ? { maxLength } : {} ) } );
	} else if ( 'select' === type ) {
		control = h( 'select', { ...common, class: 'ep-select' }, emptyOption(), options.map( toOption ) );
	} else {
		control = h( 'input', {
			...common,
			placeholder,
			type: [ 'number', 'date', 'time', 'url' ].includes( type ) ? type : 'text',
			class: 'ep-input',
			autocomplete: 'off',
			...( 'number' === type ? { min: String( min ?? '' ), max: String( max ?? '' ), step: '1', inputMode: 'numeric' } : {} ),
			...( maxLength ? { maxLength } : {} ),
		} );
	}

	const counter = maxLength ? h( 'span', { class: 'ep-field__counter', id: counterId } ) : null;
	const hintElement = undefined !== hint ? h( 'p', { class: 'ep-field__hint', id: hintId, text: hint } ) : null;
	const errorText = h( 'span' );
	const error = h( 'p', { class: 'ep-field__error', id: errorId, hidden: true }, icon( 'fa-solid fa-circle-exclamation' ), errorText );

	const element = h(
		'div',
		{ class: 'ep-field' },
		h(
			'label',
			{ class: 'ep-field__label', htmlFor: id },
			label,
			required && h( 'span', { class: 'ep-field__required', text: ' *', attrs: { 'aria-hidden': 'true' } } )
		),
		control,
		( hintElement || counter ) && h( 'div', { class: 'ep-field__meta' }, hintElement, counter ),
		error
	);

	setValue( value );

	function onInput() {
		if ( normalize ) {
			control.value = normalize( control.value );
		}
		updateCounter();
		revalidateIfInvalid( element, validate );
		onInputCallback?.( control.value );
	}

	function updateCounter() {
		if ( ! counter ) {
			return;
		}
		const length = characterCount( control.value );
		counter.textContent = `${ length }/${ maxLength }`;
		counter.classList.toggle( 'is-near-limit', length >= maxLength * 0.9 );
	}

	function setValue( newValue ) {
		control.value = String( newValue ?? '' );
		updateCounter();
	}

	function getValue() {
		return control.value.trim();
	}

	function showError( message ) {
		const hasError = Boolean( message );
		errorText.textContent = message ?? '';
		error.hidden = ! hasError;
		element.classList.toggle( 'is-invalid', hasError );
		control.setAttribute( 'aria-invalid', String( hasError ) );
	}

	const fieldError = createFieldError( () => control.value, showError );

	/**
	 * Valida con las mismas reglas y mensajes que el servidor. Si la validación local pasa, sigue
	 * visible el error de la API mientras el valor no cambie.
	 *
	 * @returns {string|null} Mensaje de error local o null si es válido.
	 */
	function validate() {
		const current = getValue();
		let message = null;

		if ( required && '' === current ) {
			/* translators: %s: nombre del campo. */
			message = sprintf( __( 'El campo «%s» es obligatorio.', 'eventos-probolsas' ), label );
		} else if ( minLength && '' !== current && characterCount( current ) < minLength ) {
			/* translators: 1: nombre del campo, 2: número mínimo de caracteres. */
			message = sprintf( __( 'El campo «%1$s» debe tener al menos %2$d caracteres.', 'eventos-probolsas' ), label, minLength );
		} else if ( maxLength && characterCount( current ) > maxLength ) {
			/* translators: 1: nombre del campo, 2: número máximo de caracteres. */
			message = sprintf( __( 'El campo «%1$s» admite máximo %2$d caracteres.', 'eventos-probolsas' ), label, maxLength );
		} else if ( 'number' === type && '' !== current && ! isIntegerInRange( current, min, max ) ) {
			/* translators: 1: nombre del campo, 2: valor mínimo, 3: valor máximo. */
			message = sprintf( __( 'El campo «%1$s» debe ser un número entre %2$d y %3$d.', 'eventos-probolsas' ), label, min, max );
		} else if ( 'date' === type && '' !== current && ! isCalendarDate( current ) ) {
			/* translators: %s: nombre del campo. */
			message = sprintf( __( 'El campo «%s» debe ser una fecha válida.', 'eventos-probolsas' ), label );
		} else if ( 'time' === type && '' !== current && ! /^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/.test( current ) ) {
			/* translators: %s: nombre del campo. */
			message = sprintf( __( 'El campo «%s» debe ser una hora válida.', 'eventos-probolsas' ), label );
		} else if ( rule && '' !== current ) {
			message = rule( current );
		}

		fieldError.fromValidation( message );
		return message;
	}

	return {
		name,
		element,
		control,
		getValue,
		setValue,
		validate,
		setError: fieldError.fromServer,
		focus: () => control.focus(),
		setHint( text ) {
			if ( hintElement ) {
				hintElement.textContent = text;
			}
		},
		/** Reemplaza las opciones de una lista y conserva la elegida si sigue existiendo. */
		setOptions( newOptions ) {
			const current = control.value;
			control.replaceChildren( emptyOption(), ...newOptions.map( toOption ) );
			control.value = current;
		},
	};
}

/**
 * Indica si un texto es una fecha de calendario real `YYYY-MM-DD` (rechaza el 30 de febrero). No usa
 * Date con la fecha como texto, que la interpreta en UTC (R-08).
 *
 * @param {string} value Texto.
 * @returns {boolean} Si es válida.
 */
function isCalendarDate( value ) {
	const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec( value );
	if ( ! match ) {
		return false;
	}
	const [ year, month, day ] = match.slice( 1 ).map( Number );
	const days = new Date( Date.UTC( year, month, 0 ) ).getUTCDate();
	return month >= 1 && month <= 12 && day >= 1 && day <= days;
}

/**
 * Indica si un texto es un entero dentro del rango.
 *
 * @param {string} value Texto.
 * @param {number} min Mínimo.
 * @param {number} max Máximo.
 * @returns {boolean} Si es válido.
 */
function isIntegerInRange( value, min, max ) {
	if ( ! /^-?\d+$/.test( value ) ) {
		return false;
	}
	const number = Number( value );
	return number >= ( min ?? -Infinity ) && number <= ( max ?? Infinity );
}

/**
 * Interruptor de sí/no (casilla nativa con role="switch"): se activa con clic, Espacio o tocando la
 * etiqueta. Cumple la interfaz FormField; su valor es booleano.
 *
 * @param {{ name: string, label: string, hint?: string, value?: boolean }} options Opciones.
 * @returns {FormField & { control: HTMLInputElement }} Campo.
 */
export function createSwitchField( { name, label, hint, value = false } ) {
	const id = uid( `ep-field-${ name }` );
	const hintId = `${ id }-hint`;
	const errorId = `${ id }-error`;
	const errorText = h( 'span' );
	const error = h( 'p', { class: 'ep-field__error', id: errorId, hidden: true }, icon( 'fa-solid fa-circle-exclamation' ), errorText );

	const control = h( 'input', {
		type: 'checkbox',
		id,
		name,
		class: 'ep-switch__input',
		checked: Boolean( value ),
		attrs: { role: 'switch', 'aria-describedby': [ hint !== undefined && hintId, errorId ].filter( Boolean ).join( ' ' ) },
		on: { change: () => fieldError.fromValidation( null ) },
	} );

	const element = h(
		'div',
		{ class: 'ep-field ep-switch' },
		h( 'label', { class: 'ep-switch__label', htmlFor: id }, control, h( 'span', { class: 'ep-switch__track', attrs: { 'aria-hidden': 'true' } } ), h( 'span', { text: label } ) ),
		undefined !== hint && h( 'p', { class: 'ep-field__hint', id: hintId, text: hint } ),
		error
	);

	const fieldError = createFieldError( () => control.checked, ( message ) => {
		errorText.textContent = message ?? '';
		error.hidden = ! message;
		element.classList.toggle( 'is-invalid', Boolean( message ) );
		control.setAttribute( 'aria-invalid', String( Boolean( message ) ) );
	} );

	return {
		name,
		element,
		control,
		getValue: () => control.checked,
		setValue: ( newValue ) => {
			control.checked = Boolean( newValue );
		},
		validate: () => {
			fieldError.fromValidation( null );
			return null;
		},
		setError: fieldError.fromServer,
		focus: () => control.focus(),
	};
}

/**
 * Crea un formulario a partir de campos.
 *
 * @param {{ fields: FormField[], onSubmit: (values: Record<string, string|boolean>) => Promise<void>|void }} options Opciones.
 * @returns {{ element: HTMLFormElement, getValues: () => Record<string, string|boolean>, setErrors: (errors: Record<string, string[]>) => void, isDirty: () => boolean, submitButton: (label: string) => HTMLButtonElement, focusFirst: () => void }} Formulario.
 */
export function createForm( { fields, onSubmit } ) {
	const id = uid( 'ep-form' );
	let busy = false;
	let submitButtonElement = null;

	const element = h( 'form', { id, class: 'ep-form', noValidate: true, on: { submit: handleSubmit } }, fields.map( ( field ) => field.element ) );
	const initialValues = JSON.stringify( getValues() );

	function getValues() {
		return Object.fromEntries( fields.map( ( field ) => [ field.name, field.getValue() ] ) );
	}

	async function handleSubmit( event ) {
		event.preventDefault();
		if ( busy ) {
			return;
		}

		// Los campos ocultos (por ejemplo, el origen no elegido) no se validan.
		const invalid = fields.filter( ( field ) => ! field.element.hidden && null !== field.validate() );
		if ( invalid.length > 0 ) {
			invalid[ 0 ].focus();
			return;
		}

		setBusy( true );
		try {
			await onSubmit( getValues() );
		} finally {
			setBusy( false );
		}
	}

	function setBusy( value ) {
		busy = value;
		if ( submitButtonElement ) {
			submitButtonElement.disabled = value;
			submitButtonElement.classList.toggle( 'is-busy', value );
			submitButtonElement.setAttribute( 'aria-busy', String( value ) );
			submitButtonElement.querySelector( 'i' ).className = value ? BUSY_ICON : SUBMIT_ICON;
		}
	}

	return {
		element,
		getValues,
		/** Muestra los errores por campo de la API y lleva el foco al primero. */
		setErrors( errors ) {
			const withError = fields.filter( ( field ) => errors[ field.name ]?.length );
			withError.forEach( ( field ) => field.setError( errors[ field.name ][ 0 ] ) );
			withError[ 0 ]?.focus();
		},
		isDirty: () => JSON.stringify( getValues() ) !== initialValues,
		/** Botón de envío asociado al formulario (puede ubicarse fuera de él, por ejemplo en el pie del panel). */
		submitButton( label ) {
			submitButtonElement = h(
				'button',
				{ type: 'submit', class: 'ep-button ep-button--primary', attrs: { form: id } },
				icon( SUBMIT_ICON ),
				h( 'span', { text: label } )
			);
			return submitButtonElement;
		},
		focusFirst: () => fields[ 0 ]?.focus(),
	};
}
