// @vitest-environment happy-dom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { characterCount, createField, createForm, ruleOptions } from '../../../assets/src/js/ui/form.js';

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
