// @vitest-environment happy-dom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { characterCount, createField, createForm, createSwitchField, ruleOptions } from '../../../assets/src/js/ui/form.js';

beforeEach( () => {
	document.body.innerHTML = '';
} );

function submit( form ) {
	form.element.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
}

describe( 'createField', () => {
	it( 'asocia etiqueta, ayuda, contador y error al control', () => {
		const field = createField( { name: 'description', label: 'Descripción', type: 'textarea', maxLength: 500, hint: 'Opcional.' } );
		document.body.append( field.element );

		const label = field.element.querySelector( 'label' );
		expect( label.htmlFor ).toBe( field.control.id );

		const describedBy = field.control.getAttribute( 'aria-describedby' ).split( ' ' );
		expect( describedBy ).toHaveLength( 3 );
		describedBy.forEach( ( id ) => expect( document.getElementById( id ) ).not.toBeNull() );
	} );

	it( 'el contador se actualiza al escribir y avisa cerca del límite (CP-2.18)', () => {
		const field = createField( { name: 'description', label: 'Descripción', type: 'textarea', maxLength: 10 } );
		const counter = field.element.querySelector( '.ep-field__counter' );
		expect( counter.textContent ).toBe( '0/10' );
		expect( field.control.maxLength ).toBe( 10 );

		field.control.value = 'ñandú año';
		field.control.dispatchEvent( new Event( 'input' ) );

		expect( counter.textContent ).toBe( '9/10' );
		expect( counter.classList.contains( 'is-near-limit' ) ).toBe( true );
	} );

	it( 'valida obligatorio con el mismo mensaje del servidor (CP-2.16)', () => {
		const field = createField( { name: 'name', label: 'Nombre', required: true } );
		field.control.value = '   ';

		expect( field.validate() ).toBe( 'El campo «Nombre» es obligatorio.' );
		expect( field.control.getAttribute( 'aria-invalid' ) ).toBe( 'true' );
		expect( field.element.querySelector( '.ep-field__error' ).hidden ).toBe( false );
	} );

	it( 'valida rangos numéricos', () => {
		const field = createField( { name: 'sort_order', label: 'Orden', type: 'number', min: 0, max: 9999 } );

		field.setValue( '-1' );
		expect( field.validate() ).toBe( 'El campo «Orden» debe ser un número entre 0 y 9999.' );

		field.setValue( '25' );
		expect( field.validate() ).toBeNull();
	} );

	it( 'valida al salir del campo, no mientras se escribe por primera vez (R-24)', () => {
		const field = createField( { name: 'name', label: 'Nombre', required: true, maxLength: 5 } );
		const error = field.element.querySelector( '.ep-field__error' );

		field.control.value = 'Cumpleaños';
		field.control.dispatchEvent( new Event( 'input' ) );
		expect( error.hidden ).toBe( true );

		field.control.dispatchEvent( new Event( 'blur' ) );
		expect( error.hidden ).toBe( false );
		expect( error.textContent ).toBe( 'El campo «Nombre» admite máximo 5 caracteres.' );
	} );

	it( 'con error, revalida en cada cambio: desaparece al corregir y vuelve si se deshace (R-24)', () => {
		const field = createField( { name: 'name', label: 'Nombre', required: true } );
		field.control.dispatchEvent( new Event( 'blur' ) );
		expect( field.control.getAttribute( 'aria-invalid' ) ).toBe( 'true' );

		field.control.value = 'C';
		field.control.dispatchEvent( new Event( 'input' ) );
		expect( field.control.getAttribute( 'aria-invalid' ) ).toBe( 'false' );

		// Ya sin error, borrar no regaña hasta salir del campo.
		field.control.value = '';
		field.control.dispatchEvent( new Event( 'input' ) );
		expect( field.control.getAttribute( 'aria-invalid' ) ).toBe( 'false' );
		field.control.dispatchEvent( new Event( 'blur' ) );
		expect( field.control.getAttribute( 'aria-invalid' ) ).toBe( 'true' );
	} );

	it( 'el error de la API se mantiene hasta que el usuario cambia el campo', () => {
		const field = createField( { name: 'name', label: 'Nombre', required: true, value: 'Cumpleaños' } );
		field.setError( 'Ya existe un tipo de evento llamado «Cumpleaños».' );

		field.control.value = 'Cumpleaños 2';
		field.control.dispatchEvent( new Event( 'input' ) );

		expect( field.element.querySelector( '.ep-field__error' ).hidden ).toBe( true );
	} );

	it( 'el error de la API se mantiene al salir del campo sin editarlo (QA-025)', () => {
		const field = createField( { name: 'name', label: 'Nombre', required: true, value: 'Cumpleaños' } );
		const error = field.element.querySelector( '.ep-field__error' );
		field.setError( 'Ya existe un tipo de evento llamado «Cumpleaños».' );

		field.control.dispatchEvent( new Event( 'blur' ) );
		expect( error.hidden ).toBe( false );
		expect( error.textContent ).toBe( 'Ya existe un tipo de evento llamado «Cumpleaños».' );
		expect( field.control.getAttribute( 'aria-invalid' ) ).toBe( 'true' );

		// Al cambiar el valor manda la validación local: aquí, «obligatorio».
		field.control.value = '';
		field.control.dispatchEvent( new Event( 'input' ) );
		expect( error.textContent ).toBe( 'El campo «Nombre» es obligatorio.' );
	} );

	it( 'el error de la API no impide volver a enviar: el servidor vuelve a decidir', () => {
		const onSubmit = vi.fn();
		const field = createField( { name: 'name', label: 'Nombre', required: true, value: 'Cumpleaños' } );
		const form = createForm( { fields: [ field ], onSubmit } );
		form.setErrors( { name: [ 'Ya existe un tipo de evento llamado «Cumpleaños».' ] } );

		submit( form );

		expect( onSubmit ).toHaveBeenCalledWith( { name: 'Cumpleaños' } );
	} );

	it( 'cuenta caracteres, no unidades UTF-16 (igual que mb_strlen)', () => {
		expect( characterCount( 'ñ📄' ) ).toBe( 2 );
	} );
} );

