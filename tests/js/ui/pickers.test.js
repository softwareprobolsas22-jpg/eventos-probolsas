// @vitest-environment happy-dom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createColorField } from '../../../assets/src/js/ui/color-field.js';
import { createIconPicker } from '../../../assets/src/js/ui/icon-picker.js';

const ICONS = [
	{ key: 'award', label: 'Distinción', keywords: 'calidad certificación' },
	{ key: 'truck', label: 'Camión', keywords: 'transporte logística' },
	{ key: 'coins', label: 'Monedas', keywords: 'finanzas' },
];

beforeEach( () => {
	document.body.innerHTML = '';
} );

describe( 'createColorField (CP-3.17)', () => {
	function build( onChange = vi.fn() ) {
		const field = createColorField( { name: 'badge_color', label: 'Color', required: true, value: '#155728', presets: [ '#155728', '#7C3AED' ], onChange } );
		document.body.append( field.element );
		return { field, onChange };
	}

	it( 'sincroniza el selector nativo con el valor', () => {
		const { field } = build();
		expect( field.getValue() ).toBe( '#155728' );
		expect( field.element.querySelector( 'input[type="color"]' ).value ).toBe( '#155728' );
		expect( field.element.querySelector( '[aria-pressed="true"]' ).dataset.color ).toBe( '#155728' );
	} );

	it( 'un color sugerido actualiza el valor y avisa el cambio', () => {
		const { field, onChange } = build();
		field.element.querySelector( '[data-color="#7C3AED"]' ).click();

		expect( field.getValue() ).toBe( '#7C3AED' );
		expect( onChange ).toHaveBeenCalledWith( '#7C3AED' );
	} );

	it( 'escribir el hexadecimal lo pasa a mayúsculas', () => {
		const { field, onChange } = build();
		const hex = field.element.querySelector( 'input[type="text"]' );
		hex.value = '#669f30';
		hex.dispatchEvent( new Event( 'input' ) );

		expect( field.getValue() ).toBe( '#669F30' );
		expect( onChange ).toHaveBeenCalledWith( '#669F30' );
	} );

	it( 'valida el formato con el mismo mensaje del servidor', () => {
		const { field } = build();
		field.setValue( 'verde' );

		expect( field.validate() ).toBe( 'El campo «Color» debe ser un color hexadecimal, por ejemplo #155728.' );
	} );

	it( 'valida al salir del hexadecimal y el error desaparece al elegir un color (R-24)', () => {
		const { field } = build();
		const hex = field.element.querySelector( 'input[type="text"]' );
		hex.value = '#66';
		hex.dispatchEvent( new Event( 'input' ) );
		expect( hex.getAttribute( 'aria-invalid' ) ).toBe( 'false' );

		hex.dispatchEvent( new Event( 'blur' ) );
		expect( hex.getAttribute( 'aria-invalid' ) ).toBe( 'true' );

		field.element.querySelector( '[data-color="#7C3AED"]' ).click();
		expect( hex.getAttribute( 'aria-invalid' ) ).toBe( 'false' );
	} );
} );

describe( 'createIconPicker (CP-3.18)', () => {
	function build( onChange = vi.fn() ) {
		const picker = createIconPicker( { name: 'icon', label: 'Ícono', icons: ICONS, value: 'truck', required: true, onChange } );
		document.body.append( picker.element );
		return { picker, onChange, options: [ ...picker.element.querySelectorAll( '[role="radio"]' ) ] };
	}

	it( 'es un grupo de opciones con la elegida marcada y alcanzable con Tab', () => {
		const { picker, options } = build();

		expect( picker.element.querySelector( '[role="radiogroup"]' ) ).not.toBeNull();
		expect( options.map( ( option ) => option.getAttribute( 'aria-checked' ) ) ).toEqual( [ 'false', 'true', 'false' ] );
		expect( options.map( ( option ) => option.tabIndex ) ).toEqual( [ -1, 0, -1 ] );
		expect( options[ 1 ].getAttribute( 'aria-label' ) ).toBe( 'Camión' );
	} );

	it( 'elegir un ícono actualiza el valor y avisa el cambio', () => {
		const { picker, onChange, options } = build();
		options[ 2 ].click();

		expect( picker.getValue() ).toBe( 'coins' );
		expect( onChange ).toHaveBeenCalledWith( 'coins' );
	} );

	it( 'busca sin mayúsculas ni tildes por etiqueta y palabras clave', () => {
		const { picker, options } = build();
		const search = picker.element.querySelector( 'input[type="search"]' );

		search.value = 'CAMION';
		search.dispatchEvent( new Event( 'input' ) );
		expect( options.filter( ( option ) => ! option.hidden ).map( ( option ) => option.dataset.key ) ).toEqual( [ 'truck' ] );

		search.value = 'certificacion';
		search.dispatchEvent( new Event( 'input' ) );
		expect( options.filter( ( option ) => ! option.hidden ).map( ( option ) => option.dataset.key ) ).toEqual( [ 'award' ] );

		search.value = 'zzz';
		search.dispatchEvent( new Event( 'input' ) );
		expect( picker.element.querySelector( '.ep-icon-picker__empty' ).hidden ).toBe( false );
	} );

	it( 'las flechas mueven el foco entre íconos', () => {
		const { options } = build();
		options[ 1 ].focus();

		options[ 1 ].dispatchEvent( new KeyboardEvent( 'keydown', { key: 'ArrowRight', bubbles: true } ) );
		expect( document.activeElement ).toBe( options[ 2 ] );

		options[ 2 ].dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Home', bubbles: true } ) );
		expect( document.activeElement ).toBe( options[ 0 ] );
	} );

	it( 'es obligatorio', () => {
		const picker = createIconPicker( { name: 'icon', label: 'Ícono', icons: ICONS, required: true } );

		expect( picker.validate() ).toBe( 'El campo «Ícono» es obligatorio.' );
	} );
} );