describe( 'createField: tipos de H-204', () => {
	it( 'longitud mínima con el mensaje del servidor; no aplica al campo vacío', () => {
		const field = createField( { name: 'title', label: 'Título', minLength: 3 } );

		field.setValue( 'Ab' );
		expect( field.validate() ).toBe( 'El campo «Título» debe tener al menos 3 caracteres.' );
		field.setValue( '  Ana  ' );
		expect( field.validate() ).toBeNull();
		field.setValue( '' );
		expect( field.validate() ).toBeNull();
	} );

	it( 'fecha y hora: el navegador vacía un valor imposible y el campo obligatorio lo marca', () => {
		const date = createField( { name: 'start_date', label: 'Fecha', type: 'date', required: true } );
		const time = createField( { name: 'start_time', label: 'Hora', type: 'time' } );
		expect( date.control.type ).toBe( 'date' );
		expect( time.control.type ).toBe( 'time' );

		date.control.value = '2026-02-30';
		expect( date.getValue() ).toBe( '' );
		expect( date.validate() ).toBe( 'El campo «Fecha» es obligatorio.' );
		date.control.value = '2024-02-29';
		expect( date.validate() ).toBeNull();

		time.control.value = '15:30';
		expect( time.validate() ).toBeNull();
	} );

	it( 'sin selector nativo (el campo cae a texto) valida la fecha real y la hora con los mensajes del servidor', () => {
		const date = createField( { name: 'start_date', label: 'Fecha', type: 'date' } );
		const time = createField( { name: 'start_time', label: 'Hora', type: 'time' } );
		date.control.type = 'text';
		time.control.type = 'text';

		date.control.value = '2026-02-30';
		expect( date.validate() ).toBe( 'El campo «Fecha» debe ser una fecha válida.' );
		date.control.value = '07/10/2026';
		expect( date.validate() ).toBe( 'El campo «Fecha» debe ser una fecha válida.' );
		date.control.value = '2026-10-07';
		expect( date.validate() ).toBeNull();

		time.control.value = '24:00';
		expect( time.validate() ).toBe( 'El campo «Hora» debe ser una hora válida.' );
		time.control.value = '09:05:00';
		expect( time.validate() ).toBeNull();
	} );

	it( 'lista con opción vacía, obligatoria y con opciones que se pueden reemplazar', () => {
		const onInput = vi.fn();
		const field = createField( { name: 'type_id', label: 'Tipo', type: 'select', required: true, placeholder: 'Selecciona un tipo', options: [ { value: 1, label: 'Cumpleaños' } ], onInput } );
		document.body.append( field.element );

		expect( [ ...field.control.options ].map( ( option ) => option.textContent ) ).toEqual( [ 'Selecciona un tipo', 'Cumpleaños' ] );
		expect( field.validate() ).toBe( 'El campo «Tipo» es obligatorio.' );

		field.control.value = '1';
		field.control.dispatchEvent( new Event( 'change' ) );
		expect( onInput ).toHaveBeenCalledWith( '1' );
		expect( field.getValue() ).toBe( '1' );

		field.setOptions( [ { value: 1, label: 'Cumpleaños' }, { value: 2, label: 'Reuniones' } ] );
		expect( field.getValue() ).toBe( '1' );
		expect( field.control.options ).toHaveLength( 3 );
	} );
} );

describe( 'ruleOptions con minLength', () => {
	it( 'incluye la longitud mínima del servidor', () => {
		expect( ruleOptions( { required: true, minLength: 3, maxLength: 150 } ) ).toEqual( { required: true, minLength: 3, maxLength: 150 } );
	} );
} );

describe( 'createSwitchField', () => {
	it( 'es una casilla con role="switch", etiqueta y ayuda asociadas, y valor booleano', () => {
		const field = createSwitchField( { name: 'requires_attachment', label: 'Requiere adjunto', hint: 'Imagen o PDF.', value: true } );
		document.body.append( field.element );

		expect( field.control.type ).toBe( 'checkbox' );
		expect( field.control.getAttribute( 'role' ) ).toBe( 'switch' );
		expect( field.element.querySelector( 'label' ).htmlFor ).toBe( field.control.id );
		field.control.getAttribute( 'aria-describedby' ).split( ' ' ).forEach( ( id ) => expect( document.getElementById( id ) ).not.toBeNull() );
		expect( field.getValue() ).toBe( true );

		field.control.click();
		expect( field.getValue() ).toBe( false );

		field.setValue( 1 );
		expect( field.control.checked ).toBe( true );
		expect( field.validate() ).toBeNull();
	} );

	it( 'muestra el error de la API hasta que cambia el valor', () => {
		const field = createSwitchField( { name: 'requires_attachment', label: 'Requiere adjunto' } );
		document.body.append( field.element );
		const error = field.element.querySelector( '.ep-field__error' );

		field.setError( 'El campo «Requiere adjunto» no es válido.' );
		field.validate();
		expect( error.hidden ).toBe( false );
		expect( field.control.getAttribute( 'aria-invalid' ) ).toBe( 'true' );

		field.control.click();
		expect( error.hidden ).toBe( true );
	} );

	it( 'el formulario envía su valor como booleano', () => {
		const onSubmit = vi.fn();
		const form = createForm( { fields: [ createSwitchField( { name: 'requires_attachment', label: 'Requiere adjunto' } ) ], onSubmit } );

		submit( form );

		expect( onSubmit ).toHaveBeenCalledWith( { requires_attachment: false } );
	} );
} );

describe( 'ruleOptions', () => {
	it( 'traduce las reglas de epConfig.rules a opciones del campo (SSOT con el servidor)', () => {
		expect( ruleOptions( { required: true, maxLength: 100 } ) ).toEqual( { required: true, maxLength: 100 } );
		expect( ruleOptions( { maxLength: 500 } ) ).toEqual( { required: false, maxLength: 500 } );
		expect( ruleOptions( { required: true, pattern: '^#[0-9A-Fa-f]{6}$', min: 0, max: 9999 } ) ).toEqual( { required: true, min: 0, max: 9999 } );
		expect( ruleOptions( undefined ) ).toEqual( { required: false } );
	} );
} );

describe( 'createForm', () => {
	function buildForm( onSubmit ) {
		const form = createForm( {
			fields: [ createField( { name: 'name', label: 'Nombre', required: true, value: 'Cumpleaños' } ), createField( { name: 'description', label: 'Descripción' } ) ],
			onSubmit,
		} );
		document.body.append( form.element );
		return form;
	}

	it( 'no envía si hay errores y lleva el foco al primer campo inválido', () => {
		const onSubmit = vi.fn();
		const form = buildForm( onSubmit );
		form.element.querySelector( 'input' ).value = '';

		submit( form );

		expect( onSubmit ).not.toHaveBeenCalled();
		expect( document.activeElement ).toBe( form.element.querySelector( 'input' ) );
	} );

	it( 'envía los valores sin espacios sobrantes', () => {
		const onSubmit = vi.fn();
		const form = buildForm( onSubmit );
		form.element.querySelector( 'input' ).value = '  Reunión de planeación ';

		submit( form );

		expect( onSubmit ).toHaveBeenCalledWith( { name: 'Reunión de planeación', description: '' } );
	} );

	it( 'muestra los errores por campo de la API (CP-2.17)', () => {
		const form = buildForm( vi.fn() );

		form.setErrors( { name: [ 'Ya existe un tipo de evento llamado «Cumpleaños».' ] } );

		expect( form.element.querySelector( '.ep-field__error' ).textContent ).toBe( 'Ya existe un tipo de evento llamado «Cumpleaños».' );
	} );

	it( 'detecta cambios sin guardar', () => {
		const form = buildForm( vi.fn() );
		expect( form.isDirty() ).toBe( false );

		form.element.querySelector( 'input' ).value = 'Otro nombre';
		expect( form.isDirty() ).toBe( true );
	} );

	it( 'deshabilita el botón mientras envía', async () => {
		let finish;
		const form = buildForm( () => new Promise( ( resolve ) => ( finish = resolve ) ) );
		const button = form.submitButton( 'Guardar' );
		document.body.append( button );

		submit( form );
		expect( button.disabled ).toBe( true );
		expect( button.getAttribute( 'aria-busy' ) ).toBe( 'true' );

		finish();
		await Promise.resolve();
		await Promise.resolve();
		expect( button.disabled ).toBe( false );
	} );
} );
